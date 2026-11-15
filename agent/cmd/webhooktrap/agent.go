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
	"net/url"
	"os"
	"regexp"
	"slices"
	"strconv"
	"strings"
	"sync"
	"time"

	"github.com/jiordiviera/webhooktrap/agent/execute"
	"github.com/jiordiviera/webhooktrap/agent/guard"
	"github.com/jiordiviera/webhooktrap/agent/internal/agentapi"
	"github.com/jiordiviera/webhooktrap/agent/internal/agentstate"
	"github.com/jiordiviera/webhooktrap/agent/internal/config"
	"github.com/jiordiviera/webhooktrap/agent/internal/version"
	"github.com/jiordiviera/webhooktrap/agent/judge"
	"github.com/jiordiviera/webhooktrap/agent/observe"
	"github.com/jiordiviera/webhooktrap/agent/pack"
	"github.com/jiordiviera/webhooktrap/agent/plan"
	"github.com/jiordiviera/webhooktrap/agent/render"
	"github.com/jiordiviera/webhooktrap/agent/scrub"
)

const usageAgent = `usage: webhooktrap agent [flags]
       webhooktrap agent unpair [--server URL]
       webhooktrap agent pair <CODE> [flags]   (same as webhooktrap pair)

Connects this machine to the server it was paired with and runs the
scenarios started from the browser against the target declared in
webhooktrap.yml. Every new target is approved once, here, in the terminal.
The agent sends the server secret names, never values, and opens no
listening socket. Ctrl-C stops it.

flags:
  --server URL    which paired server (default: the only one, or $WEBHOOKTRAP_SERVER)
  --config FILE   config file with the declared target, secret names and probes
                  (default webhooktrap.yml)
  --once          exit after the first run job
  --packs DIR     read packs from DIR instead of the embedded copy
  --ascii         ASCII glyphs
`

// Timings of the connected mode (contracts §5.3, §5.4).
const (
	heartbeatEvery = 20 * time.Second
	longPollWait   = 25
	maxBackoff     = 60 * time.Second
	maxRetryAfter  = 5 * time.Minute
	skipPause      = 5 * time.Second
	// A job declined with needs_confirmation stays queued on the server, so
	// the next long-poll returns it at once; the agent waits between
	// declines, doubling from skipPause up to maxConfirmWait (AC-8).
	maxConfirmWait = time.Minute
	abortTimeout   = 10 * time.Second
)

var envNameRe = regexp.MustCompile(`^[A-Z_][A-Z0-9_]{0,63}$`) // TM-39

// exitErr ends the agent with a code and a message.
type exitErr struct {
	code int
	msg  string
}

func (e *exitErr) Error() string { return e.msg }

// errReconnect asks the loop to reconnect at once (hello saw needs_reconfirm).
var errReconnect = errors.New("reconnect")

type agentRunner struct {
	e       env
	st      *agentstate.Store
	a       *agentstate.Agent
	cl      *agentapi.Client
	cfgPath string
	once    bool
	ascii   bool
	src     pack.Source

	heartbeat time.Duration

	mu          sync.Mutex
	busyRunID   string
	ranJob      bool
	skipped     map[string]bool
	warnedCfg   bool
	confirmWait time.Duration   // current wait after a needs_confirmation decline
	waiting     map[string]bool // jobs already reported as waiting for a confirmation
}

func runAgent(ctx context.Context, args []string, e env) int {
	if len(args) > 0 && args[0] == "unpair" {
		return runUnpair(ctx, args[1:], e)
	}
	if len(args) > 0 && args[0] == "pair" { // alias of webhooktrap pair (AC-14)
		return runPair(ctx, args[1:], e)
	}
	fs := flag.NewFlagSet("agent", flag.ContinueOnError)
	fs.SetOutput(io.Discard)
	server := fs.String("server", "", "")
	cfgPath := fs.String("config", "webhooktrap.yml", "")
	once := fs.Bool("once", false, "")
	packs := fs.String("packs", "", "")
	ascii := fs.Bool("ascii", false, "")
	if err := fs.Parse(args); err != nil {
		if errors.Is(err, flag.ErrHelp) {
			fmt.Fprint(e.stdout, usageAgent)
			return 0
		}
		fmt.Fprintf(e.stderr, "webhooktrap agent: %s\n\n%s", clean(err.Error()), usageAgent)
		return 2
	}
	if fs.NArg() > 0 {
		fmt.Fprintf(e.stderr, "webhooktrap agent: unexpected argument %q\n", clean(fs.Arg(0)))
		return 2
	}
	r, code := loadPairing(e, *server, "agent")
	if r == nil {
		return code
	}
	if code, ok := r.checkOrigin(); !ok {
		return code
	}
	r.cfgPath, r.once, r.ascii = *cfgPath, *once, *ascii
	r.src = e.packs
	if *packs != "" {
		r.src = pack.FSSource{FS: os.DirFS(*packs)}
	}
	return r.loop(ctx)
}

