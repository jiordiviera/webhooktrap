package main

import (
	"bufio"
	"bytes"
	"context"
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"os"
	"path/filepath"
	"strings"
	"sync"
	"testing"
	"time"

	"github.com/jiordiviera/webhooktrap/agent/execute"
	"github.com/jiordiviera/webhooktrap/agent/internal/agentapi"
	"github.com/jiordiviera/webhooktrap/agent/internal/agentstate"
	"github.com/jiordiviera/webhooktrap/agent/internal/fixture"
	"github.com/jiordiviera/webhooktrap/agent/internal/packdata"
	"github.com/jiordiviera/webhooktrap/agent/internal/refhandler"
	"github.com/jiordiviera/webhooktrap/agent/internal/schematest"
)

const pairCode = "7KQ2MXR9TD"

// syncBuf is a goroutine-safe output buffer.
type syncBuf struct {
	mu sync.Mutex
	b  bytes.Buffer
}

func (s *syncBuf) Write(p []byte) (int, error) {
	s.mu.Lock()
	defer s.mu.Unlock()
	return s.b.Write(p)
}

func (s *syncBuf) String() string {
	s.mu.Lock()
	defer s.mu.Unlock()
	return s.b.String()
}

type harness struct {
	t      *testing.T
	f      *fakeServer
	store  *agentstate.Store
	out    *syncBuf
	errOut *syncBuf
	sleeps []time.Duration
	mu     sync.Mutex
}

func newHarness(t *testing.T) *harness {
	t.Chdir(t.TempDir())
	return &harness{t: t, f: newFakeServer(t), store: &agentstate.Store{Dir: filepath.Join(t.TempDir(), "cfg")}}
}

// env builds an environment answering the given lines on a TTY (or none).
func (h *harness) env(tty bool, answers string) env {
	h.out, h.errOut = &syncBuf{}, &syncBuf{}
	return env{
		stdout: h.out, stderr: h.errOut, stdin: strings.NewReader(answers), in: bufio.NewReader(strings.NewReader(answers)), tty: tty,
		getenv: func(k string) string {
			if k == "STRIPE_WEBHOOK_SECRET" {
				return testSecret
			}
			return ""
		},
		packs: packdata.Source(), store: h.store, http: h.f.srv.Client(), now: time.Now,
		hostname: func() (string, error) { return "maya-mbp.local", nil },
		sleep: func(ctx context.Context, d time.Duration) error {
			h.mu.Lock()
			h.sleeps = append(h.sleeps, d)
			h.mu.Unlock()
			if ctx.Err() != nil {
				return ctx.Err()
			}
			return nil
		},
	}
}

func (h *harness) pair(t *testing.T, args ...string) int {
	t.Helper()
	h.f.addCode(pairCode)
	return runPair(context.Background(), append([]string{"7kq2m-xr9td", "--server", h.f.srv.URL, "--insecure-dev-origin"}, args...), h.env(true, "y\n"))
}

func (h *harness) origin() string {
	o, _ := agentapi.NormalizeOrigin(h.f.srv.URL, true)
	return o
}

// ---- pairing ----

func TestPairHappyPath(t *testing.T) {
	h := newHarness(t)
	if code := h.pair(t); code != 0 {
		t.Fatalf("exit %d: %s", code, h.errOut.String())
	}
	a, err := h.store.Load(h.origin())
	if err != nil || !a.Confirmed || a.Name != "maya-mbp" {
		t.Fatalf("state %+v %v", a, err)
	}
	st, _ := os.Stat(h.store.KeyFile(h.origin()))
	dir, _ := os.Stat(filepath.Dir(h.store.KeyFile(h.origin())))
	if st.Mode().Perm() != 0o600 || dir.Mode().Perm() != 0o700 {
		t.Fatalf("key %v dir %v", st.Mode(), dir.Mode())
	}
	e := h.errOut.String()
	if i, j := strings.Index(e, h.origin()), strings.Index(e, "Brindle Coffee"); i < 0 || j < i || !strings.Contains(e, "[y/N]") {
		t.Fatalf("origin must print before the label and the question:\n%s", e)
	}
	defer h.f.lock()()
	if h.f.confirms != 1 || len(h.f.hellos) != 0 {
		t.Fatalf("confirms %d hellos %d (no hello before the local y)", h.f.confirms, len(h.f.hellos))
	}
}

