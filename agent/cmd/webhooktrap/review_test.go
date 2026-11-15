package main

// Tests for the A09 agent connection review, Go side (AC-1, AC-2, AC-8,
// AC-9, AC-13, AC-14, AC-15 and the --insecure-dev-origin decision).

import (
	"context"
	"crypto/ed25519"
	"crypto/sha256"
	"encoding/base64"
	"encoding/hex"
	"encoding/json"
	"errors"
	"os"
	"strings"
	"testing"
	"time"

	"github.com/jiordiviera/webhooktrap/agent/execute"
	"github.com/jiordiviera/webhooktrap/agent/internal/agentapi"
	"github.com/jiordiviera/webhooktrap/agent/internal/agentstate"
	"github.com/jiordiviera/webhooktrap/agent/internal/config"
	"github.com/jiordiviera/webhooktrap/agent/internal/packdata"
	"github.com/jiordiviera/webhooktrap/agent/plan"
	"github.com/jiordiviera/webhooktrap/agent/scrub"
	"github.com/jiordiviera/webhooktrap/agent/signing"
)

const invoiceID = "in_1QKImRcZJ8HR9BZYostEV4ui"

// rewriteCaptures replaces old with new in every capture body of the job and
// keeps the job's body_sha256 in step, as a forged capture would arrive.
func (h *harness) rewriteCaptures(t *testing.T, job *agentapi.Job, old, new string) {
	t.Helper()
	defer h.f.lock()()
	hit := false
	for i, ref := range job.Selection {
		c := h.f.captures[ref.CaptureID]
		if !strings.Contains(string(c.body), old) {
			continue
		}
		hit = true
		c.body = []byte(strings.ReplaceAll(string(c.body), old, new))
		h.f.captures[ref.CaptureID] = c
		sum := sha256.Sum256(c.body)
		job.Selection[i].BodySHA256 = hex.EncodeToString(sum[:])
	}
	if !hit {
		t.Fatalf("no capture contains %q", old)
	}
}

// ---- AC-1: probe values never reach a shell string ----

func stripeCaps(t *testing.T, bodies ...string) []plan.Capture {
	t.Helper()
	var out []plan.Capture
	for i, b := range bodies {
		out = append(out, plan.Capture{Ref: "c", Provider: "stripe", CaptureOrder: i + 1, Body: []byte(b)})
	}
	return out
}

var probeCorpus = []string{
	`in_1'; touch pwned; '`, `in_1$(touch pwned)`, "in_1`touch pwned`", `in_1;touch pwned`,
	"in_1\ntouch pwned", `in_1%PATH%`, `in_1&calc`, `in_1" ; touch pwned ; echo "`, `in_1 -o x`,
}

func TestSetupProbesRefusesHostileValues(t *testing.T) {
	pk, err := packdata.Source().Load("stripe", "")
	if err != nil {
		t.Fatal(err)
	}
	gh, _ := packdata.Source().Load("github", "")
	cfg := &config.Config{Probe: []config.Probe{{As: "orders.status", Kind: "cmd",
		Text: `psql "$DATABASE_URL" -Atc "select status from orders where id = '{{invoice.id}}'"`}}}
	for _, v := range probeCorpus {
		raw, _ := json.Marshal(v)
		// Lookup 1: the pack object's id; lookup 2: a field named "invoice".
		object := `{"type":"invoice.paid","data":{"object":{"object":"invoice","id":` + string(raw) + `}}}`
		scalar := `{"type":"payment_intent.succeeded","data":{"object":{"object":"payment_intent","id":"pi_1","invoice":` + string(raw) + `}}}`
		for name, caps := range map[string][]plan.Capture{"object id": stripeCaps(t, object), "named field": stripeCaps(t, scalar)} {
			if _, err := setupProbes(cfg, caps, pk, nil, nil); err == nil {
				t.Errorf("%s: value %q accepted", name, v)
			}
		}
		// A pack without id_pattern still gets the plain-id rule.
		ghCaps := []plan.Capture{{Ref: "c", Provider: "github", CaptureOrder: 1, Body: []byte(`{"invoice":` + string(raw) + `}`)}}
		if _, err := setupProbes(cfg, ghCaps, gh, nil, nil); err == nil {
			t.Errorf("github: value %q accepted", v)
		}
	}
}