// loadPairing finds the pairing for --server (or the only one) and builds
// the client with its key.
func loadPairing(e env, server, cmd string) (*agentRunner, int) {
	st, err := e.stateStore()
	if err != nil {
		fmt.Fprintf(e.stderr, "webhooktrap %s: no config directory: %s\n", cmd, clean(err.Error()))
		return nil, 1
	}
	if server == "" && e.getenv != nil {
		server = e.getenv("WEBHOOKTRAP_SERVER")
	}
	var origin string
	if server != "" {
		if origin, err = agentapi.NormalizeOrigin(server, true); err != nil {
			fmt.Fprintf(e.stderr, "webhooktrap %s: %s\n", cmd, clean(err.Error()))
			return nil, 2
		}
	} else {
		switch origins := st.Origins(); len(origins) {
		case 0:
			fmt.Fprintf(e.stderr, "webhooktrap %s: this machine isn't paired; create a code in your browser and run webhooktrap pair <CODE>\n", cmd)
			return nil, 1
		case 1:
			origin = origins[0]
		default:
			fmt.Fprintf(e.stderr, "webhooktrap %s: paired with several servers (%s); pass --server\n", cmd, strings.Join(origins, ", "))
			return nil, 2
		}
	}
	a, err := st.Load(origin)
	if err != nil {
		fmt.Fprintf(e.stderr, "webhooktrap %s: not paired with %s (%s); run webhooktrap pair <CODE> --server %s\n", cmd, origin, clean(err.Error()), origin)
		return nil, 1
	}
	if err := agentapi.RevalidateOrigin(a.Origin); err != nil || !agentapi.ValidAgentID(a.AgentID) {
		fmt.Fprintf(e.stderr, "webhooktrap %s: the stored pairing for %s is not valid; pair again\n", cmd, origin)
		return nil, 1
	}
	key, err := agentstate.LoadKey(st.KeyFile(origin))
	if err != nil {
		fmt.Fprintf(e.stderr, "webhooktrap %s: %s\n", cmd, clean(err.Error()))
		return nil, 1
	}
	cl := e.client(origin)
	cl.AgentID, cl.Key = a.AgentID, key
	return &agentRunner{e: e, st: st, a: a, cl: cl, heartbeat: heartbeatEvery, skipped: map[string]bool{}, waiting: map[string]bool{}}, 0
}

// insecureOriginWarning is printed on every start for a plain http:// origin
// (paired with --insecure-dev-origin).
const insecureOriginWarning = "Warning: %s is plain http:// (paired with --insecure-dev-origin). Traffic to it is not encrypted or authenticated; use it only for a development server on this machine.\n"

// errHostedHTTP refuses a plain http:// origin whose server says it is hosted.
const errHostedHTTP = "%s reports itself as a hosted server, which is only reached over https://; unpair (webhooktrap agent unpair --server %s) and pair again with an https:// origin"

// checkOrigin warns on every start for a plain http:// origin and refuses it
// when the server reported itself as hosted at pairing. It also prints the
// platform's key-storage warning, if any.
func (r *agentRunner) checkOrigin() (int, bool) {
	if w := agentstate.PlatformWarning(); w != "" {
		r.logf("%s", w)
	}
	if !strings.HasPrefix(r.a.Origin, "http://") {
		return 0, true
	}
	fmt.Fprintf(r.e.stderr, insecureOriginWarning, r.a.Origin)
	if r.a.ServerKind == agentapi.ServerKindHosted {
		r.logf(errHostedHTTP, r.a.Origin, r.a.Origin)
		return 1, false
	}
	return 0, true
}

func runUnpair(ctx context.Context, args []string, e env) int {
	fs := flag.NewFlagSet("unpair", flag.ContinueOnError)
	fs.SetOutput(io.Discard)
	server := fs.String("server", "", "")
	if err := fs.Parse(args); err != nil || fs.NArg() > 0 {
		fmt.Fprint(e.stderr, usageAgent)
		return 2
	}
	r, code := loadPairing(e, *server, "agent unpair")
	if r == nil {
		return code
	}
	if err := r.cl.Unpair(ctx); err != nil {
		if _, revoked := agentapi.Revoked(err); !revoked {
			fmt.Fprintln(e.stderr, "webhooktrap agent unpair: the server didn't answer ("+clean(err.Error())+"); revoke the agent in your browser too")
		}
	}
	if err := r.st.Delete(r.a.Origin); err != nil {
		fmt.Fprintln(e.stderr, "webhooktrap agent unpair: "+clean(err.Error()))
		return 1
	}
	fmt.Fprintf(e.stdout, "Unpaired from %s. The key for this server was deleted.\n", r.a.Origin)
	return 0
}

func (r *agentRunner) logf(format string, a ...any) {
	fmt.Fprintf(r.e.stderr, format+"\n", a...)
}

func (r *agentRunner) sleep(ctx context.Context, d time.Duration) error {
	if r.e.sleep != nil {
		return r.e.sleep(ctx, d)
	}
	return sleepCtx(ctx, d)
}

