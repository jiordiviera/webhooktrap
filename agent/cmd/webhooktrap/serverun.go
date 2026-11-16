package main

import (
	"context"
	"crypto/rand"
	"encoding/json"
	"errors"
	"flag"
	"fmt"
	"io"
	mrand "math/rand/v2"
	"net"
	"net/http"
	"net/netip"
	"net/url"
	"os"
	"regexp"
	"strconv"
	"strings"
	"sync"
	"time"

	"github.com/jiordiviera/webhooktrap/agent/execute"
	"github.com/jiordiviera/webhooktrap/agent/guard"
	"github.com/jiordiviera/webhooktrap/agent/internal/agentapi"
	"github.com/jiordiviera/webhooktrap/agent/internal/version"
	"github.com/jiordiviera/webhooktrap/agent/judge"
	"github.com/jiordiviera/webhooktrap/agent/observe"
	"github.com/jiordiviera/webhooktrap/agent/pack"
	"github.com/jiordiviera/webhooktrap/agent/plan"
	"github.com/jiordiviera/webhooktrap/agent/render"
	"github.com/jiordiviera/webhooktrap/agent/signing"
)

const usageServeRun = `usage: webhooktrap serve-run [flags]

The server runner (ADR-010 §12): pulls server-run jobs from /runner/v1 and
sends them, Level 1 only, through the public or allowlist guard. It reads
no webhooktrap.yml and no probe or reset settings (RN-7).

configuration (environment, or the flag of the same meaning):
  RUNNER_SERVER_ORIGIN    --server URL        the app's origin (https://; http:// only
                                              for a Compose service name or loopback)
  RUNNER_TOKEN_FILE       (never a flag)      file holding the wt_rnr_ token
  RUNNER_TOKEN            (never a flag)      the token itself, when no file is set
  RUNNER_CONCURRENCY      --concurrency N     jobs at once (default 1, at most 8)
  RUNNER_GUARD_CEILING    --ceiling MODE      widest guard: public (default) or allowlist
  RUNNER_ALLOWLIST_FILE   --allowlist FILE    local allow-list, one host:port,
                                              host:from-to or cidr:port per line

SIGTERM or Ctrl-C stops it; a job in flight is aborted on the server.
`

// The runner uses the agent's timings (contracts §6 points to §5).
const (
	runnerMaxConcurrency = 8
	runnerVerifyPause    = 30 * time.Second
)

var runnerTokenRe = regexp.MustCompile(`^wt_rnr_[0-9A-Za-z]{38}$`)

// serveRunner is one `serve-run` process.
type serveRunner struct {
	e       env
	cl      *agentapi.Client
	ar      *agentRunner // uploader and postFinal plumbing, shared with the agent
	origin  string
	ceiling guard.Mode
	local   []guard.AllowEntry
	conc    int
	// ownNets returns this host's networks (RN-4); a seam for tests.
	ownNets func() []netip.Prefix
}

func runServeRun(ctx context.Context, args []string, e env) int {
	fs := flag.NewFlagSet("serve-run", flag.ContinueOnError)
	fs.SetOutput(io.Discard)
	getenv := e.getenv
	if getenv == nil {
		getenv = func(string) string { return "" }
	}
	server := fs.String("server", getenv("RUNNER_SERVER_ORIGIN"), "")
	concDefault := 1
	if v := getenv("RUNNER_CONCURRENCY"); v != "" {
		n, err := strconv.Atoi(v)
		if err != nil {
			fmt.Fprintln(e.stderr, "webhooktrap serve-run: RUNNER_CONCURRENCY is not a number")
			return 2
		}
		concDefault = n
	}
	conc := fs.Int("concurrency", concDefault, "")
	ceilingDefault := getenv("RUNNER_GUARD_CEILING")
	if ceilingDefault == "" {
		ceilingDefault = string(guard.Public)
	}
	ceiling := fs.String("ceiling", ceilingDefault, "")
	allowFile := fs.String("allowlist", getenv("RUNNER_ALLOWLIST_FILE"), "")
	if err := fs.Parse(args); err != nil || fs.NArg() > 0 {
		if errors.Is(err, flag.ErrHelp) {
			fmt.Fprint(e.stdout, usageServeRun)
			return 0
		}
		fmt.Fprint(e.stderr, usageServeRun)
		return 2
	}
	fail := func(format string, a ...any) int {
		fmt.Fprintf(e.stderr, "webhooktrap serve-run: "+format+"\n", a...)
		return 2
	}
	origin, err := runnerOrigin(*server)
	if err != nil {
		return fail("%s", clean(err.Error()))
	}
	if *conc < 1 || *conc > runnerMaxConcurrency {
		return fail("concurrency must be 1 to %d", runnerMaxConcurrency)
	}
	token, err := runnerToken(getenv)
	if err != nil {
		return fail("%s", err.Error())
	}
	r := &serveRunner{e: e, origin: origin, conc: *conc, ownNets: hostNetworks}
	switch guard.Mode(*ceiling) {
	case guard.Public:
		r.ceiling = guard.Public
		if *allowFile != "" {
			return fail("an allow-list needs the allowlist ceiling")
		}
	case guard.Allowlist:
		r.ceiling = guard.Allowlist
		if *allowFile == "" {
			return fail("the allowlist ceiling needs RUNNER_ALLOWLIST_FILE")
		}
		if r.local, err = readAllowlist(*allowFile); err != nil {
			return fail("%s", clean(err.Error()))
		}
	default:
		return fail("the guard ceiling must be public or allowlist")
	}
	r.cl = agentapi.NewRunner(origin, token, e.http, "webhooktrap-runner/"+version.Current().Version, e.now)
	r.ar = &agentRunner{e: e, cl: r.cl}
	disableCoreDumps()
	return r.loop(ctx)
}

