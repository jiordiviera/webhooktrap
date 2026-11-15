package main

import (
	"bufio"
	"context"
	"crypto/rand"
	"encoding/binary"
	"encoding/json"
	"errors"
	"flag"
	"fmt"
	"io"
	"net/http"
	"net/url"
	"os"
	"path/filepath"
	"slices"
	"strconv"
	"strings"
	"time"

	"github.com/jiordiviera/webhooktrap/agent/execute"
	"github.com/jiordiviera/webhooktrap/agent/guard"
	"github.com/jiordiviera/webhooktrap/agent/internal/agentstate"
	"github.com/jiordiviera/webhooktrap/agent/internal/config"
	"github.com/jiordiviera/webhooktrap/agent/internal/fixture"
	"github.com/jiordiviera/webhooktrap/agent/internal/ids"
	"github.com/jiordiviera/webhooktrap/agent/internal/packdata"
	"github.com/jiordiviera/webhooktrap/agent/internal/version"
	"github.com/jiordiviera/webhooktrap/agent/judge"
	"github.com/jiordiviera/webhooktrap/agent/pack"
	"github.com/jiordiviera/webhooktrap/agent/plan"
	"github.com/jiordiviera/webhooktrap/agent/render"
	"github.com/jiordiviera/webhooktrap/agent/scrub"
	"github.com/jiordiviera/webhooktrap/agent/signing"
)

// env is everything `test` reads from the outside world. Tests replace the
// seams to get a deterministic run (golden-files §6: determinism by injection).
type env struct {
	stdout, stderr io.Writer
	stdin          io.Reader
	in             *bufio.Reader // shared line reader over stdin, so prompts don't lose buffered answers
	tty            bool
	getenv         func(string) string
	environ        []string
	packs          pack.Source

	clock     execute.Clock
	scheduler execute.Scheduler
	wrap      func(execute.Transport) execute.Transport
	reset     execute.Resetter
	salt      []byte
	resolver  guard.Resolver

	// Agent mode seams.
	store    *agentstate.Store
	http     *http.Client
	now      func() time.Time
	sleep    func(context.Context, time.Duration) error
	hostname func() (string, error)
}

func realEnv(stdout, stderr io.Writer) env {
	st, _ := os.Stdin.Stat()
	return env{
		stdout: stdout, stderr: stderr, stdin: os.Stdin, in: bufio.NewReader(os.Stdin),
		tty:    st != nil && st.Mode()&os.ModeCharDevice != 0,
		getenv: os.Getenv, environ: os.Environ(), packs: packdata.Source(),
		now: time.Now, sleep: sleepCtx, hostname: os.Hostname,
	}
}

const usageTest = `usage: webhooktrap test [flags]

Runs the scenarios against the target in webhooktrap.yml with local fixtures,
prints the verdict, writes the run document and exits per --fail-on.

flags:
  --config FILE            config file (default webhooktrap.yml)
  --fail-on LEVEL          critical | high | medium | never (default high)
  --fail-on-inconclusive   exit 1 when the run is Inconclusive
  --scenarios LIST         comma-separated scenarios (default: all)
  --seed N                 run seed (default: random, printed with --verbose)
  --out FILE               run document path (default .webhooktrap/runs/<run id>.json)
  --json                   print the run document instead of text
  --ascii                  ASCII glyphs
  --yes                    confirm probe/reset commands and the side-effect warning
  --packs DIR              read packs from DIR instead of the embedded copy
  --insecure-localhost     skip TLS verification for a local https target
  --allow-untrusted-config allow cmd: probes and reset in a fork pull request context
  --verbose                print the seed and absolute times
`

type testFlags struct {
	config, failOn, scenarios, out, packs string
	seed                                  string
	failOnInconclusive, json, ascii, yes  bool
	insecure, allowUntrusted, verbose     bool
}