// loop keeps a session open and reconnects with exponential backoff.
func (r *agentRunner) loop(ctx context.Context) int {
	backoff := time.Second
	for {
		connected, err := r.session(ctx)
		if ctx.Err() != nil {
			r.logf("Stopped.")
			return 0
		}
		var ex *exitErr
		if errors.As(err, &ex) {
			if ex.msg != "" {
				r.logf("%s", ex.msg)
			}
			return ex.code
		}
		if reason, ok := agentapi.Revoked(err); ok {
			_ = r.st.Delete(r.a.Origin)
			r.logf("This agent was revoked by %s (%s). Its key was deleted; create a new code in your browser and pair again.", r.a.Origin, clean(display(reason, 40)))
			return 1
		}
		var ae *agentapi.APIError
		if errors.As(err, &ae) && (ae.Code == "agent_too_old" || ae.Status == http.StatusUpgradeRequired) {
			r.logf("This agent (%s) is too old for %s; it needs %s or later.", version.Current().Version, r.a.Origin, clean(display(ae.MinAgentVersion, 32)))
			return 1
		}
		if errors.Is(err, errReconnect) {
			continue
		}
		if connected {
			backoff = time.Second
		}
		wait := backoff + time.Duration(mrand.Int64N(int64(backoff)/5+1))
		r.logf("Connection to %s lost: %s. Reconnecting in %s.", r.a.Origin, clean(err.Error()), wait.Round(100*time.Millisecond))
		if r.sleep(ctx, wait) != nil {
			r.logf("Stopped.")
			return 0
		}
		backoff = min(backoff*2, maxBackoff)
	}
}

// session authenticates, says hello, keeps the heartbeat and long-polls for
// jobs until something fails. connected reports whether hello succeeded.
func (r *agentRunner) session(ctx context.Context) (bool, error) {
	sctx, cancel := context.WithCancel(ctx)
	defer cancel()
	if err := r.cl.Authenticate(sctx); err != nil {
		return false, err
	}
	if !r.a.Confirmed {
		if err := r.reconfirm(sctx, r.a.WorkspaceLabel, false); err != nil {
			return false, err
		}
	}
	hr, err := r.cl.Hello(sctx, r.helloBody())
	if agentapi.IsCode(err, "needs_reconfirm") {
		if err := r.reconfirm(sctx, r.a.WorkspaceLabel, true); err != nil {
			return false, err
		}
		hr, err = r.cl.Hello(sctx, r.helloBody())
	}
	if err != nil {
		return false, err
	}
	if hr.Server != nil && hr.Server.Kind == agentapi.ServerKindHosted && strings.HasPrefix(r.a.Origin, "http://") {
		return false, &exitErr{1, fmt.Sprintf(errHostedHTTP, r.a.Origin, r.a.Origin)}
	}
	if hr.NeedsReconfirm {
		if err := r.reconfirm(sctx, display(hr.Workspace.Label, 120), true); err != nil {
			return false, err
		}
		if hr, err = r.cl.Hello(sctx, r.helloBody()); err != nil {
			return false, err
		}
	}
	label := display(hr.Workspace.Label, 120)
	r.logf("Connected to %s as %s · %s. Waiting for runs (Ctrl-C to stop).", clean(r.a.Origin), clean(r.a.Name), label)
	if hr.Deprecation != nil && *hr.Deprecation != "" {
		r.logf("Notice from the server: %s", clean(display(*hr.Deprecation, 200)))
	}

	hbErr := make(chan error, 1)
	go r.heartbeatLoop(sctx, cancel, hbErr)
	for {
		job, err := r.cl.NextJob(sctx, longPollWait)
		if err != nil {
			select {
			case herr := <-hbErr:
				return true, herr
			default:
			}
			var ae *agentapi.APIError
			if errors.As(err, &ae) && ae.Status == http.StatusServiceUnavailable {
				wait := ae.RetryAfter
				if wait <= 0 {
					wait = skipPause
				}
				if r.sleep(sctx, min(wait, maxRetryAfter)) != nil {
					return true, ctx.Err()
				}
				continue
			}
			return true, err
		}
		if job == nil {
			continue
		}
		if err := r.handleJob(sctx, job); err != nil {
			return true, err
		}
		if r.once && r.ran() {
			return true, &exitErr{code: 0}
		}
	}
}

func (r *agentRunner) heartbeatLoop(ctx context.Context, cancel context.CancelFunc, out chan<- error) {
	t := time.NewTicker(r.heartbeat)
	defer t.Stop()
	for {
		select {
		case <-ctx.Done():
			return
		case <-t.C:
		}
		hr, err := r.cl.Hello(ctx, r.helloBody())
		var fatal error
		switch {
		case err == nil && hr.NeedsReconfirm, agentapi.IsCode(err, "needs_reconfirm"):
			fatal = errReconnect
		case err != nil:
			if _, revoked := agentapi.Revoked(err); revoked || agentapi.IsCode(err, "agent_too_old") {
				fatal = err
			}
		}
		if fatal != nil {
			out <- fatal
			if !r.busy() {
				cancel() // stop the long-poll; a running job finishes its own calls
			}
			return
		}
	}
}