func TestSetupProbesPassesValuesInTheEnvironment(t *testing.T) {
	pk, _ := packdata.Source().Load("stripe", "")
	cfg := &config.Config{Probe: []config.Probe{
		{As: "orders.status", Kind: "cmd", Text: `psql "$DATABASE_URL" -Atc "select status from orders where id = '{{invoice.id}}'" {{ invoice.id }}`},
		{As: "ledger", Kind: "url", Text: "http://localhost:3000/_debug?invoice={{invoice.id}}"},
	}}
	caps := stripeCaps(t, `{"type":"invoice.paid","data":{"object":{"object":"invoice","id":"`+invoiceID+`"}}}`)
	ps, err := setupProbes(cfg, caps, pk, []string{"PATH=/bin", "WT_INVOICE_ID=stale"}, nil)
	if err != nil {
		t.Fatal(err)
	}
	entries := ps.prober.(*execute.CommandProbe).Entries
	cmd, u := entries[0], entries[1]
	if strings.Contains(cmd.Text, invoiceID) || strings.Contains(cmd.Text, "{{") {
		t.Fatalf("cmd text carries the value or a template: %s", cmd.Text)
	}
	if !strings.Contains(cmd.Text, `'${WT_INVOICE_ID}'`) || !strings.HasSuffix(cmd.Text, ` "${WT_INVOICE_ID}"`) {
		t.Fatalf("cmd text %s", cmd.Text)
	}
	if len(cmd.Env) != 1 || cmd.Env[0] != "WT_INVOICE_ID="+invoiceID {
		t.Fatalf("cmd env %v", cmd.Env)
	}
	if u.Text != "http://localhost:3000/_debug?invoice="+invoiceID || len(u.Env) != 0 {
		t.Fatalf("url entry %+v", u)
	}
}

// A forged capture in a remote run: the job is accepted (its captures are
// only fetched after the local approvals), the probe value is refused, the
// run is aborted with capture_verification_failed, nothing runs and nothing
// is uploaded.
func TestAgentHostileProbeValueAborts(t *testing.T) {
	h := newHarness(t)
	if code := h.pair(t); code != 0 {
		t.Fatal(h.errOut.String())
	}
	e := h.env(true, "y\ny\n")
	target := h.refTarget(t, &e)
	cfg := "target: " + target + "\nprovider: stripe\nsecret: env:STRIPE_WEBHOOK_SECRET\n" +
		"probe:\n  - as: orders.status\n    cmd: printf '%s' \"select status from orders where id = '{{invoice.id}}'\"\n"
	if err := os.WriteFile("webhooktrap.yml", []byte(cfg), 0o644); err != nil {
		t.Fatal(err)
	}
	job := h.queueRun(t, target)
	h.rewriteCaptures(t, job, invoiceID, "in_1$(touch pwned)`touch pwned2`")
	if code, _ := h.agent(t, e, "--once"); code != 0 {
		t.Fatalf("exit %d: %s", code, h.errOut.String())
	}
	for _, f := range []string{"pwned", "pwned2"} {
		if _, err := os.Stat(f); err == nil {
			t.Fatalf("%s was created: a capture value ran as a command", f)
		}
	}
	if !strings.Contains(h.errOut.String(), "not a plain id") {
		t.Fatalf("stderr: %s", h.errOut.String())
	}
	defer h.f.lock()()
	if h.f.aborted[job.JobID] != agentapi.AbortCaptureVerificationFailed {
		t.Fatalf("aborted %v", h.f.aborted)
	}
	if len(h.f.obs[job.RunID]) != 0 || h.f.finals[job.RunID] != nil {
		t.Fatal("observations or a final document were sent")
	}
}

// ---- AC-2 and AC-9: hostile event types and ids ----

func TestAgentHostileEventTypeIsCleanAndNormalised(t *testing.T) {
	h := newHarness(t)
	if code := h.pair(t); code != 0 {
		t.Fatal(h.errOut.String())
	}
	e := h.env(true, "y\ny\n")
	job := h.queueRun(t, h.refTarget(t, &e))
	hostile := `invoice.paid\u001b]0;owned\u0007\u001b[2J\u202e\u2066\u200b\u2028\u00ad\u0085`
	h.rewriteCaptures(t, job, `"type": "invoice.paid"`, `"type": "`+hostile+`"`)
	h.rewriteCaptures(t, job, `"id": "evt_1QK9BpyOmOFtoVkdOlN4pjPU"`, `"id": "evt_1\u001b[31m red"`)
	if code, _ := h.agent(t, e, "--once"); code != 0 {
		t.Fatalf("exit %d: %s", code, h.errOut.String())
	}
	for name, out := range map[string]string{"stdout": h.out.String(), "stderr": h.errOut.String()} {
		for _, r := range out {
			if r < 0x20 && r != '\n' || r >= 0x7f && r < 0xa0 || r == 0x202e || r == 0x2066 || r == 0x200b || r == 0x2028 || r == 0x00ad {
				t.Fatalf("%s carries U+%04X:\n%q", name, r, out)
			}
		}
	}
	if !strings.Contains(h.out.String(), "invoice.paid?]0;owned??[2J") {
		t.Fatalf("event type not shown cleaned:\n%s", h.out.String())
	}
	defer h.f.lock()()
	final := h.f.finals[job.RunID] // the fake validated it against final.request.schema.json
	if final == nil {
		t.Fatalf("no final document: %v", h.f.aborted)
	}
	var doc struct {
		Events []struct {
			EventType       string `json:"event_type"`
			ProviderEventID string `json:"provider_event_id"`
		} `json:"events"`
	}
	if err := json.Unmarshal(final, &doc); err != nil {
		t.Fatal(err)
	}
	seen := false
	for _, ev := range doc.Events {
		if ev.EventType == "" && ev.ProviderEventID == "" {
			seen = true
		}
		if strings.ContainsAny(ev.EventType+ev.ProviderEventID, "\x1b \u202e") {
			t.Fatalf("event %+v", ev)
		}
	}
	if !seen {
		t.Fatalf("hostile event not normalised: %+v", doc.Events)
	}
}