func TestPairAnswerNoUnpairs(t *testing.T) {
	h := newHarness(t)
	h.f.addCode(pairCode)
	code := runPair(context.Background(), []string{pairCode, "--server", h.f.srv.URL, "--insecure-dev-origin"}, h.env(true, "n\n"))
	if code != 1 {
		t.Fatalf("exit %d", code)
	}
	if _, err := h.store.Load(h.origin()); err == nil {
		t.Fatal("state kept after N")
	}
	defer h.f.lock()()
	if h.f.deletes != 1 || h.f.confirms != 0 {
		t.Fatalf("deletes %d confirms %d", h.f.deletes, h.f.confirms)
	}
}

func TestPairGuestNeedsTTYEvenWithYes(t *testing.T) {
	h := newHarness(t)
	h.f.guest = true
	h.f.addCode(pairCode)
	if code := runPair(context.Background(), []string{pairCode, "--yes", "--server", h.f.srv.URL, "--insecure-dev-origin"}, h.env(false, "")); code != 1 {
		t.Fatalf("exit %d", code)
	}
	defer h.f.lock()()
	if h.f.deletes != 1 || h.f.confirms != 0 {
		t.Fatalf("guest joined without a TTY: deletes %d confirms %d", h.f.deletes, h.f.confirms)
	}
}

func TestPairAccountYesWithoutTTY(t *testing.T) {
	h := newHarness(t)
	h.f.addCode(pairCode)
	if code := runPair(context.Background(), []string{pairCode, "--yes", "--server", h.f.srv.URL, "--insecure-dev-origin"}, h.env(false, "")); code != 0 {
		t.Fatalf("exit %d: %s", code, h.errOut.String())
	}
}

func TestPairRefusals(t *testing.T) {
	h := newHarness(t)
	for _, c := range []struct {
		name string
		args []string
		want string
	}{
		{"malformed code", []string{"7KQ2", "--server", h.f.srv.URL, "--insecure-dev-origin"}, "10 characters"},
		{"letter U", []string{"7KQ2MXR9TU", "--server", h.f.srv.URL, "--insecure-dev-origin"}, "10 characters"},
		{"unknown code", []string{"7KQ2MXR9TE", "--server", h.f.srv.URL, "--insecure-dev-origin"}, "unknown, used or expired"},
		{"plain http without flag", []string{pairCode, "--server", h.f.srv.URL}, "https://"},
		{"plain http hosted", []string{pairCode, "--server", "http://webhooktrap.dev", "--insecure-dev-origin"}, "https://"},
		{"path in origin", []string{pairCode, "--server", "https://webhooktrap.dev/x"}, "origin only"},
	} {
		t.Run(c.name, func(t *testing.T) {
			code := runPair(context.Background(), c.args, h.env(true, "y\n"))
			if code == 0 || !strings.Contains(h.errOut.String(), c.want) {
				t.Fatalf("exit %d, stderr %q, want %q", code, h.errOut.String(), c.want)
			}
		})
	}
}

func TestPairWrongOriginEcho(t *testing.T) {
	h := newHarness(t)
	h.f.echoOrigin = "https://evil.example"
	if code := h.pair(t); code != 1 || !strings.Contains(h.errOut.String(), "refusing") {
		t.Fatalf("exit %d: %s", code, h.errOut.String())
	}
	if _, err := h.store.Load(h.origin()); err == nil {
		t.Fatal("pairing saved for a server that echoed another origin")
	}
}