func (r *agentRunner) busy() bool {
	r.mu.Lock()
	defer r.mu.Unlock()
	return r.busyRunID != ""
}

func (r *agentRunner) ran() bool {
	r.mu.Lock()
	defer r.mu.Unlock()
	return r.ranJob
}

// reconfirm asks the local [y/N] (pairing not yet confirmed, or after a
// claim) before calling /confirm. On N the agent unpairs.
func (r *agentRunner) reconfirm(ctx context.Context, label string, claimed bool) error {
	r.logf("Server     %s\nWorkspace  %s", r.a.Origin, label)
	q := joinQuestion
	if claimed {
		q = "This agent was moved to the workspace above. Continue? Only continue if you did this yourself just now. [y/N] "
	}
	if !r.e.tty {
		return &exitErr{1, "The server needs your confirmation for this agent; run webhooktrap agent in a terminal to answer."}
	}
	if !confirm(r.e, false, q) {
		_ = r.cl.Unpair(ctx)
		_ = r.st.Delete(r.a.Origin)
		return &exitErr{1, "Not confirmed. The agent was unpaired and its key deleted."}
	}
	if claimed {
		_ = r.st.ClearApprovals(r.a.Origin) // a claim clears approvals (§6.3 rule 6)
	}
	if err := r.cl.Confirm(ctx); err != nil {
		return err
	}
	r.a.Confirmed = true
	r.a.WorkspaceLabel = label
	return r.st.Save(*r.a)
}

// loadConfig reads webhooktrap.yml; nil when there is none.
func (r *agentRunner) loadConfig() (*config.Config, error) {
	raw, err := readRegular(r.cfgPath, 1<<20)
	if err != nil {
		if errors.Is(err, os.ErrNotExist) {
			return nil, nil
		}
		return nil, err
	}
	return config.Parse(raw)
}

// helloBody builds the mirrors: secret names that are set (never values),
// the declared target, the probe config hash, the busy run.
func (r *agentRunner) helloBody() agentapi.Hello {
	h := agentapi.Hello{Version: version.Current().Version}
	cfg, err := r.loadConfig()
	if err != nil || cfg == nil {
		r.mu.Lock()
		if !r.warnedCfg {
			r.warnedCfg = true
			if err != nil {
				r.logf("webhooktrap.yml: %s (no target declared until it's fixed)", clean(err.Error()))
			} else {
				r.logf("No %s here: no target is declared, so runs from the browser will be declined.", clean(r.cfgPath))
			}
		}
		r.mu.Unlock()
	}
	seen := map[string]bool{}
	add := func(provider, name string) {
		k := provider + "/" + name
		if seen[k] || !envNameRe.MatchString(name) || r.e.getenv == nil || r.e.getenv(name) == "" {
			return
		}
		seen[k] = true
		h.Secrets = append(h.Secrets, agentapi.SecretName{Provider: provider, EnvName: name})
	}
	if r.src != nil {
		ids, _ := r.src.Providers()
		for _, id := range ids {
			if p, err := r.src.Load(id, ""); err == nil {
				for _, n := range p.Signing.SecretEnv {
					add(id, n)
				}
			}
		}
	}
	if cfg != nil {
		if cfg.Secret != "" && cfg.Provider != "" {
			add(cfg.Provider, cfg.Secret)
		}
		if t, ok := targetParts(cfg.Target); ok {
			kind := "allowed"
			if localHost(t.host) {
				kind = "local"
			}
			h.Targets = append(h.Targets, agentapi.TargetMirror{Scheme: t.scheme, Host: t.host, Port: t.port, Kind: kind,
				Declared: true, Approved: r.st.Approved(r.a.Origin, r.a.AgentID, t.scheme, t.host, t.port)})
		}
		if len(cfg.Probe) > 0 || cfg.Reset != "" {
			hash := cfg.Hash
			h.ProbeConfigHash = &hash
		}
	}
	r.mu.Lock()
	if r.busyRunID != "" {
		id := r.busyRunID
		h.BusyRunID = &id
	}
	r.mu.Unlock()
	return h
}

type targetKey struct {
	scheme, host, path string
	port               int
}

func targetParts(raw string) (targetKey, bool) {
	u, err := url.Parse(raw)
	if err != nil || (u.Scheme != "http" && u.Scheme != "https") || u.Hostname() == "" {
		return targetKey{}, false
	}
	port := 80
	if u.Scheme == "https" {
		port = 443
	}
	if p := u.Port(); p != "" {
		n, err := strconv.Atoi(p)
		if err != nil || n < 1 || n > 65535 {
			return targetKey{}, false
		}
		port = n
	}
	path := u.EscapedPath()
	if path == "" {
		path = "/"
	}
	return targetKey{scheme: u.Scheme, host: strings.ToLower(u.Hostname()), path: path, port: port}, true
}