// runnerOrigin accepts an https origin, or http for a single-label Compose
// service name (the self-host `runner` network) or a development host.
func runnerOrigin(raw string) (string, error) {
	if raw == "" {
		return "", errors.New("RUNNER_SERVER_ORIGIN is not set")
	}
	if o, err := agentapi.NormalizeOrigin(raw, true); err == nil {
		return o, nil
	}
	u, err := url.Parse(strings.TrimSpace(raw))
	if err != nil || strings.ToLower(u.Scheme) != "http" || u.User != nil || u.RawQuery != "" || u.Fragment != "" || (u.Path != "" && u.Path != "/") {
		return "", errors.New("RUNNER_SERVER_ORIGIN must be an origin such as https://webhooktrap.example")
	}
	host := strings.ToLower(u.Hostname())
	if host == "" || strings.ContainsAny(host, ".:") {
		return "", errors.New("RUNNER_SERVER_ORIGIN must use https:// (http:// only for a Compose service name)")
	}
	for _, c := range host {
		if (c < 'a' || c > 'z') && (c < '0' || c > '9') && c != '-' && c != '_' {
			return "", errors.New("RUNNER_SERVER_ORIGIN host is not valid")
		}
	}
	if p := u.Port(); p != "" {
		n, err := strconv.Atoi(p)
		if err != nil || n < 1 || n > 65535 {
			return "", errors.New("RUNNER_SERVER_ORIGIN port is not valid")
		}
		if n != 80 {
			host += ":" + strconv.Itoa(n)
		}
	}
	return "http://" + host, nil
}

// runnerToken reads the token from RUNNER_TOKEN_FILE, else RUNNER_TOKEN;
// never from argv (contracts §1.1). The value is never printed.
func runnerToken(getenv func(string) string) (string, error) {
	tok := ""
	if f := getenv("RUNNER_TOKEN_FILE"); f != "" {
		b, err := os.ReadFile(f)
		if err != nil {
			return "", errors.New("cannot read RUNNER_TOKEN_FILE")
		}
		tok = strings.TrimSpace(string(b))
	} else {
		tok = strings.TrimSpace(getenv("RUNNER_TOKEN"))
	}
	if tok == "" {
		return "", errors.New("no runner token: set RUNNER_TOKEN_FILE")
	}
	if !runnerTokenRe.MatchString(tok) {
		return "", errors.New("the runner token is not a wt_rnr_ token")
	}
	return tok, nil
}

// readAllowlist reads the local allow-list: one entry per line, # comments.
func readAllowlist(path string) ([]guard.AllowEntry, error) {
	b, err := os.ReadFile(path)
	if err != nil {
		return nil, errors.New("cannot read the allow-list file")
	}
	var out []guard.AllowEntry
	for i, line := range strings.Split(string(b), "\n") {
		if j := strings.IndexByte(line, '#'); j >= 0 {
			line = line[:j]
		}
		if line = strings.TrimSpace(line); line == "" {
			continue
		}
		e, err := guard.ParseAllowEntry(line)
		if err != nil {
			return nil, fmt.Errorf("allow-list line %d: %w", i+1, err)
		}
		out = append(out, e)
	}
	if len(out) == 0 {
		return nil, errors.New("the allow-list file has no entries")
	}
	return out, nil
}