// ---- AC-8: no decline loop without a TTY ----

func TestAgentNeedsConfirmationBacksOff(t *testing.T) {
	h := newHarness(t)
	if code := h.pair(t); code != 0 {
		t.Fatal(h.errOut.String())
	}
	e := h.env(false, "")
	job := h.queueRun(t, h.refTarget(t, &e))
	ctx, cancel := context.WithTimeout(context.Background(), 20*time.Second)
	defer cancel()
	h.f.onDecline = func(string, string) {
		h.f.mu.Lock()
		n := h.f.declines
		h.f.mu.Unlock()
		if n >= 6 {
			cancel()
		}
	}
	runAgent(ctx, []string{"--server", h.f.srv.URL}, e)
	if n := strings.Count(h.errOut.String(), "is waiting"); n != 1 {
		t.Fatalf("%d waiting lines, want 1:\n%s", n, h.errOut.String())
	}
	h.mu.Lock()
	var waits []time.Duration
	for _, d := range h.sleeps {
		if d >= skipPause {
			waits = append(waits, d)
		}
	}
	h.mu.Unlock()
	want := []time.Duration{5 * time.Second, 10 * time.Second, 20 * time.Second, 40 * time.Second, time.Minute}
	if len(waits) < len(want) {
		t.Fatalf("waits %v, want at least %v", waits, want)
	}
	for i, w := range want {
		if waits[i] != w {
			t.Fatalf("waits %v, want %v first", waits, want)
		}
	}
	defer h.f.lock()()
	if h.f.declined[job.JobID] != agentapi.DeclineNeedsConfirmation || h.f.accepted[job.JobID] {
		t.Fatalf("declined %v accepted %v", h.f.declined, h.f.accepted)
	}
}

// ---- AC-9: abort on local failures after accept ----

func TestAgentAbortsOnTamperedCapture(t *testing.T) {
	h := newHarness(t)
	if code := h.pair(t); code != 0 {
		t.Fatal(h.errOut.String())
	}
	e := h.env(true, "y\ny\n")
	job := h.queueRun(t, h.refTarget(t, &e))
	h.f.tamper[job.Selection[0].CaptureID] = true
	if code, _ := h.agent(t, e, "--once"); code != 0 {
		t.Fatalf("exit %d: %s", code, h.errOut.String())
	}
	defer h.f.lock()()
	if h.f.aborted[job.JobID] != agentapi.AbortCaptureVerificationFailed {
		t.Fatalf("aborted %v\n%s", h.f.aborted, h.errOut.String())
	}
}

func TestAgentAbortsWhenFinalIsRefused(t *testing.T) {
	h := newHarness(t)
	if code := h.pair(t); code != 0 {
		t.Fatal(h.errOut.String())
	}
	e := h.env(true, "y\ny\n")
	job := h.queueRun(t, h.refTarget(t, &e))
	h.f.finalFail = 422
	if code, _ := h.agent(t, e, "--once"); code != 0 {
		t.Fatalf("exit %d: %s", code, h.errOut.String())
	}
	defer h.f.lock()()
	if h.f.aborted[job.JobID] != agentapi.AbortInternalError {
		t.Fatalf("aborted %v\n%s", h.f.aborted, h.errOut.String())
	}
}