func runTest(ctx context.Context, args []string, e env) int {
	var f testFlags
	fs := flag.NewFlagSet("test", flag.ContinueOnError)
	fs.SetOutput(io.Discard)
	fs.StringVar(&f.config, "config", "webhooktrap.yml", "")
	fs.StringVar(&f.failOn, "fail-on", "high", "")
	fs.BoolVar(&f.failOnInconclusive, "fail-on-inconclusive", false, "")
	fs.StringVar(&f.scenarios, "scenarios", "", "")
	fs.StringVar(&f.seed, "seed", "", "")
	fs.StringVar(&f.out, "out", "", "")
	fs.BoolVar(&f.json, "json", false, "")
	fs.BoolVar(&f.ascii, "ascii", false, "")
	fs.BoolVar(&f.yes, "yes", false, "")
	fs.StringVar(&f.packs, "packs", "", "")
	fs.BoolVar(&f.insecure, "insecure-localhost", false, "")
	fs.BoolVar(&f.allowUntrusted, "allow-untrusted-config", false, "")
	fs.BoolVar(&f.verbose, "verbose", false, "")
	if err := fs.Parse(args); err != nil {
		if errors.Is(err, flag.ErrHelp) {
			fmt.Fprint(e.stdout, usageTest)
			return 0
		}
		fmt.Fprintf(e.stderr, "webhooktrap test: %v\n\n%s", clean(err.Error()), usageTest)
		return 3
	}
	if fs.NArg() > 0 {
		fmt.Fprintf(e.stderr, "webhooktrap test: unexpected argument %q\n", clean(fs.Arg(0)))
		return 3
	}
	defErr := func(format string, a ...any) int {
		fmt.Fprintf(e.stderr, "webhooktrap test: "+format+"\n", a...)
		return 3 // EXIT-1: invalid run definition, nothing sent
	}
	switch f.failOn {
	case "critical", "high", "medium", "never":
	default:
		return defErr("--fail-on must be critical, high, medium or never")
	}

	raw, err := readRegular(f.config, 1<<20)
	if err != nil {
		return defErr("can't read %s: %s", clean(f.config), clean(err.Error()))
	}
	cfg, err := config.Parse(raw)
	if err != nil {
		return defErr("%s", clean(err.Error()))
	}
	if cfg.Target == "" {
		return defErr("webhooktrap.yml: target is required")
	}
	for _, p := range cfg.Probe {
		if p.Kind == "sql" {
			return defErr("probe %s: sql: probes need database drivers that aren't in this build yet; use cmd: (for example psql \"$DATABASE_URL\" -Atc \"...\")", p.As)
		}
	}
	src := e.packs
	if f.packs != "" {
		src = pack.FSSource{FS: os.DirFS(f.packs)}
	}

	// Captures from local fixtures.
	paths := cfg.Fixtures
	if len(paths) == 0 {
		if cfg.Provider == "" {
			return defErr("webhooktrap.yml: set fixtures, or provider to read webhooktrap/fixtures/<provider>/")
		}
		if paths, err = fixture.Discover(filepath.Join("webhooktrap", "fixtures"), cfg.Provider); err != nil {
			return defErr("%s", clean(err.Error()))
		}
	}
	var caps []plan.Capture
	for _, p := range paths {
		c, err := fixture.Load(p)
		if err != nil {
			return defErr("%s", clean(err.Error()))
		}
		caps = append(caps, c)
	}
	provider := cfg.Provider
	if provider == "" {
		provider = caps[0].Provider
	}
	pk, err := src.Load(provider, "")
	if err != nil {
		return defErr("no pack for provider %q: %s", clean(provider), clean(err.Error()))
	}

	budget, err := resolveBudget(cfg, pk)
	if err != nil {
		return defErr("%s", err.Error())
	}
	secret, secretNames := resolveSecret(cfg, pk, e.getenv)
	masker := scrub.NewMasker(secret)

	scenarios := plan.Order
	list := cfg.Scenarios
	if f.scenarios != "" {
		list = strings.Split(f.scenarios, ",")
	}
	if len(list) > 0 {
		scenarios = nil
		for _, s := range list {
			sc, ok := plan.ParseScenario(strings.TrimSpace(s))
			if !ok {
				return defErr("unknown scenario %q (use %s)", clean(s), scenarioNames())
			}
			scenarios = append(scenarios, sc)
		}
	}

	seed, err := parseSeed(f.seed)
	if err != nil {
		return defErr("--seed must be an unsigned integer")
	}
	pl, err := plan.Build(plan.Input{Captures: caps, Scenarios: scenarios, Pack: pk, Seed: seed, HasSecret: !secret.IsZero(), Budget: budget})
	if err != nil {
		return defErr("%s", clean(err.Error()))
	}

	// Probe and reset (PRB-1 to PRB-11, TM-53, TM-62).
	strip := allSecretNames(src)
	strip = append(strip, secretNames...)
	safe := execute.SafeEnv(e.environ, strip)
	g := guard.New(cfg.Targets)
	if e.resolver != nil {
		g.Resolver = e.resolver
	}
	ps, err := setupProbes(cfg, caps, pk, safe, g)
	if err != nil {
		return defErr("%s", clean(err.Error()))
	}
	prober, covers, entries, resetter, hasCmd := ps.prober, ps.covers, ps.entries, ps.resetter, ps.hasCmd
	if hasCmd && e.getenv("GITHUB_EVENT_NAME") == "pull_request_target" && !f.allowUntrusted {
		return defErr("refusing cmd: probes and reset in a pull_request_target run (TM-62); pass --allow-untrusted-config only for trusted code")
	}

	// Target (TGT-1 to TGT-5): checked and pinned before anything runs.
	tgt, err := g.Check(ctx, cfg.Target)
	if err != nil {
		reason := "target_not_allowed"
		var ge *guard.Error
		if errors.As(err, &ge) {
			reason = ge.Reason
		}
		fmt.Fprintf(e.stderr, "Couldn't start the run · %s\n%s\n", reason, clean(err.Error()))
		return 2
	}

	// Confirmations (PRB-11, NAME-4), remembered in .webhooktrap/.
	st := loadState()
	var notes []string
	if hasCmd && !slices.Contains(st.Confirmed, cfg.Hash) {
		if !confirm(e, f.yes, commandsPrompt(cfg)) {
			return defErr("webhooktrap.yml runs commands; confirm them interactively or pass --yes")
		}
		st.Confirmed = append(st.Confirmed, cfg.Hash)
	}
	key := tgt.Scheme + "://" + tgt.Host + ":" + strconv.Itoa(tgt.Port)
	ack := slices.Contains(st.Acknowledged, key)
	if !ack {
		const warn = "This run sends the same events several times. If your handler sends emails or charges cards, honour the Webhooktrap-Dry-Run header first."
		if !confirm(e, f.yes, warn+" Continue? [y/N] ") {
			return defErr("acknowledge the side-effect warning interactively or pass --yes")
		}
		st.Acknowledged = append(st.Acknowledged, key)
		notes = append(notes, warn)
		ack = true
	}
	_ = saveState(st)
	if tgt.Warning != "" {
		notes = append(notes, tgt.Warning)
	}

	// Execute.
	clock, sched := e.clock, e.scheduler
	if clock == nil {
		clock = execute.NewSystemClock()
	}
	if sched == nil {
		sched = execute.SystemScheduler{}
	}
	if e.reset != nil && resetter != nil {
		resetter = e.reset
	}
	start := clock.Wall()
	runID := ids.RunID(start.UnixMilli(), ids.NewRand(seed))
	var tr execute.Transport = &execute.HTTPTransport{Target: tgt, Clock: clock, InsecureLocalhost: f.insecure}
	if e.wrap != nil {
		tr = e.wrap(tr)
	}
	if !f.json {
		fmt.Fprintf(e.stderr, "Running %d deliveries against %s …\n", len(pl.Deliveries), clean(cfg.Target))
	}
	obs, err := execute.Run(ctx, execute.Options{
		RunID: runID, Plan: pl, Captures: caps, Pack: pk, Secret: secret, Target: tgt,
		Transport: tr, Clock: clock, Scheduler: sched, Probe: prober, Reset: resetter, Settle: cfg.Settle,
		OnProbeError: func(err error) { fmt.Fprintln(e.stderr, "probe: "+clean(masker.Mask(err.Error()))) },
	})
	if err != nil {
		fmt.Fprintln(e.stderr, "webhooktrap test: "+clean(err.Error()))
		return 2
	}
	finished := clock.Wall()

	v := judge.Judge(pl, obs, pk, judge.Options{
		FailOn: f.failOn, FailOnInconclusive: f.failOnInconclusive, Framework: cfg.Framework,
		TargetPath: tgt.Path, Covers: covers,
	})
	salt := e.salt
	if salt == nil {
		salt = make([]byte, 16) // D-3a: OS CSPRNG, never written
		_, _ = rand.Read(salt)
	}
	in := render.Input{Plan: pl, Obs: obs, Verdict: v, Pack: pk, Meta: render.Meta{
		RunID: runID, StartedAt: rfc3339(start), FinishedAt: rfc3339(finished),
		Target:   render.Target{Scheme: tgt.Scheme, Host: tgt.Host, Port: tgt.Port, Path: tgt.Path},
		Executor: "ci", Source: "cli", AgentVersion: version.Current().Version,
		ProbeEntries: entries, SideEffects: ack, Warnings: notes, Salt: salt,
	}}
	doc, err := render.JSON(in)
	if err != nil {
		fmt.Fprintln(e.stderr, "webhooktrap test: "+clean(err.Error()))
		return 2
	}
	if masker.Contains(string(doc)) { // JSON-2: never write a secret
		fmt.Fprintln(e.stderr, "webhooktrap test: refusing to write a run document that contains a secret")
		return 2
	}
	out := f.out
	if out == "" {
		out = filepath.Join(".webhooktrap", "runs", runID+".json")
	}
	if err := writePrivate(out, doc); err != nil {
		fmt.Fprintln(e.stderr, "webhooktrap test: can't write the run document: "+clean(err.Error()))
	}
	if f.json {
		_, _ = e.stdout.Write(doc)
		return v.ExitCode
	}
	prof := render.Unicode
	if f.ascii || e.getenv("TERM") == "dumb" {
		prof = render.ASCIIProfile
	}
	text := masker.Mask(render.Text(in, prof))
	fmt.Fprint(e.stdout, text)
	fmt.Fprintln(e.stdout, "  run file  "+clean(out))
	if f.verbose {
		fmt.Fprintf(e.stdout, "  seed      %d · started %s\n", seed, rfc3339(start))
	}
	fmt.Fprintln(e.stdout, render.ExitLine(v))
	return v.ExitCode
}