func TestPairFollowsNoRedirect(t *testing.T) {
	h := newHarness(t)
	var hits int
	other := httptest.NewServer(http.HandlerFunc(func(http.ResponseWriter, *http.Request) { hits++ }))
	defer other.Close()
	h.f.redirect["/agent/v1/pair"] = other.URL + "/agent/v1/pair"
	if code := h.pair(t); code != 1 || !strings.Contains(h.errOut.String(), "redirect") {
		t.Fatalf("exit %d: %s", code, h.errOut.String())
	}
	if hits != 0 {
		t.Fatalf("redirect target received %d requests", hits)
	}
}

// ---- connected mode ----

// queueRun2 registers the RD Run #3 captures and a run job for them, served
// by the buggy reference handler on a virtual clock (as the goldens do).
func (h *harness) queueRun(t *testing.T, target string) *agentapi.Job {
	t.Helper()
	src, _ := filepath.Abs(filepath.Join(h.repoAgent(), "testdata", "fixtures", "stripe"))
	pk, err := packdata.Source().Load("stripe", "")
	if err != nil {
		t.Fatal(err)
	}
	n := 16
	job := &agentapi.Job{JobID: "job_" + randID(), Kind: "run", RunID: "run_" + randID(), RunNumber: 3, Remote: true,
		Requester: agentapi.Requester{WorkspaceLabel: "Brindle Coffee"}, ProjectKey: "Br7nQ2xk9A",
		Scenarios: strings.Split(strings.ReplaceAll(run3Scenarios, " ", ""), ","), TargetRef: target,
		Pack: agentapi.PackRef{ID: "stripe", Version: pk.Version}, FailOn: "high", Seed: goldenSeed, DeliveryCount: &n,
		ExpiresAt: time.Now().Add(10 * time.Minute).UTC().Format("2006-01-02T15:04:05.000Z")}
	defer h.f.lock()()
	for _, fx := range fixtures {
		c, err := fixture.Load(filepath.Join(src, filepath.Base(fx)))
		if err != nil {
			t.Fatal(err)
		}
		id := "cap_" + randID()
		sum := sha256.Sum256(c.Body)
		h.f.captures[id] = fakeCapture{headers: c.Headers, body: c.Body}
		job.Selection = append(job.Selection, agentapi.SelectionRef{CaptureID: id, Seq: c.CaptureOrder, BodySHA256: hex.EncodeToString(sum[:])})
	}
	h.f.jobs = append(h.f.jobs, job)
	return job
}

// Resolved before any test changes directory.
var (
	agentRoot, _ = filepath.Abs(filepath.Join("..", ".."))
	schemaDir    = schematest.Dir("agent/v1")
)

func (h *harness) repoAgent() string { return agentRoot }

// refTarget starts the buggy reference handler and writes webhooktrap.yml.
func (h *harness) refTarget(t *testing.T, e *env) string {
	t.Helper()
	clock := execute.NewVirtualClock(goldenStart)
	rh := refhandler.New(false, []byte(testSecret), clock.Wall)
	rh.Simulate = true
	rh.Base = 150 * time.Millisecond
	rh.Latency = map[string]time.Duration{}
	for k, v := range rdLatency {
		rh.Latency[k] = v * time.Millisecond
	}
	srv := httptest.NewServer(rh)
	t.Cleanup(srv.Close)
	cfg := "target: " + srv.URL + "/webhooks/stripe\nprovider: stripe\nsecret: env:STRIPE_WEBHOOK_SECRET\n" +
		"probe:\n  - as: orders.count\n    url: " + srv.URL + "/_debug/orders?invoice={{invoice.id}}\n" +
		"reset: npm run db:reset:webhooks\n"
	if err := os.WriteFile("webhooktrap.yml", []byte(cfg), 0o644); err != nil {
		t.Fatal(err)
	}
	e.clock, e.scheduler = clock, clock
	e.wrap = func(tr execute.Transport) execute.Transport { return simTransport{tr, clock} }
	e.reset = resetFunc(rh.Reset)
	e.salt = []byte("golden-salt-0001")
	return srv.URL + "/webhooks/stripe"
}