// hostNetworks is RN-4's "own networks" of the runner's host.
func hostNetworks() []netip.Prefix {
	addrs, err := net.InterfaceAddrs()
	if err != nil {
		return nil
	}
	return guard.OwnNetworks(addrs)
}

func (r *serveRunner) logf(format string, a ...any) {
	fmt.Fprintf(r.e.stderr, format+"\n", a...)
}

func (r *serveRunner) sleep(ctx context.Context, d time.Duration) error {
	if r.e.sleep != nil {
		return r.e.sleep(ctx, d)
	}
	return sleepCtx(ctx, d)
}

// fatalRunner reports an answer that no retry fixes: the token is refused
// (401) or the versions differ (426).
func fatalRunner(err error) bool {
	var ae *agentapi.APIError
	return errors.As(err, &ae) && (ae.Status == http.StatusUnauthorized || ae.Status == http.StatusUpgradeRequired)
}

func (r *serveRunner) fatalMessage(err error) string {
	var ae *agentapi.APIError
	if errors.As(err, &ae) && ae.Status == http.StatusUpgradeRequired {
		return fmt.Sprintf("this runner (%s) does not match the app's version at %s; deploy the runner image of the same release", version.Current().Version, r.origin)
	}
	return fmt.Sprintf("%s refused the runner token (revoked, unknown, or not from an allowed address); issue a new token", r.origin)
}

// loop says hello until the app answers, then keeps the heartbeat and the
// workers running; it reconnects with backoff on any non-fatal failure.
func (r *serveRunner) loop(ctx context.Context) int {
	backoff := time.Second
	for {
		err := r.session(ctx)
		if ctx.Err() != nil {
			r.logf("runner stopped")
			return 0
		}
		if fatalRunner(err) {
			r.logf("runner: %s", r.fatalMessage(err))
			return 1
		}
		wait := backoff + time.Duration(mrand.Int64N(int64(backoff)/5+1))
		r.logf("runner: %s unreachable (%s); retrying in %s", r.origin, clean(err.Error()), wait.Round(100*time.Millisecond))
		if r.sleep(ctx, wait) != nil {
			r.logf("runner stopped")
			return 0
		}
		backoff = min(backoff*2, maxBackoff)
	}
}

func (r *serveRunner) session(ctx context.Context) error {
	if err := r.cl.HelloRunner(ctx, version.Current().Version); err != nil {
		return err
	}
	r.logf("runner connected origin=%s version=%s ceiling=%s concurrency=%d", r.origin, version.Current().Version, r.ceiling, r.conc)
	sctx, cancel := context.WithCancel(ctx)
	defer cancel()
	errs := make(chan error, r.conc+1)
	var wg sync.WaitGroup
	wg.Add(1)
	go func() {
		defer wg.Done()
		t := time.NewTicker(heartbeatEvery)
		defer t.Stop()
		for {
			select {
			case <-sctx.Done():
				return
			case <-t.C:
			}
			if err := r.cl.HelloRunner(sctx, version.Current().Version); fatalRunner(err) {
				errs <- err
				return
			}
		}
	}()
	for i := 0; i < r.conc; i++ {
		wg.Add(1)
		go func() {
			defer wg.Done()
			if err := r.worker(sctx); err != nil {
				errs <- err
			}
		}()
	}
	var first error
	select {
	case first = <-errs:
	case <-ctx.Done():
	}
	cancel()
	wg.Wait()
	return first
}

// worker long-polls and runs one job at a time. It returns on a fatal
// answer or when the long-poll fails (the session then reconnects).
func (r *serveRunner) worker(ctx context.Context) error {
	for {
		job, err := r.cl.NextJob(ctx, longPollWait)
		if ctx.Err() != nil {
			return nil
		}
		if err != nil {
			var ae *agentapi.APIError
			if errors.As(err, &ae) && ae.Status == http.StatusServiceUnavailable {
				wait := ae.RetryAfter
				if wait <= 0 {
					wait = skipPause
				}
				if r.sleep(ctx, min(wait, maxRetryAfter)) != nil {
					return nil
				}
				continue
			}
			return err
		}
		if job == nil {
			continue
		}
		if err := r.handleJob(ctx, job); err != nil {
			return err
		}
	}
}

// runnerJob is what the pre-accept checks resolved.
type runnerJob struct {
	tgt       *guard.Target
	pk        *pack.Pack
	scenarios []plan.Scenario
	seed      uint64
	timeout   time.Duration
	cap       int64
}