// resolveBudget applies webhooktrap.yml's budget over the pack's (BUD-1, BUD-2).
func resolveBudget(cfg *config.Config, pk *pack.Pack) (plan.Budget, error) {
	budget := plan.Budget{MS: pk.Budget.MS, Source: pk.Budget.Source, Label: pk.Budget.Label, SourceLabel: pk.Budget.SourceLabel}
	if cfg != nil && cfg.Budget > 0 {
		if pk.Budget.Documented && cfg.Budget > pk.Budget.MS {
			return budget, fmt.Errorf("budget %d ms is above %s's documented limit of %d ms (BUD-2)", cfg.Budget, pk.Name, pk.Budget.MS)
		}
		budget = plan.Budget{MS: cfg.Budget, Source: "webhooktrap.yml", Label: strconv.Itoa(cfg.Budget) + " ms budget · webhooktrap.yml", SourceLabel: "webhooktrap.yml"}
	}
	return budget, nil
}

// resolveSecret reads the signing secret from the environment. Only names
// ever leave this function (SIG-3, JSON-2).
func resolveSecret(cfg *config.Config, pk *pack.Pack, getenv func(string) string) (signing.Secret, []string) {
	names := pk.Signing.SecretEnv
	if cfg != nil && cfg.Secret != "" {
		names = []string{cfg.Secret}
	}
	for _, n := range names {
		if v := getenv(n); v != "" {
			return signing.NewSecret(n, []byte(v)), names
		}
	}
	return signing.Secret{}, names
}