func (h *harness) agent(t *testing.T, e env, args ...string) (int, context.CancelFunc) {
	t.Helper()
	ctx, cancel := context.WithTimeout(context.Background(), 30*time.Second)
	defer cancel()
	h.f.onDecline = func(string, string) { cancel() }
	return runAgent(ctx, append([]string{"--server", h.f.srv.URL}, args...), e), cancel
}

func TestAgentHappyPath(t *testing.T) {
	h := newHarness(t)
	if code := h.pair(t); code != 0 {
		t.Fatal(h.errOut.String())
	}
	e := h.env(true, "y\ny\n") // approve the target, then the reset command
	target := h.refTarget(t, &e)
	job := h.queueRun(t, target)
	code, _ := h.agent(t, e, "--once")
	if code != 0 {
		t.Fatalf("exit %d\nstderr: %s\nstdout: %s", code, h.errOut.String(), h.out.String())
	}
	out := h.out.String()
	if !strings.HasPrefix(out, "Run #3 from Brindle Coffee · "+target+" · 16 deliveries\n") {
		t.Fatalf("missing the one-line notice first:\n%s", out)
	}

	defer h.f.lock()()
	// hello: names only, never values; closed shape checked by the fake.
	if len(h.f.hellos) == 0 {
		t.Fatal("no hello")
	}
	var hello agentapi.Hello
	_ = json.Unmarshal(h.f.hellos[0], &hello)
	if bytes.Contains(h.f.hellos[0], []byte(testSecret)) || len(hello.Secrets) == 0 || hello.Secrets[0].EnvName != "STRIPE_WEBHOOK_SECRET" {
		t.Fatalf("hello %s", h.f.hellos[0])
	}
	if len(hello.Targets) != 1 || hello.Targets[0].Kind != "local" || !hello.Targets[0].Declared || hello.ProbeConfigHash == nil {
		t.Fatalf("hello targets %+v", hello)
	}
	if !h.f.accepted[job.JobID] || len(h.f.fetched) != 3 {
		t.Fatalf("accepted %v fetched %v", h.f.accepted, h.f.fetched)
	}
	obs := h.f.obs[job.RunID]
	if len(obs) != 17 || obs[1].Plan == nil || obs[1].DeliveryCount == nil || *obs[1].DeliveryCount != 16 {
		t.Fatalf("observations: %d batches, seq 1 %+v", len(obs), obs[1].DeliveryCount)
	}
	final := h.f.finals[job.RunID]
	for _, bad := range []string{testSecret, `"title"`, `"what"`, `"why"`, `"reading_text"`, `"observations"`, `"likely_cause_text"`} {
		if bytes.Contains(final, []byte(bad)) {
			t.Fatalf("final document contains %s", bad)
		}
	}
	var doc struct {
		Profile string `json:"profile"`
		Run     struct {
			ID, Status string
			Provenance map[string]string
		} `json:"run"`
		Findings []struct{ Type, Severity string } `json:"findings"`
	}
	if err := json.Unmarshal(final, &doc); err != nil {
		t.Fatal(err)
	}
	var types []string
	for _, f := range doc.Findings {
		types = append(types, f.Type)
	}
	if doc.Profile != "hosted" || doc.Run.ID != job.RunID || doc.Run.Status != "findings" || doc.Run.Provenance["executor"] != "agent" ||
		strings.Join(types, ",") != "accepts_tampered,duplicate_changed_state,over_budget,probe_gap" {
		t.Fatalf("final %s %s %v %v", doc.Profile, doc.Run.Status, doc.Run.Provenance, types)
	}
	if !h.store.Approved(h.origin(), h.f.agentIDs()[0], "http", "127.0.0.1", portOf(target)) {
		t.Fatal("approval not recorded")
	}
}

func (f *fakeServer) agentIDs() []string {
	var out []string
	for id := range f.agents {
		out = append(out, id)
	}
	return out
}