func TestAbortReason(t *testing.T) {
	live := context.Background()
	gone, cancel := context.WithCancel(live)
	cancel()
	for _, c := range []struct {
		ctx  context.Context
		err  error
		want string
	}{
		{live, abortWith(agentapi.AbortCaptureVerificationFailed, errors.New("x")), agentapi.AbortCaptureVerificationFailed},
		{live, errors.New("engine"), agentapi.AbortInternalError},
		{gone, context.Canceled, agentapi.AbortInterrupted},
	} {
		if got := abortReason(c.ctx, c.err); got != c.want {
			t.Errorf("abortReason(%v) = %s, want %s", c.err, got, c.want)
		}
	}
}

// ---- AC-15: the secret check covers every upload ----

func TestUploaderRefusesSecret(t *testing.T) {
	m := scrub.NewMasker(signing.NewSecret("STRIPE_WEBHOOK_SECRET", []byte(testSecret)))
	u := newUploader(&agentRunner{}, "run_x", 1, m) // no client: nothing may be sent
	for _, o := range []agentapi.Observations{
		{Plan: json.RawMessage(`{"note":"` + testSecret + `"}`)},
		{Deliveries: []json.RawMessage{json.RawMessage(`{"x":"` + testSecret + `"}`)}},
	} {
		if err := u.post(context.Background(), o); !errors.Is(err, errSecretPayload) {
			t.Fatalf("post with a secret: %v", err)
		}
	}
}

// ---- --insecure-dev-origin ----

func TestPairRefusesHTTPForHostedServer(t *testing.T) {
	h := newHarness(t)
	h.f.kind = "hosted"
	if code := h.pair(t); code != 1 || !strings.Contains(h.errOut.String(), "reports itself as hosted") {
		t.Fatalf("exit %d: %s", code, h.errOut.String())
	}
	if _, err := os.Stat(h.store.KeyFile(h.origin())); err == nil {
		t.Fatal("key kept")
	}
	defer h.f.lock()()
	if h.f.confirms != 0 || h.f.deletes != 1 {
		t.Fatalf("confirms %d deletes %d", h.f.confirms, h.f.deletes)
	}
}

func TestInsecureOriginWarnsOnEveryStart(t *testing.T) {
	h := newHarness(t)
	if code := h.pair(t); code != 0 {
		t.Fatal(h.errOut.String())
	}
	if !strings.Contains(h.errOut.String(), "Warning: "+h.origin()+" is plain http://") {
		t.Fatalf("pair didn't warn: %s", h.errOut.String())
	}
	if a, _ := h.store.Load(h.origin()); a.ServerKind != "selfhost" {
		t.Fatalf("server kind %q", a.ServerKind)
	}
	for range 2 {
		e := h.env(true, "")
		ctx, cancel := context.WithCancel(context.Background())
		e.sleep = func(context.Context, time.Duration) error { cancel(); return context.Canceled }
		h.f.retry503 = 1
		runAgent(ctx, []string{"--server", h.f.srv.URL}, e)
		if !strings.Contains(h.errOut.String(), "Warning: "+h.origin()+" is plain http://") {
			t.Fatalf("agent didn't warn: %s", h.errOut.String())
		}
	}
}

func TestAgentRefusesHTTPForHostedServer(t *testing.T) {
	t.Run("stored at pairing", func(t *testing.T) {
		h := newHarness(t)
		if code := h.pair(t); code != 0 {
			t.Fatal(h.errOut.String())
		}
		a, _ := h.store.Load(h.origin())
		a.ServerKind = "hosted"
		_ = h.store.Save(*a)
		h.f.mu.Lock()
		before := len(h.f.calls)
		h.f.mu.Unlock()
		code, _ := h.agent(t, h.env(true, ""))
		if code != 1 || !strings.Contains(h.errOut.String(), "reports itself as a hosted server") {
			t.Fatalf("exit %d: %s", code, h.errOut.String())
		}
		defer h.f.lock()()
		if len(h.f.calls) != before {
			t.Fatal("the agent contacted a hosted server over http://")
		}
	})
	t.Run("reported in hello", func(t *testing.T) {
		h := newHarness(t)
		if code := h.pair(t); code != 0 {
			t.Fatal(h.errOut.String())
		}
		h.f.helloKind = "hosted"
		code, _ := h.agent(t, h.env(true, ""))
		if code != 1 || !strings.Contains(h.errOut.String(), "reports itself as a hosted server") {
			t.Fatalf("exit %d: %s", code, h.errOut.String())
		}
	})
}

// ---- AC-14: webhooktrap agent pair is an alias ----

func TestAgentPairAlias(t *testing.T) {
	h := newHarness(t)
	h.f.addCode(pairCode)
	code := runAgent(context.Background(), []string{"pair", "7kq2m-xr9td", "--server", h.f.srv.URL, "--insecure-dev-origin"}, h.env(true, "y\n"))
	if code != 0 {
		t.Fatalf("exit %d: %s", code, h.errOut.String())
	}
	if a, err := h.store.Load(h.origin()); err != nil || !a.Confirmed {
		t.Fatalf("%+v %v", a, err)
	}
}