type probeSetup struct {
	prober   execute.Prober
	covers   map[string][]string
	entries  []render.ProbeEntry
	resetter execute.Resetter
	hasCmd   bool
}

// setupProbes resolves probe templates for the captures and builds the
// prober and reset hook (PRB-1 to PRB-11).
func setupProbes(cfg *config.Config, caps []plan.Capture, pk *pack.Pack, safe []string, g *guard.Guard) (probeSetup, error) {
	var ps probeSetup
	if len(cfg.Probe) > 0 {
		cp := &execute.CommandProbe{Env: safe, Timeout: cfg.ProbeTimeout, Guard: g}
		ps.covers = map[string][]string{}
		for _, p := range cfg.Probe {
			vals, err := plan.ResolveTemplate(p.Text, caps, pk)
			if err != nil {
				return ps, err
			}
			cp.Entries = append(cp.Entries, probeEntry(p, vals))
			ps.covers[p.As] = p.Covers
			ps.entries = append(ps.entries, render.ProbeEntry{Name: p.As, DisplayMode: "code", Covers: p.Covers})
		}
		ps.prober = cp
	}
	if cfg.Reset != "" {
		ps.resetter = &execute.ShellReset{Command: cfg.Reset, Env: safe}
	}
	ps.hasCmd = hasCommands(cfg)
	return ps, nil
}

func hasCommands(cfg *config.Config) bool {
	return cfg.Reset != "" || slices.ContainsFunc(cfg.Probe, func(p config.Probe) bool { return p.Kind == "cmd" })
}

func scenarioNames() string {
	var xs []string
	for _, s := range plan.Order {
		xs = append(xs, string(s))
	}
	return strings.Join(xs, ", ")
}

func parseSeed(s string) (uint64, error) {
	if s != "" {
		return strconv.ParseUint(s, 10, 64)
	}
	var b [8]byte
	_, _ = rand.Read(b[:])
	return binary.BigEndian.Uint64(b[:]) >> 1, nil
}