func portOf(u string) int {
	t, _ := targetParts(u)
	return t.port
}

func TestAgentDeclines(t *testing.T) {
	for _, c := range []struct {
		name    string
		tty     bool
		answers string
		setup   func(h *harness, e *env, target string) string // returns the job target
		want    string
	}{
		{"no config", true, "", func(h *harness, e *env, target string) string { _ = os.Remove("webhooktrap.yml"); return target }, agentapi.DeclineTargetNotAllowed},
		{"undeclared port", true, "", func(h *harness, e *env, target string) string {
			return strings.Replace(target, "127.0.0.1:", "127.0.0.1:1", 1)
		}, agentapi.DeclineTargetNotAllowed},
		{"first approval without a TTY", false, "", nil, agentapi.DeclineNeedsConfirmation},
		{"approval refused", true, "n\n", nil, agentapi.DeclineTargetNotAllowed},
		{"probe commands unconfirmed without a TTY", false, "", func(h *harness, e *env, target string) string {
			tg, _ := targetParts(target)
			_ = h.store.Approve(h.origin(), agentstate.Approval{AgentID: h.f.agentIDs()[0], Scheme: "http", Host: tg.host, Port: tg.port, SideEffect: true})
			return target
		}, agentapi.DeclineNeedsConfirmation},
		{"unknown pack version", true, "y\ny\n", func(h *harness, e *env, target string) string { return "pack:9.9.9" }, agentapi.DeclineAgentTooOld},
	} {
		t.Run(c.name, func(t *testing.T) {
			h := newHarness(t)
			if code := h.pair(t); code != 0 {
				t.Fatal(h.errOut.String())
			}
			e := h.env(c.tty, c.answers)
			target := h.refTarget(t, &e)
			jt := target
			if c.setup != nil {
				jt = c.setup(h, &e, target)
			}
			var job *agentapi.Job
			if v, ok := strings.CutPrefix(jt, "pack:"); ok {
				job = h.queueRun(t, target)
				job.Pack.Version = v
			} else {
				job = h.queueRun(t, jt)
			}
			h.agent(t, e)
			defer h.f.lock()()
			if got := h.f.declined[job.JobID]; got != c.want || h.f.accepted[job.JobID] || len(h.f.fetched) != 0 {
				t.Fatalf("declined %q (want %q), accepted %v, fetched %v\n%s", got, c.want, h.f.accepted[job.JobID], h.f.fetched, h.errOut.String())
			}
		})
	}
}

func TestAgentTamperedCapture(t *testing.T) {
	h := newHarness(t)
	if code := h.pair(t); code != 0 {
		t.Fatal(h.errOut.String())
	}
	e := h.env(true, "y\ny\n")
	job := h.queueRun(t, h.refTarget(t, &e))
	h.f.tamper[job.Selection[1].CaptureID] = true
	if code, _ := h.agent(t, e, "--once"); code != 0 {
		t.Fatalf("exit %d: %s", code, h.errOut.String())
	}
	if !strings.Contains(h.errOut.String(), "body_sha256 doesn't match") {
		t.Fatalf("stderr: %s", h.errOut.String())
	}
	defer h.f.lock()()
	if len(h.f.obs[job.RunID]) != 0 || h.f.finals[job.RunID] != nil {
		t.Fatal("a run with a tampered capture must send nothing")
	}
}

func TestAgentRevoked(t *testing.T) {
	h := newHarness(t)
	if code := h.pair(t); code != 0 {
		t.Fatal(h.errOut.String())
	}
	h.f.helloHook = func(int) (int, string) { return 401, "replaced" }
	code, _ := h.agent(t, h.env(true, ""))
	if code != 1 || !strings.Contains(h.errOut.String(), "revoked") {
		t.Fatalf("exit %d: %s", code, h.errOut.String())
	}
	if _, err := os.Stat(h.store.KeyFile(h.origin())); err == nil {
		t.Fatal("key kept after revocation")
	}
}