// AC-13: the pairing lives in a directory named after a hash of the origin.
func TestPairingDirIsOriginHash(t *testing.T) {
	h := newHarness(t)
	if code := h.pair(t); code != 0 {
		t.Fatal(h.errOut.String())
	}
	sum := sha256.Sum256([]byte(h.origin()))
	if !strings.HasSuffix(agentstate.Slug(h.origin()), hex.EncodeToString(sum[:16])) ||
		!strings.Contains(h.store.KeyFile(h.origin()), hex.EncodeToString(sum[:16])) {
		t.Fatalf("key file %s", h.store.KeyFile(h.origin()))
	}
}

// ---- AC-3 follow-up: the name the server kept, and replacements ----

func TestPairStoresServerName(t *testing.T) {
	h := newHarness(t)
	if code := h.pair(t); code != 0 {
		t.Fatal(h.errOut.String())
	}
	// A second machine with the same name, in its own config directory.
	h.store = &agentstate.Store{Dir: t.TempDir()}
	if code := h.pair(t); code != 0 {
		t.Fatal(h.errOut.String())
	}
	a, err := h.store.Load(h.origin())
	if err != nil || a.Name != "maya-mbp-2" {
		t.Fatalf("stored %+v %v", a, err)
	}
	if !strings.Contains(h.errOut.String(), "Agent      maya-mbp-2") || !strings.Contains(h.errOut.String(), "maya-mbp is taken") ||
		!strings.Contains(h.out.String(), "as maya-mbp-2.") {
		t.Fatalf("stderr %s\nstdout %s", h.errOut.String(), h.out.String())
	}
	e := h.env(true, "")
	ctx, cancel := context.WithCancel(context.Background())
	e.sleep = func(context.Context, time.Duration) error { cancel(); return context.Canceled }
	h.f.retry503 = 1
	runAgent(ctx, []string{"--server", h.f.srv.URL}, e)
	if !strings.Contains(h.errOut.String(), "as maya-mbp-2 ·") {
		t.Fatalf("agent shows another name: %s", h.errOut.String())
	}
}

func TestPairShowsReplacedAgent(t *testing.T) {
	h := newHarness(t)
	oldFP := "SHA256:" + strings.Repeat("A", 42) + "E"
	h.f.replaces = map[string]string{"agent_id": "agt_0" + strings.Repeat("A", 25), "name": "laptop", "key_fingerprint": oldFP}
	if code := h.pair(t); code != 0 {
		t.Fatal(h.errOut.String())
	}
	key, err := agentstate.LoadKey(h.store.KeyFile(h.origin()))
	if err != nil {
		t.Fatal(err)
	}
	pub := key.Public().(ed25519.PublicKey)
	sum := sha256.Sum256(pub)
	ownFP := "SHA256:" + base64.RawStdEncoding.EncodeToString(sum[:])
	e := h.errOut.String()
	q := strings.Index(e, "[y/N]")
	for _, want := range []string{"Replaces   laptop (" + oldFP + ")", "Key        " + ownFP, "Agent      laptop"} {
		if i := strings.Index(e, want); i < 0 || i > q {
			t.Fatalf("%q missing before the question:\n%s", want, e)
		}
	}
	if a, _ := h.store.Load(h.origin()); a.Name != "laptop" {
		t.Fatalf("stored name %q", a.Name)
	}

	// A replacement always asks, even with --yes.
	h2 := newHarness(t)
	h2.f.replaces = h.f.replaces
	h2.f.addCode(pairCode)
	if code := runPair(context.Background(), []string{pairCode, "--yes", "--server", h2.f.srv.URL, "--insecure-dev-origin"}, h2.env(false, "")); code != 1 {
		t.Fatalf("replacement joined without a TTY: exit %d", code)
	}
}

func TestPairRefusesInvalidServerName(t *testing.T) {
	h := newHarness(t)
	h.f.replaces = map[string]string{"agent_id": "agt_x", "name": "bad name\x1b[2J", "key_fingerprint": "x"}
	if code := h.pair(t); code != 1 || !strings.Contains(h.errOut.String(), "isn't a valid agent name") || strings.Contains(h.errOut.String(), "\x1b") {
		t.Fatalf("exit %d: %q", code, h.errOut.String())
	}
	if _, err := h.store.Load(h.origin()); err == nil {
		t.Fatal("pairing saved")
	}
}
