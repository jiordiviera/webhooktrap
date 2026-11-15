package main

import (
	"bytes"
	"context"
	"encoding/json"
	"net/http/httptest"
	"os"
	"path/filepath"
	"regexp"
	"strconv"
	"strings"
	"testing"
	"time"

	"github.com/jiordiviera/webhooktrap/agent/execute"
	"github.com/jiordiviera/webhooktrap/agent/internal/packdata"
	"github.com/jiordiviera/webhooktrap/agent/internal/refhandler"
	"github.com/jiordiviera/webhooktrap/agent/render"
)

// Golden inputs (golden-files §6.1): RD Run #3's start time, a fixed seed,
// a fixed probe label salt and RD's per-delivery latencies.
var (
	goldenStart = time.Date(2026, 11, 11, 14, 9, 2, 118e6, time.UTC)
	goldenSeed  = "1234567890"
	testSecret  = "whsec_dGVzdC1vbmx5LXNlY3JldC1mb3ItZ29sZGVucw"
	rdLatency   = map[string]time.Duration{
		"D1": 184, "D2": 121, "D3": 143, "D4": 212, "D5": 196, "D6": 138, "D7": 117, "D8": 199,
		"D10": 11840, "D11": 205, "D12": 167, "D13": 601, "D14": 312, "D15": 448, "D16": 10420,
	}
	fixtures = []string{
		"webhooktrap/fixtures/stripe/invoice.paid.json",
		"webhooktrap/fixtures/stripe/payment_intent.succeeded.json",
		"webhooktrap/fixtures/stripe/customer.subscription.updated.json",
	}
)

// simTransport replaces measured latency with the handler's simulated one and
// moves the virtual clock by it, so goldens need no 11 s sleeps. Burst
// requests run concurrently and don't move the clock (their spread stays 0).
type simTransport struct {
	inner execute.Transport
	clock *execute.VirtualClock
}

type simConn struct {
	execute.Conn
	clock *execute.VirtualClock
}

func (s simTransport) Open(ctx context.Context) (execute.Conn, error) {
	c, err := s.inner.Open(ctx)
	if err != nil {
		return nil, err
	}
	return simConn{c, s.clock}, nil
}

func (c simConn) Do(ctx context.Context, r *execute.Request) execute.Result {
	res := c.Conn.Do(ctx, r)
	if v := res.Header[refhandler.LatencyHeader]; len(v) == 1 {
		ms, _ := strconv.Atoi(v[0])
		res.Latency = time.Duration(ms) * time.Millisecond
		res.Header = nil
		burst := false
		for _, kv := range r.Header {
			if kv[0] == "Webhooktrap-Scenario" && kv[1] == "burst" {
				burst = true
			}
		}
		if !burst {
			c.clock.Advance(res.Latency)
		}
	}
	return res
}

type resetFunc func()

func (f resetFunc) Reset(context.Context) error { f(); return nil }

type result struct {
	code int
	text string
	doc  []byte
	h    *refhandler.Handler
}