// handleJob checks a job, accepts it and runs it. A job the runner can't
// take is accepted and aborted with its reason, so it leaves the queue
// (contracts §6: the runner API has no decline). It returns only fatal
// errors; everything else is logged.
func (r *serveRunner) handleJob(ctx context.Context, job *agentapi.Job) error {
	if err := agentapi.ValidateJob(job); err != nil {
		r.logf("runner: ignored a job it can't read: %s", clean(err.Error()))
		return r.sleep(ctx, skipPause)
	}
	if job.Kind != "run" { // the runner never receives verify jobs (contracts §6)
		r.logf("runner: ignored a %s job", clean(job.Kind))
		return r.sleep(ctx, runnerVerifyPause)
	}
	prep, reason, why := r.check(ctx, job)
	if err := r.cl.Accept(ctx, job.JobID); err != nil {
		if fatalRunner(err) {
			return err
		}
		r.logf("runner: run %s not taken: %s", job.RunID, clean(err.Error()))
		return nil
	}
	if prep == nil {
		r.logf("runner: run %s refused: %s (%s)", job.RunID, clean(why), reason)
		return r.abortJob(ctx, job, reason)
	}
	if err := r.runJob(ctx, job, prep); err != nil {
		if fatalRunner(err) {
			return err
		}
		reason := abortReason(ctx, err)
		r.logf("runner: run %s stopped: %s (%s)", job.RunID, clean(err.Error()), reason)
		return r.abortJob(ctx, job, reason)
	}
	r.logf("runner: run %s finished", job.RunID)
	return nil
}

func (r *serveRunner) abortJob(ctx context.Context, job *agentapi.Job, reason string) error {
	err := r.ar.abort(ctx, job.JobID, reason)
	if err != nil && !errors.Is(err, errNothingToAbort) {
		if fatalRunner(err) {
			return err
		}
		r.logf("runner: abort of run %s didn't reach the app (%s); it expires the job", job.RunID, clean(err.Error()))
	}
	return nil
}

// check runs the local checks: the guard ceiling and allow-list (RN-3), the
// app's own addresses and the runner's networks (RN-4), the target, the
// pack and the scenarios. Nothing is dialled.
func (r *serveRunner) check(ctx context.Context, job *agentapi.Job) (*runnerJob, string, string) {
	requested := guard.Mode(job.Guard)
	if requested == "" {
		requested = guard.Public
	}
	var entries []guard.AllowEntry
	for _, a := range job.Allowlist {
		e, err := guard.NewAllowEntry(a.Host, a.CIDR, a.PortFrom, a.PortTo)
		if err != nil {
			return nil, agentapi.AbortGuardExceedsCeiling, err.Error()
		}
		entries = append(entries, e)
	}
	g, err := guard.NewServer(r.ceiling, r.local, requested, entries, r.deny(ctx))
	if err != nil {
		return nil, agentapi.AbortGuardExceedsCeiling, err.Error()
	}
	if r.e.resolver != nil {
		g.Resolver = r.e.resolver
	}
	tgt, err := g.Check(ctx, job.TargetRef)
	if err != nil {
		var ge *guard.Error
		if errors.As(err, &ge) && ge.Reason == agentapi.AbortTargetUnreachable {
			return nil, agentapi.AbortTargetUnreachable, err.Error()
		}
		return nil, agentapi.AbortTargetNotAllowed, err.Error()
	}
	pk, err := r.e.packs.Load(job.Pack.ID, job.Pack.Version)
	if err != nil {
		return nil, agentapi.AbortInternalError, "pack " + job.Pack.ID + " " + job.Pack.Version + " is not in this build"
	}
	var scenarios []plan.Scenario
	for _, s := range job.Scenarios {
		sc, ok := plan.ParseScenario(s)
		if !ok {
			return nil, agentapi.AbortInternalError, "unknown scenario " + display(s, 32)
		}
		scenarios = append(scenarios, sc)
	}
	seed, err := strconv.ParseUint(job.Seed, 10, 64)
	if err != nil {
		return nil, agentapi.AbortInternalError, "the seed is out of range"
	}
	p := &runnerJob{tgt: tgt, pk: pk, scenarios: scenarios, seed: seed}
	if job.Limits != nil {
		p.timeout = time.Duration(job.Limits.RequestTimeoutMS) * time.Millisecond
		p.cap = job.Limits.ResponseCapBytes
	}
	return p, "", ""
}