func localHost(h string) bool {
	if h == "localhost" || strings.HasSuffix(h, ".localhost") {
		return true
	}
	ip := net.ParseIP(h)
	return ip != nil && ip.IsLoopback()
}

// declined is a local refusal: the reason sent and the line printed.
type declined struct{ reason, why string }

func (r *agentRunner) handleJob(ctx context.Context, job *agentapi.Job) error {
	if err := agentapi.ValidateJob(job); err != nil {
		r.logf("Ignored a job this agent can't read: %s.", clean(err.Error()))
		return r.sleep(ctx, skipPause)
	}
	if job.Kind == "verify" {
		if !r.skipped[job.JobID] {
			r.skipped[job.JobID] = true
			r.logf("Signature checks aren't supported by this build yet; %d check(s) stay pending.", len(job.Captures))
		}
		return r.sleep(ctx, skipPause)
	}
	who := display(job.Requester.WorkspaceLabel, 80)
	prep, d := r.check(ctx, job)
	if d != nil {
		err := r.cl.Decline(ctx, job.JobID, d.reason)
		if fatal := r.callErr(err); fatal != nil {
			return fatal
		}
		if d.reason == agentapi.DeclineNeedsConfirmation {
			return r.waitForConfirmation(ctx, job, who, d)
		}
		r.logf("Declined run #%d from %s: %s (%s).", job.RunNumber, who, d.why, d.reason)
		return nil
	}
	if err := r.cl.Accept(ctx, job.JobID); err != nil {
		var ae *agentapi.APIError
		if errors.As(err, &ae) && (ae.Status == http.StatusConflict || ae.Status == http.StatusNotFound) {
			r.logf("Run #%d was already taken or has expired.", job.RunNumber)
			return nil
		}
		return err
	}
	r.mu.Lock()
	r.busyRunID, r.ranJob = job.RunID, true
	r.confirmWait = 0
	r.mu.Unlock()
	defer func() {
		r.mu.Lock()
		r.busyRunID = ""
		r.mu.Unlock()
	}()
	if err := r.runJob(ctx, job, prep); err != nil {
		if fatal := r.callErr(err); fatal != nil {
			return fatal
		}
		reason := abortReason(ctx, err)
		switch aerr := r.abort(ctx, job.JobID, reason); {
		case errors.Is(aerr, errNothingToAbort):
			r.logf("Run #%d stopped: %s. The server had already finished it.", job.RunNumber, clean(err.Error()))
		case aerr != nil:
			r.logf("Run #%d stopped: %s. The abort didn't reach the server (%s); it marks the run Aborted when the job expires.", job.RunNumber, clean(err.Error()), clean(aerr.Error()))
		default:
			r.logf("Run #%d stopped: %s. The run was aborted on the server (%s).", job.RunNumber, clean(err.Error()), reason)
		}
	}
	return nil
}

// waitForConfirmation follows a needs_confirmation decline: the job stays
// queued, so the agent prints one line per job and waits with backoff before
// the next long-poll instead of declining the same job in a loop (AC-8).
func (r *agentRunner) waitForConfirmation(ctx context.Context, job *agentapi.Job, who string, d *declined) error {
	r.mu.Lock()
	r.confirmWait = min(max(r.confirmWait*2, skipPause), maxConfirmWait)
	wait := r.confirmWait
	first := !r.waiting[job.JobID]
	if len(r.waiting) > 256 {
		clear(r.waiting)
	}
	r.waiting[job.JobID] = true
	r.mu.Unlock()
	if first {
		r.logf("Run #%d from %s is waiting: %s (%s). Answer in a terminal running webhooktrap agent; meanwhile this agent checks again every %s, then up to every %s.",
			job.RunNumber, who, d.why, d.reason, wait, maxConfirmWait)
	}
	return r.sleep(ctx, wait)
}

// abortErr is a local failure after accept with the abort reason to report.
type abortErr struct {
	reason string
	err    error
}

func (e *abortErr) Error() string { return e.err.Error() }
func (e *abortErr) Unwrap() error { return e.err }

func abortWith(reason string, err error) error { return &abortErr{reason, err} }

// abortReason maps a runJob failure to an abort reason.
func abortReason(ctx context.Context, err error) string {
	var ae *abortErr
	switch {
	case errors.As(err, &ae):
		return ae.reason
	case ctx.Err() != nil:
		return agentapi.AbortInterrupted
	}
	return agentapi.AbortInternalError
}

// errNothingToAbort is abort's 409: the job was already finished.
var errNothingToAbort = errors.New("nothing to abort")