// runAgainst runs `webhooktrap test` against the reference handler.
func runAgainst(t *testing.T, fixed bool, scenarios string, extraArgs ...string) result {
	t.Helper()
	src, err := filepath.Abs(filepath.Join("..", "..", "testdata", "fixtures", "stripe"))
	if err != nil {
		t.Fatal(err)
	}
	t.Chdir(t.TempDir())
	if err := os.MkdirAll(filepath.Join("webhooktrap", "fixtures", "stripe"), 0o755); err != nil {
		t.Fatal(err)
	}
	for _, f := range fixtures {
		raw, err := os.ReadFile(filepath.Join(src, filepath.Base(f)))
		if err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(f, raw, 0o644); err != nil {
			t.Fatal(err)
		}
	}
	clock := execute.NewVirtualClock(goldenStart)
	h := refhandler.New(fixed, []byte(testSecret), clock.Wall)
	h.Simulate = true
	h.Base = 150 * time.Millisecond
	h.Latency = map[string]time.Duration{}
	for k, v := range rdLatency {
		h.Latency[k] = v * time.Millisecond
	}
	srv := httptest.NewServer(h)
	t.Cleanup(srv.Close)

	cfg := "# RD Run #3, against the reference handler\n" +
		"target: " + srv.URL + "/webhooks/stripe\n" +
		"fixtures:\n  - " + strings.Join(fixtures, "\n  - ") + "\n" +
		"scenarios: [" + scenarios + "]\n" +
		"probe:\n  - as: orders.count\n    url: " + srv.URL + "/_debug/orders?invoice={{invoice.id}}\n" +
		"reset: npm run db:reset:webhooks\n"
	if err := os.WriteFile("webhooktrap.yml", []byte(cfg), 0o644); err != nil {
		t.Fatal(err)
	}
	var out, errb bytes.Buffer
	e := env{
		stdout: &out, stderr: &errb,
		getenv: func(k string) string {
			if k == "STRIPE_WEBHOOK_SECRET" {
				return testSecret
			}
			return ""
		},
		packs: packdata.Source(), clock: clock, scheduler: clock,
		wrap:  func(tr execute.Transport) execute.Transport { return simTransport{tr, clock} },
		reset: resetFunc(h.Reset), salt: []byte("golden-salt-0001"),
	}
	args := append([]string{"--seed", goldenSeed, "--yes", "--out", "run.json"}, extraArgs...)
	code := runTest(context.Background(), args, e)
	doc, _ := os.ReadFile("run.json")
	port := regexp.MustCompile(`127\.0\.0\.1:\d+`)
	portJSON := regexp.MustCompile(`"port": \d+`)
	norm := func(b []byte) []byte {
		b = port.ReplaceAll(b, []byte("127.0.0.1:3000"))
		return portJSON.ReplaceAll(b, []byte(`"port": 3000`))
	}
	if errb.Len() > 0 && !strings.HasPrefix(errb.String(), "Running") {
		t.Logf("stderr: %s", errb.String())
	}
	return result{code: code, text: string(norm(out.Bytes())), doc: norm(doc), h: h}
}

func golden(t *testing.T, name string, got []byte) {
	t.Helper()
	path := filepath.Join(goldenDir, name)
	if os.Getenv("UPDATE_GOLDEN") == "1" {
		if err := os.WriteFile(path, got, 0o644); err != nil {
			t.Fatal(err)
		}
		return
	}
	want, err := os.ReadFile(path)
	if err != nil {
		t.Fatalf("%v (run with UPDATE_GOLDEN=1 to create)", err)
	}
	if !bytes.Equal(want, got) {
		t.Errorf("%s differs from the golden; diff it or run UPDATE_GOLDEN=1 after review.\n--- got ---\n%s", name, got)
	}
}

var goldenDir string

func init() {
	d, _ := filepath.Abs(filepath.Join("..", "..", "testdata", "golden"))
	goldenDir = d
}

type docView struct {
	Run struct {
		Status   string `json:"status"`
		ExitCode int    `json:"exit_code"`
	} `json:"run"`
	Findings []struct {
		ID              string `json:"id"`
		Type            string `json:"type"`
		Severity        string `json:"severity"`
		Level           int    `json:"level"`
		LikelyCauseRule string `json:"likely_cause_rule"`
		Evidence        []struct {
			D    int    `json:"d"`
			Role string `json:"role"`
		} `json:"evidence"`
	} `json:"findings"`
	Deliveries []json.RawMessage `json:"deliveries"`
}

const run3Scenarios = "once, twice, shuffled, late, retry, tampered, burst, slow_budget"