// probeEntry fills resolved {{T.id}} templates for the probe kind (AC-1).
// url: the value, query-escaped. cmd: a reference to WT_<T>_ID written for
// the template's quoting context (execute.EnvRef) and the value in the
// entry's environment; the value is never put in the command text, so the
// shell never parses it.
func probeEntry(p config.Probe, vals map[string]string) execute.ProbeEntry {
	e := execute.ProbeEntry{Name: p.As, Kind: p.Kind}
	switch p.Kind {
	case "cmd":
		e.Text = plan.ReplaceTemplates(p.Text, func(t string, at int) string { return execute.EnvRef(p.Text, at, execute.TemplateEnvName(t)) })
		for _, t := range plan.TemplateNames(p.Text) {
			e.Env = append(e.Env, execute.TemplateEnvName(t)+"="+vals[t])
		}
	default:
		e.Text = plan.ReplaceTemplates(p.Text, func(t string, _ int) string { return url.QueryEscape(vals[t]) })
	}
	return e
}

func allSecretNames(src pack.Source) []string {
	var out []string
	ids, _ := src.Providers()
	for _, id := range ids {
		if p, err := src.Load(id, ""); err == nil {
			out = append(out, p.Signing.SecretEnv...)
		}
	}
	return out
}

func commandsPrompt(c *config.Config) string {
	var b strings.Builder
	b.WriteString("webhooktrap.yml runs these commands on this machine:\n")
	for _, p := range c.Probe {
		fmt.Fprintf(&b, "  probe %s (%s): %s\n", p.As, p.Kind, clean(p.Text))
	}
	if c.Reset != "" {
		fmt.Fprintf(&b, "  reset: %s\n", clean(c.Reset))
	}
	b.WriteString("Template values such as {{invoice.id}} reach a command only as an environment variable (WT_INVOICE_ID), and only if they are plain ids.\n")
	b.WriteString("Probe values stay on your machine. The web app shows counts up to 9 999 as numbers and other values as short codes such as #a1f3c0, which only show whether a value changed.\nRun them? [y/N] ")
	return b.String()
}

func confirm(e env, yes bool, prompt string) bool {
	if yes {
		return true
	}
	if !e.tty {
		return false
	}
	fmt.Fprint(e.stderr, prompt)
	return strings.EqualFold(strings.TrimSpace(e.readLine()), "y")
}

// readLine reads one answer line from stdin.
func (e env) readLine() string {
	r := e.in
	if r == nil {
		r = bufio.NewReader(e.stdin)
	}
	line, _ := r.ReadString('\n')
	return line
}

func sleepCtx(ctx context.Context, d time.Duration) error {
	t := time.NewTimer(d)
	defer t.Stop()
	select {
	case <-ctx.Done():
		return ctx.Err()
	case <-t.C:
		return nil
	}
}

type state struct {
	Confirmed    []string `json:"confirmed_configs"`
	Acknowledged []string `json:"acknowledged_targets"`
}

func loadState() state {
	var s state
	if raw, err := readRegular(filepath.Join(".webhooktrap", "state.json"), 1<<20); err == nil {
		_ = json.Unmarshal(raw, &s)
	}
	return s
}

func saveState(s state) error {
	raw, _ := json.MarshalIndent(s, "", "  ")
	return writePrivate(filepath.Join(".webhooktrap", "state.json"), append(raw, '\n'))
}

// writePrivate writes a file 0600 in a 0700 directory, with a .gitignore of
// "*" in .webhooktrap/ (NAME-2).
func writePrivate(path string, data []byte) error {
	dir := filepath.Dir(path)
	if err := os.MkdirAll(dir, 0o700); err != nil {
		return err
	}
	if root := strings.Split(filepath.ToSlash(path), "/")[0]; root == ".webhooktrap" {
		gi := filepath.Join(".webhooktrap", ".gitignore")
		if _, err := os.Stat(gi); err != nil {
			_ = os.WriteFile(gi, []byte("*\n"), 0o600)
		}
	}
	tmp := path + ".tmp"
	if err := os.WriteFile(tmp, data, 0o600); err != nil {
		return err
	}
	return os.Rename(tmp, path)
}

func readRegular(path string, limit int64) ([]byte, error) {
	st, err := os.Lstat(path)
	if err != nil {
		return nil, err
	}
	if !st.Mode().IsRegular() {
		return nil, errors.New("not a regular file")
	}
	f, err := os.Open(path)
	if err != nil {
		return nil, err
	}
	defer f.Close()
	return io.ReadAll(io.LimitReader(f, limit))
}

func rfc3339(t time.Time) string { return t.UTC().Format("2006-01-02T15:04:05.000Z") }

// clean makes text from files, flags, errors, the server or captures safe
// for a terminal: the one shared cleaner (scrub.Terminal, AC-2).
func clean(s string) string { return scrub.Terminal(s) }