func TestAgentNeedsReconfirm(t *testing.T) {
	h := newHarness(t)
	if code := h.pair(t); code != 0 {
		t.Fatal(h.errOut.String())
	}
	_ = h.store.Approve(h.origin(), agentstate.Approval{AgentID: "agt_x", Scheme: "http", Host: "localhost", Port: 3000, SideEffect: true})
	h.f.helloHook = func(n int) (int, string) {
		if n == 1 {
			return 200, "needs_reconfirm"
		}
		return 0, ""
	}
	e := h.env(true, "y\n")
	ctx, cancel := context.WithTimeout(context.Background(), 10*time.Second)
	defer cancel()
	go func() { // stop once the second hello landed
		for ctx.Err() == nil {
			h.f.mu.Lock()
			n := len(h.f.hellos)
			h.f.mu.Unlock()
			if n >= 2 {
				cancel()
			}
			time.Sleep(5 * time.Millisecond)
		}
	}()
	runAgent(ctx, []string{"--server", h.f.srv.URL}, e)
	defer h.f.lock()()
	if h.f.confirms != 2 || len(h.store.Approvals(h.origin())) != 0 {
		t.Fatalf("confirms %d approvals %v\n%s", h.f.confirms, h.store.Approvals(h.origin()), h.errOut.String())
	}
}

func TestAgentHonoursRetryAfter(t *testing.T) {
	h := newHarness(t)
	if code := h.pair(t); code != 0 {
		t.Fatal(h.errOut.String())
	}
	h.f.retry503 = 1
	e := h.env(true, "y\ny\n")
	h.queueRun(t, h.refTarget(t, &e))
	if code, _ := h.agent(t, e, "--once"); code != 0 {
		t.Fatalf("exit %d: %s", code, h.errOut.String())
	}
	h.mu.Lock()
	defer h.mu.Unlock()
	if len(h.sleeps) == 0 || h.sleeps[0] != 7*time.Second {
		t.Fatalf("sleeps %v, want 7s first", h.sleeps)
	}
}

func TestAgentRedirectIsConnectionError(t *testing.T) {
	h := newHarness(t)
	if code := h.pair(t); code != 0 {
		t.Fatal(h.errOut.String())
	}
	var hits int
	other := httptest.NewServer(http.HandlerFunc(func(http.ResponseWriter, *http.Request) { hits++ }))
	defer other.Close()
	h.f.redirect["/agent/v1/jobs/next"] = other.URL + "/agent/v1/jobs/next"
	e := h.env(true, "")
	ctx, cancel := context.WithTimeout(context.Background(), 10*time.Second)
	defer cancel()
	e.sleep = func(context.Context, time.Duration) error { cancel(); return context.Canceled }
	runAgent(ctx, []string{"--server", h.f.srv.URL}, e)
	if hits != 0 || !strings.Contains(h.errOut.String(), "redirect") {
		t.Fatalf("hits %d: %s", hits, h.errOut.String())
	}
}

// TestHostedDocumentMatchesSchema runs the happy path and validates the
// observations and final document against A06's schemas when present.
func TestMessagesMatchSchemas(t *testing.T) {
	dir := schemaDir
	if dir == "" {
		t.Skip("contracts/agent/v1 not present")
	}
	h := newHarness(t)
	if code := h.pair(t); code != 0 {
		t.Fatal(h.errOut.String())
	}
	e := h.env(true, "y\ny\n")
	job := h.queueRun(t, h.refTarget(t, &e))
	if code, _ := h.agent(t, e, "--once"); code != 0 {
		t.Fatal(h.errOut.String())
	}
	// The fake server validated every request body as it arrived; here the
	// final is checked once more explicitly.
	v := schematest.New(dir)
	h.f.mu.Lock()
	final := h.f.finals[job.RunID]
	h.f.mu.Unlock()
	errs, err := v.Validate("final.request.schema.json", final)
	if err != nil {
		t.Fatal(err)
	}
	for _, e := range errs {
		t.Error(e)
	}
}