// abort tells the server the accepted job can't finish (AC-9). It runs even
// when ctx was cancelled (Ctrl-C), with its own short timeout.
func (r *agentRunner) abort(ctx context.Context, jobID, reason string) error {
	actx, cancel := context.WithTimeout(context.WithoutCancel(ctx), abortTimeout)
	defer cancel()
	err := r.cl.Abort(actx, jobID, reason)
	var ae *agentapi.APIError
	if errors.As(err, &ae) && ae.Status == http.StatusConflict {
		return errNothingToAbort
	}
	return err
}

// callErr keeps the errors that end the session (revoked, needs_reconfirm,
// too old); others are reported where they happen.
func (r *agentRunner) callErr(err error) error {
	if err == nil {
		return nil
	}
	if _, revoked := agentapi.Revoked(err); revoked {
		return err
	}
	if agentapi.IsCode(err, "needs_reconfirm") {
		return errReconnect
	}
	if agentapi.IsCode(err, "agent_too_old") {
		return err
	}
	return nil
}

// prepared is what the local checks resolved for an accepted job.
type prepared struct {
	cfg       *config.Config
	g         *guard.Guard
	tgt       *guard.Target
	pk        *pack.Pack
	budget    plan.Budget
	scenarios []plan.Scenario
	seed      uint64
}

// check runs the local checks before accept (ADR-010 §6.3, contracts §5.4):
// the target is declared in webhooktrap.yml and approved here for (origin,
// workspace, scheme, host, port) with the NAME-4 acknowledgement, the probe
// commands are confirmed, and a first approval needs an interactive TTY.
func (r *agentRunner) check(ctx context.Context, job *agentapi.Job) (*prepared, *declined) {
	cfg, err := r.loadConfig()
	if err != nil {
		return nil, &declined{agentapi.DeclineTargetNotAllowed, "webhooktrap.yml can't be read: " + clean(err.Error())}
	}
	if cfg == nil || cfg.Target == "" {
		return nil, &declined{agentapi.DeclineTargetNotAllowed, "no target is declared in webhooktrap.yml"}
	}
	want, ok1 := targetParts(cfg.Target)
	got, ok2 := targetParts(job.TargetRef)
	if !ok1 || !ok2 || want.scheme != got.scheme || want.host != got.host || want.port != got.port {
		return nil, &declined{agentapi.DeclineTargetNotAllowed, "the target " + clean(display(job.TargetRef, 120)) + " isn't the one declared in webhooktrap.yml"}
	}
	for _, p := range cfg.Probe {
		if p.Kind == "sql" {
			return nil, &declined{agentapi.DeclineAgentTooOld, "probe " + p.As + " uses sql:, which this build can't run; use cmd: psql …"}
		}
	}
	g := guard.New(cfg.Targets)
	if r.e.resolver != nil {
		g.Resolver = r.e.resolver
	}
	tgt, err := g.Check(ctx, job.TargetRef)
	if err != nil {
		return nil, &declined{agentapi.DeclineTargetNotAllowed, clean(err.Error())}
	}
	pk, err := r.src.Load(job.Pack.ID, job.Pack.Version)
	if err != nil {
		return nil, &declined{agentapi.DeclineAgentTooOld, "this agent doesn't have pack " + clean(job.Pack.ID) + " " + clean(job.Pack.Version)}
	}
	var scenarios []plan.Scenario
	for _, s := range job.Scenarios {
		sc, ok := plan.ParseScenario(s)
		if !ok {
			return nil, &declined{agentapi.DeclineAgentTooOld, "this agent doesn't know the scenario " + clean(display(s, 32))}
		}
		scenarios = append(scenarios, sc)
	}
	seed, err := strconv.ParseUint(job.Seed, 10, 64)
	if err != nil {
		return nil, &declined{agentapi.DeclineAgentTooOld, "the seed is out of range"}
	}
	budget, err := resolveBudget(cfg, pk)
	if err != nil {
		return nil, &declined{agentapi.DeclineTargetNotAllowed, err.Error()}
	}

	// Approval of this target for runs started from the browser, with NAME-4.
	if !r.st.Approved(r.a.Origin, r.a.AgentID, tgt.Scheme, tgt.Host, tgt.Port) {
		if !r.e.tty {
			return nil, &declined{agentapi.DeclineNeedsConfirmation, "approve this target once in a terminal running webhooktrap agent"}
		}
		q := fmt.Sprintf("Run from the browser\n  server     %s\n  workspace  %s\n  requested  %s\n  target     %s\n"+
			"This run sends the same events several times. If your handler sends emails or charges cards, honour the Webhooktrap-Dry-Run header first.\n"+
			"Allow runs started from the browser to send to this target? [y/N] ",
			r.a.Origin, display(r.a.WorkspaceLabel, 120), display(job.Requester.WorkspaceLabel, 120), clean(display(job.TargetRef, 200)))
		if !confirm(r.e, false, q) {
			return nil, &declined{agentapi.DeclineTargetNotAllowed, "you didn't approve the target"}
		}
		if err := r.st.Approve(r.a.Origin, agentstate.Approval{AgentID: r.a.AgentID, Scheme: tgt.Scheme, Host: tgt.Host,
			Port: tgt.Port, Path: tgt.Path, ApprovedAt: rfc3339(r.e.now()), SideEffect: true}); err != nil {
			return nil, &declined{agentapi.DeclineNeedsConfirmation, "can't save the approval: " + clean(err.Error())}
		}
	}
	// Probe and reset commands, confirmed once per file hash (PRB-11, TM-52).
	if hasCommands(cfg) {
		st := loadState()
		if !slices.Contains(st.Confirmed, cfg.Hash) {
			if !r.e.tty {
				return nil, &declined{agentapi.DeclineNeedsConfirmation, "confirm the probe commands of webhooktrap.yml in a terminal"}
			}
			if !confirm(r.e, false, commandsPrompt(cfg)) {
				return nil, &declined{agentapi.DeclineTargetNotAllowed, "you didn't confirm the probe commands"}
			}
			st.Confirmed = append(st.Confirmed, cfg.Hash)
			_ = saveState(st)
		}
	}
	return &prepared{cfg: cfg, g: g, tgt: tgt, pk: pk, budget: budget, scenarios: scenarios, seed: seed}, nil
}