// TestGoldenRun3 is the proof: the buggy reference handler gives exactly
// RD Run #3's F1 to F4 (SJ §16 change 2, PRB-12a).
func TestGoldenRun3(t *testing.T) {
	r := runAgainst(t, false, run3Scenarios)
	if r.code != 1 {
		t.Fatalf("exit %d, want 1\n%s", r.code, r.text)
	}
	var d docView
	if err := json.Unmarshal(r.doc, &d); err != nil {
		t.Fatal(err)
	}
	type want struct {
		id, typ, sev, lc string
		level            int
		ev               string
	}
	wants := []want{
		{"F1", "accepts_tampered", "critical", "LC-1", 1, "D12:offending"},
		{"F2", "duplicate_changed_state", "high", "LC-8", 2, "D4:reference D5:offending D10:reference D11:offending"},
		{"F3", "over_budget", "medium", "LC-4", 1, "D10:offending D16:offending"},
		{"F4", "probe_gap", "info", "", 2, "D6:offending D15:offending"},
	}
	if len(d.Deliveries) != 16 || len(d.Findings) != len(wants) || d.Run.Status != "findings" {
		t.Fatalf("deliveries %d, findings %d, status %s\n%s", len(d.Deliveries), len(d.Findings), d.Run.Status, r.text)
	}
	for i, w := range wants {
		f := d.Findings[i]
		var ev []string
		for _, e := range f.Evidence {
			ev = append(ev, "D"+strconv.Itoa(e.D)+":"+e.Role)
		}
		got := want{f.ID, f.Type, f.Severity, f.LikelyCauseRule, f.Level, strings.Join(ev, " ")}
		if got != w {
			t.Errorf("finding %d = %+v, want %+v", i+1, got, w)
		}
	}
	golden(t, "run3-findings.u-plain-100.txt", []byte(r.text))
	golden(t, "run3-findings.local.json", r.doc)
}

// TestGoldenFixed: the fixed handler, all 9 scenarios (RD Run #4): Passed,
// only the probe_gap Info left (orders.count still can't see subscriptions).
func TestGoldenFixed(t *testing.T) {
	r := runAgainst(t, true, run3Scenarios+", unsigned")
	var d docView
	if err := json.Unmarshal(r.doc, &d); err != nil {
		t.Fatal(err)
	}
	if r.code != 0 || d.Run.Status != "passed" || len(d.Deliveries) != 17 {
		t.Fatalf("exit %d, status %s, deliveries %d\n%s", r.code, d.Run.Status, len(d.Deliveries), r.text)
	}
	for _, f := range d.Findings {
		if f.Severity != "info" {
			t.Errorf("fixed handler has finding %s %s", f.Type, f.Severity)
		}
	}
	if len(d.Findings) != 1 || d.Findings[0].Type != "probe_gap" {
		t.Errorf("findings %+v, want only probe_gap", d.Findings)
	}
	golden(t, "run4-fixed.u-plain-100.txt", []byte(r.text))
}

// TestRejudgeIdentical: rejudge on the run file gives the same verdict (JSON-4).
func TestRejudgeIdentical(t *testing.T) {
	runAgainst(t, false, run3Scenarios)
	var out bytes.Buffer
	e := env{stdout: &out, stderr: &out, packs: packdata.Source()}
	if code := runRejudge([]string{"run.json"}, e); code != 0 {
		t.Fatalf("rejudge exit %d: %s", code, out.String())
	}
}

// TestASCIIProfile renders the same run with ASCII glyphs only.
func TestASCIIProfile(t *testing.T) {
	r := runAgainst(t, false, run3Scenarios, "--ascii")
	for _, c := range r.text {
		if c > 0x7e || (c < 0x20 && c != '\n') {
			t.Fatalf("non-ASCII %q in ASCII profile", c)
		}
	}
}

// TestNoSecretInOutput: JSON-2, the secret appears in no output.
func TestNoSecretInOutput(t *testing.T) {
	r := runAgainst(t, false, run3Scenarios)
	for _, s := range []string{testSecret, "dGVzdC1vbmx5", "Stripe-Signature\": \"t="} {
		if strings.Contains(r.text, s) || bytes.Contains(r.doc, []byte(s)) {
			t.Fatalf("output contains %q", s)
		}
	}
	_ = render.SchemaVersion
}