// deny is RN-4: the runner-API origin's addresses, re-resolved per job,
// and the runner's own networks.
func (r *serveRunner) deny(ctx context.Context) []netip.Prefix {
	var out []netip.Prefix
	if r.ownNets != nil {
		out = append(out, r.ownNets()...)
	}
	u, err := url.Parse(r.origin)
	if err != nil {
		return out
	}
	host := u.Hostname()
	if a, err := netip.ParseAddr(host); err == nil {
		return append(out, guard.HostPrefixes([]netip.Addr{a})...)
	}
	var res guard.Resolver = net.DefaultResolver
	if r.e.resolver != nil {
		res = r.e.resolver
	}
	rctx, cancel := context.WithTimeout(ctx, 5*time.Second)
	defer cancel()
	if addrs, err := res.LookupNetIP(rctx, "ip", host); err == nil {
		out = append(out, guard.HostPrefixes(addrs)...)
	}
	return out
}

// runJob is the agent's runJob without probes, reset, secrets or a local
// config: Level 1 only (ADR-010 §12.5).
func (r *serveRunner) runJob(ctx context.Context, job *agentapi.Job, p *runnerJob) error {
	var caps []plan.Capture
	for _, ref := range job.Selection {
		cr, err := r.cl.FetchCapture(ctx, job.JobID, ref.CaptureID)
		if err != nil {
			return fmt.Errorf("fetching capture %s: %w", ref.CaptureID, err)
		}
		body, err := agentapi.VerifyCapture(ref, cr)
		if err != nil {
			return abortWith(agentapi.AbortCaptureVerificationFailed, fmt.Errorf("capture %s: %w", ref.CaptureID, err))
		}
		caps = append(caps, plan.Capture{Ref: ref.CaptureID, Provider: job.Pack.ID, CaptureOrder: ref.Seq, Headers: agentapi.ResendableHeaders(cr), Body: body})
	}
	budget := plan.Budget{MS: p.pk.Budget.MS, Source: p.pk.Budget.Source, Label: p.pk.Budget.Label, SourceLabel: p.pk.Budget.SourceLabel}
	pl, err := plan.Build(plan.Input{Captures: caps, Scenarios: p.scenarios, Pack: p.pk, Seed: p.seed, Budget: budget})
	if err != nil {
		return fmt.Errorf("plan: %s", err.Error())
	}
	salt := r.e.salt
	if salt == nil {
		salt = make([]byte, 16)
		_, _ = rand.Read(salt)
	}
	planJSON, _ := json.Marshal(pl)
	count := len(pl.Deliveries)
	up := newUploader(r.ar, job.RunID, count, nil)
	if err := up.post(ctx, agentapi.Observations{Seq: 1, Plan: planJSON, PlanHash: render.HostedPlanHash(pl.Hash), DeliveryCount: &count}); err != nil {
		return err
	}
	go up.run(ctx)

	clock, sched := r.e.clock, r.e.scheduler
	if clock == nil {
		clock = execute.NewSystemClock()
	}
	if sched == nil {
		sched = execute.SystemScheduler{}
	}
	var tr execute.Transport = &execute.HTTPTransport{Target: p.tgt, Clock: clock, Timeout: p.timeout, ResponseCap: p.cap}
	if r.e.wrap != nil {
		tr = r.e.wrap(tr)
	}
	start := clock.Wall()
	obs, err := execute.Run(ctx, execute.Options{
		RunID: job.RunID, Plan: pl, Captures: caps, Pack: p.pk, Secret: signing.Secret{}, Target: p.tgt,
		Transport: tr, Clock: clock, Scheduler: sched, // no Probe, no Reset: Level 1 only
		OnDelivery: func(pd plan.Delivery, o observe.Delivery) {
			up.push(render.Live(pd, o, nil, salt, pl.Budget.MS))
		},
	})
	upErr := up.close()
	if err != nil {
		return err
	}
	if upErr != nil && (fatalRunner(upErr) || agentapi.IsCode(upErr, "run_final")) {
		return upErr
	}
	v := judge.Judge(pl, obs, p.pk, judge.Options{FailOn: job.FailOn, TargetPath: p.tgt.Path})
	in := render.Input{Plan: pl, Obs: obs, Verdict: v, Pack: p.pk, Meta: render.Meta{
		RunID: job.RunID, StartedAt: rfc3339(start), FinishedAt: rfc3339(clock.Wall()),
		Target:   render.Target{Scheme: p.tgt.Scheme, Host: p.tgt.Host, Port: p.tgt.Port, Path: p.tgt.Path},
		Executor: "server", Source: "web", AgentVersion: version.Current().Version,
		ProbeEntries: []render.ProbeEntry{}, SideEffects: true, Salt: salt,
	}}
	doc, err := render.HostedJSON(in)
	if err != nil {
		return err
	}
	return r.ar.postFinal(ctx, job.RunID, doc)
}