// runJob fetches and verifies the captures, runs the engine, streams the
// observations and posts the final document.
func (r *agentRunner) runJob(ctx context.Context, job *agentapi.Job, p *prepared) error {
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
	secret, secretNames := resolveSecret(p.cfg, p.pk, r.e.getenv)
	masker := scrub.NewMasker(secret)
	pl, err := plan.Build(plan.Input{Captures: caps, Scenarios: p.scenarios, Pack: p.pk, Seed: p.seed, HasSecret: !secret.IsZero(), Budget: p.budget})
	if err != nil {
		return fmt.Errorf("plan: %s", err.Error())
	}
	if job.DeliveryCount != nil && len(pl.Deliveries) != *job.DeliveryCount {
		r.logf("Note: this agent planned %d deliveries; the server expected %d.", len(pl.Deliveries), *job.DeliveryCount)
	}
	strip := append(allSecretNames(r.src), secretNames...)
	ps, err := setupProbes(p.cfg, caps, p.pk, execute.SafeEnv(r.e.environ, strip), p.g)
	if err != nil {
		var pe *plan.Error
		if errors.As(err, &pe) && pe.Code == "template_value" { // AC-1: a capture tried to feed a probe
			return abortWith(agentapi.AbortCaptureVerificationFailed, fmt.Errorf("probe value is not a plain id: %w", err))
		}
		return err
	}
	salt := r.e.salt
	if salt == nil {
		salt = make([]byte, 16) // D-3a: never written, never sent
		_, _ = rand.Read(salt)
	}
	entries := ps.entries
	if entries == nil {
		entries = []render.ProbeEntry{}
	}

	// The one line before the first delivery (ADR-010 §6.3 rule 4).
	fmt.Fprintf(r.e.stdout, "Run #%d from %s · %s · %d deliveries\n", job.RunNumber, display(job.Requester.WorkspaceLabel, 80), clean(display(job.TargetRef, 200)), len(pl.Deliveries))

	planJSON, _ := json.Marshal(pl)
	entriesJSON, _ := json.Marshal(entries)
	up := newUploader(r, job.RunID, len(pl.Deliveries), masker)
	count := len(pl.Deliveries)
	if err := up.post(ctx, agentapi.Observations{Seq: 1, Plan: planJSON, PlanHash: render.HostedPlanHash(pl.Hash), DeliveryCount: &count, ProbeEntries: entriesJSON,
		LabelBits: 24, ProbeDisplayPolicy: "strict"}); err != nil {
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
	resetter := ps.resetter
	if r.e.reset != nil && resetter != nil {
		resetter = r.e.reset
	}
	var tr execute.Transport = &execute.HTTPTransport{Target: p.tgt, Clock: clock}
	if r.e.wrap != nil {
		tr = r.e.wrap(tr)
	}
	start := clock.Wall()
	var probeNames []string
	for _, pe := range ps.entries {
		probeNames = append(probeNames, pe.Name)
	}
	obs, err := execute.Run(ctx, execute.Options{
		RunID: job.RunID, Plan: pl, Captures: caps, Pack: p.pk, Secret: secret, Target: p.tgt,
		Transport: tr, Clock: clock, Scheduler: sched, Probe: ps.prober, Reset: resetter, Settle: p.cfg.Settle,
		OnDelivery: func(pd plan.Delivery, o observe.Delivery) {
			up.push(render.Live(pd, o, probeNames, salt, pl.Budget.MS))
		},
		OnProbeError: func(err error) { r.logf("probe: %s", clean(masker.Mask(err.Error()))) },
	})
	upErr := up.close()
	if err != nil {
		return err
	}
	if upErr != nil {
		if fatal := r.callErr(upErr); fatal != nil || agentapi.IsCode(upErr, "run_final") {
			return upErr
		}
	}
	finished := clock.Wall()
	v := judge.Judge(pl, obs, p.pk, judge.Options{FailOn: job.FailOn, Framework: p.cfg.Framework, TargetPath: p.tgt.Path, Covers: ps.covers})
	in := render.Input{Plan: pl, Obs: obs, Verdict: v, Pack: p.pk, Meta: render.Meta{
		RunID: job.RunID, StartedAt: rfc3339(start), FinishedAt: rfc3339(finished),
		Target:   render.Target{Scheme: p.tgt.Scheme, Host: p.tgt.Host, Port: p.tgt.Port, Path: p.tgt.Path},
		Executor: "agent", Source: "web", AgentName: r.a.Name, AgentVersion: version.Current().Version,
		ProbeEntries: entries, SideEffects: true, Salt: salt,
	}}
	doc, err := render.HostedJSON(in)
	if err != nil {
		return err
	}
	if masker.Contains(string(doc)) { // JSON-2: never send a secret
		return errors.New("refusing to send a run document that contains a secret")
	}
	if err := r.postFinal(ctx, job.RunID, doc); err != nil {
		return err
	}
	prof := render.Unicode
	if r.ascii || (r.e.getenv != nil && r.e.getenv("TERM") == "dumb") {
		prof = render.ASCIIProfile
	}
	fmt.Fprint(r.e.stdout, masker.Mask(render.Text(in, prof)))
	fmt.Fprintln(r.e.stdout, render.ExitLine(v))
	return nil
}

// postFinal sends the document, retrying transport failures (first write
// wins, so a repeat of the same document is safe).
func (r *agentRunner) postFinal(ctx context.Context, runID string, doc []byte) error {
	wait := time.Second
	for attempt := 1; ; attempt++ {
		err := r.cl.PostFinal(ctx, runID, doc)
		var ae *agentapi.APIError
		switch {
		case err == nil:
			return nil
		case errors.As(err, &ae):
			if ae.Code == "run_final" {
				return errors.New("the server already holds a different final document for this run")
			}
			if ae.Status < 500 || attempt >= 5 {
				return err
			}
		case attempt >= 5 || ctx.Err() != nil:
			return err
		}
		if r.sleep(ctx, wait) != nil {
			return err
		}
		wait *= 2
	}
}

// uploader posts observation batches in order with an increasing seq,
// off the execute goroutine so delivery timing never waits on the network.
type uploader struct {
	r      *agentRunner
	runID  string
	masker *scrub.Masker
	ch     chan render.LiveDelivery
	done   chan struct{}
	seq    int

	mu  sync.Mutex
	err error
}

func newUploader(r *agentRunner, runID string, n int, m *scrub.Masker) *uploader {
	return &uploader{r: r, runID: runID, masker: m, ch: make(chan render.LiveDelivery, n+1), done: make(chan struct{})}
}

// errSecretPayload refuses an upload that contains the signing secret.
var errSecretPayload = errors.New("refusing to send data that contains a secret")

func (u *uploader) push(d render.LiveDelivery) {
	select {
	case u.ch <- d:
	default: // never blocks execute; the final document is authoritative
	}
}

func (u *uploader) post(ctx context.Context, o agentapi.Observations) error {
	u.seq++
	o.Seq = u.seq
	if o.Deliveries == nil {
		o.Deliveries = []json.RawMessage{}
	}
	o.Scenarios, o.FindingsProvisional = []json.RawMessage{}, []json.RawMessage{}
	if u.masker != nil { // JSON-2 on every upload, not only the final document (AC-15)
		if raw, err := json.Marshal(o); err != nil || u.masker.Contains(string(raw)) {
			return errSecretPayload
		}
	}
	wait := 500 * time.Millisecond
	for attempt := 1; ; attempt++ {
		err := u.r.cl.PostObservations(ctx, u.runID, o)
		var ae *agentapi.APIError
		if err == nil || errors.As(err, &ae) && ae.Status < 500 || attempt >= 3 || ctx.Err() != nil {
			return err
		}
		if u.r.sleep(ctx, wait) != nil {
			return err
		}
		wait *= 2
	}
}

func (u *uploader) run(ctx context.Context) {
	defer close(u.done)
	for d := range u.ch {
		u.mu.Lock()
		stop := u.err != nil
		u.mu.Unlock()
		if stop {
			continue // drain
		}
		raw, _ := json.Marshal(d)
		if err := u.post(ctx, agentapi.Observations{Deliveries: []json.RawMessage{raw}}); err != nil {
			var ae *agentapi.APIError
			if errors.As(err, &ae) {
				u.mu.Lock()
				u.err = err
				u.mu.Unlock()
			} else {
				u.r.logf("observation %d not sent: %s (the final document still carries it)", u.seq, clean(err.Error()))
			}
		}
	}
}

func (u *uploader) close() error {
	close(u.ch)
	<-u.done
	u.mu.Lock()
	defer u.mu.Unlock()
	return u.err
}
