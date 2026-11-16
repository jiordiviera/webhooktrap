package main

import (
	"bytes"
	"context"
	"crypto/sha256"
	"encoding/base64"
	"encoding/hex"
	"encoding/json"
	"io"
	"net/http"
	"net/http/httptest"
	"net/netip"
	"os"
	"path/filepath"
	"strings"
	"sync"
	"testing"
	"time"

	"github.com/jiordiviera/webhooktrap/agent/execute"
	"github.com/jiordiviera/webhooktrap/agent/internal/agentapi"
	"github.com/jiordiviera/webhooktrap/agent/internal/fixture"
	"github.com/jiordiviera/webhooktrap/agent/internal/packdata"
)

const testRunnerToken = "wt_rnr_ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijkl"

// fakeRunnerAPI is /runner/v1 (contracts §6) closely enough to drive
// serve-run: one job, then 204s.
type fakeRunnerAPI struct {
	t      *testing.T
	srv    *httptest.Server
	cancel context.CancelFunc

	mu       sync.Mutex
	hello    int // status of hello; 0 = 204
	job      *agentapi.Job
	served   bool
	captures map[string]fakeCapture
	calls    []string
	badAuth  int
	accepted bool
	aborted  string
	fetched  int
	obs      []agentapi.Observations
	final    []byte
}

func newFakeRunnerAPI(t *testing.T) *fakeRunnerAPI {
	f := &fakeRunnerAPI{t: t, captures: map[string]fakeCapture{}}
	f.srv = httptest.NewServer(http.HandlerFunc(f.serve))
	t.Cleanup(f.srv.Close)
	return f
}

func (f *fakeRunnerAPI) serve(w http.ResponseWriter, r *http.Request) {
	f.mu.Lock()
	defer f.mu.Unlock()
	f.calls = append(f.calls, r.Method+" "+r.URL.Path)
	if r.Header.Get("Authorization") != "Bearer "+testRunnerToken {
		f.badAuth++
		w.WriteHeader(http.StatusUnauthorized)
		return
	}
	if !strings.HasPrefix(r.URL.Path, "/runner/v1/") {
		w.WriteHeader(http.StatusNotFound)
		return
	}
	p := strings.TrimPrefix(r.URL.Path, "/runner/v1")
	switch {
	case p == "/hello":
		var h agentapi.RunnerHello
		_ = json.NewDecoder(r.Body).Decode(&h)
		if h.Proto != 1 || h.Version == "" {
			w.WriteHeader(http.StatusBadRequest)
			return
		}
		if f.hello != 0 {
			w.WriteHeader(f.hello)
			return
		}
		w.WriteHeader(http.StatusNoContent)
	case p == "/jobs/next":
		if f.job == nil || f.served {
			f.mu.Unlock()
			time.Sleep(10 * time.Millisecond)
			f.mu.Lock()
			w.WriteHeader(http.StatusNoContent)
			return
		}
		f.served = true
		writeProto(w, f.job)
	case strings.HasSuffix(p, "/accept"):
		f.accepted = true
		w.WriteHeader(http.StatusNoContent)
	case strings.HasSuffix(p, "/abort"):
		var b struct {
			Reason string `json:"reason"`
		}
		_ = json.NewDecoder(r.Body).Decode(&b)
		f.aborted = b.Reason
		w.WriteHeader(http.StatusNoContent)
		go func() { time.Sleep(50 * time.Millisecond); f.cancel() }()
	case strings.HasSuffix(p, "/request"):
		parts := strings.Split(p, "/")
		id := parts[len(parts)-2]
		c, ok := f.captures[id]
		if !ok || !f.accepted {
			w.WriteHeader(http.StatusForbidden)
			return
		}
		f.fetched++
		sum := sha256.Sum256(c.body)
		writeProto(w, agentapi.CaptureRequest{CaptureID: id, Seq: 1, Headers: c.headers, RedactedHeaders: []string{},
			BodyBytes: len(c.body), BodyBase64: base64.StdEncoding.EncodeToString(c.body), BodySHA256: hex.EncodeToString(sum[:])})
	case strings.HasSuffix(p, "/observations"):
		var o agentapi.Observations
		_ = json.NewDecoder(r.Body).Decode(&o)
		f.obs = append(f.obs, o)
		w.WriteHeader(http.StatusNoContent)
	case strings.HasSuffix(p, "/final"):
		f.final, _ = io.ReadAll(r.Body)
		w.WriteHeader(http.StatusNoContent)
		go func() { time.Sleep(50 * time.Millisecond); f.cancel() }()
	default:
		w.WriteHeader(http.StatusNotFound)
	}
}

// writeProto answers v with "proto": 1 added.
func writeProto(w http.ResponseWriter, v any) {
	raw, _ := json.Marshal(v)
	var m map[string]any
	_ = json.Unmarshal(raw, &m)
	m["proto"] = 1
	w.Header().Set("Content-Type", "application/json")
	_ = json.NewEncoder(w).Encode(m)
}

// queue registers one stripe capture and a run job against target.
func (f *fakeRunnerAPI) queue(t *testing.T, target string, mutate func(*agentapi.Job)) {
	t.Helper()
	c, err := fixture.Load(filepath.Join(agentRoot, "testdata", "fixtures", "stripe", "invoice.paid.json"))
	if err != nil {
		t.Fatal(err)
	}
	pk, err := packdata.Source().Load("stripe", "")
	if err != nil {
		t.Fatal(err)
	}
	id := "cap_" + randID()
	sum := sha256.Sum256(c.Body)
	job := &agentapi.Job{JobID: "job_" + randID(), Kind: "run", RunID: "run_" + randID(), RunNumber: 1,
		Requester: agentapi.Requester{WorkspaceLabel: "Brindle Coffee"}, ProjectKey: "Br7nQ2xk9A",
		Selection: []agentapi.SelectionRef{{CaptureID: id, Seq: 1, BodySHA256: hex.EncodeToString(sum[:])}},
		Scenarios: []string{"once", "twice"}, TargetRef: target, Pack: agentapi.PackRef{ID: "stripe", Version: pk.Version},
		FailOn: "high", Seed: "42", Guard: "public", Limits: &agentapi.JobLimits{RequestTimeoutMS: 10000, ResponseCapBytes: 65536},
		ExpiresAt: time.Now().Add(10 * time.Minute).UTC().Format("2006-01-02T15:04:05.000Z")}
	if mutate != nil {
		mutate(job)
	}
	f.mu.Lock()
	f.captures[id] = fakeCapture{headers: c.Headers, body: c.Body}
	f.job = job
	f.mu.Unlock()
}

// fakeTarget answers 200 to every delivery without dialling.
type fakeTarget struct {
	mu   sync.Mutex
	reqs []execute.Request
	opts []*execute.HTTPTransport
}

func (t *fakeTarget) wrap(tr execute.Transport) execute.Transport {
	t.mu.Lock()
	defer t.mu.Unlock()
	if h, ok := tr.(*execute.HTTPTransport); ok {
		t.opts = append(t.opts, h)
	}
	return t
}

func (t *fakeTarget) Open(context.Context) (execute.Conn, error) { return t, nil }
func (t *fakeTarget) Close() error                               { return nil }
func (t *fakeTarget) Do(_ context.Context, r *execute.Request) execute.Result {
	t.mu.Lock()
	t.reqs = append(t.reqs, *r)
	t.mu.Unlock()
	return execute.Result{Status: 200, Responded: true, Latency: 20 * time.Millisecond}
}

// publicResolver maps every name to one public address.
type publicResolver struct{}

func (publicResolver) LookupNetIP(context.Context, string, string) ([]netip.Addr, error) {
	return []netip.Addr{netip.MustParseAddr("93.184.216.34")}, nil
}

func runnerEnv(t *testing.T, f *fakeRunnerAPI, tgt *fakeTarget, extra map[string]string) (env, *bytes.Buffer) {
	t.Helper()
	tokFile := filepath.Join(t.TempDir(), "runner_token")
	if err := os.WriteFile(tokFile, []byte(testRunnerToken+"\n"), 0o600); err != nil {
		t.Fatal(err)
	}
	vars := map[string]string{"RUNNER_SERVER_ORIGIN": f.srv.URL, "RUNNER_TOKEN_FILE": tokFile}
	for k, v := range extra {
		vars[k] = v
	}
	var errb bytes.Buffer
	clock := execute.NewVirtualClock(time.Unix(1794406143, 0))
	return env{
		stdout: io.Discard, stderr: &syncWriter{w: &errb}, getenv: func(k string) string { return vars[k] },
		packs: packdata.Source(), clock: clock, scheduler: clock, wrap: tgt.wrap, resolver: publicResolver{},
		salt: []byte("runner-salt-0001"), now: time.Now,
		sleep: func(ctx context.Context, d time.Duration) error { return sleepCtx(ctx, min(d, 20*time.Millisecond)) },
	}, &errb
}

type syncWriter struct {
	mu sync.Mutex
	w  io.Writer
}

func (s *syncWriter) Write(p []byte) (int, error) {
	s.mu.Lock()
	defer s.mu.Unlock()
	return s.w.Write(p)
}

func serveRun(t *testing.T, f *fakeRunnerAPI, e env, args ...string) int {
	t.Helper()
	ctx, cancel := context.WithTimeout(context.Background(), 20*time.Second)
	defer cancel()
	f.cancel = cancel
	code := runServeRun(ctx, args, e)
	if ctx.Err() == context.DeadlineExceeded {
		t.Fatal("serve-run did not finish the job in time")
	}
	return code
}

func TestServeRunPublicJob(t *testing.T) {
	// RN-7: a webhooktrap.yml with probes in the working directory and probe
	// variables in the environment are never read.
	dir := t.TempDir()
	t.Chdir(dir)
	if err := os.WriteFile("webhooktrap.yml", []byte("target: https://evil.example/\nprobe:\n  - as: orders.count\n    url: https://probe.example/x\nreset: touch /tmp/pwned\n"), 0o644); err != nil {
		t.Fatal(err)
	}
	f := newFakeRunnerAPI(t)
	f.queue(t, "https://handler.example/webhooks/stripe", nil)
	tgt := &fakeTarget{}
	e, errb := runnerEnv(t, f, tgt, map[string]string{"WEBHOOKTRAP_PROBE_URL": "https://probe.example/"})
	if code := serveRun(t, f, e); code != 0 {
		t.Fatalf("exit %d: %s", code, errb.String())
	}
	f.mu.Lock()
	defer f.mu.Unlock()
	if f.badAuth > 0 || !f.accepted || f.aborted != "" || f.fetched != 1 || f.final == nil {
		t.Fatalf("calls %v, badAuth %d, aborted %q\n%s", f.calls, f.badAuth, f.aborted, errb.String())
	}
	if len(f.obs) == 0 || f.obs[0].Seq != 1 || f.obs[0].DeliveryCount == nil || *f.obs[0].DeliveryCount != 3 || f.obs[0].ProbeEntries != nil || f.obs[0].LabelBits != 0 {
		t.Fatalf("first observation: %+v", f.obs)
	}
	// Stripe signs a timestamp and a server run has no secret (SIGN-2), so
	// the deliveries may stay "Needs secret"; whatever is sent goes to the
	// pinned target only.
	for _, r := range tgt.reqs {
		if r.Host != "handler.example" || r.Path != "/webhooks/stripe" {
			t.Fatalf("delivery to %s%s", r.Host, r.Path)
		}
	}
	if len(tgt.opts) == 0 || tgt.opts[0].Timeout != 10*time.Second || tgt.opts[0].ResponseCap != 65536 {
		t.Fatalf("job limits not passed to the transport: %+v", tgt.opts)
	}
	var doc struct {
		Run struct {
			Levels       []int             `json:"levels"`
			Provenance   map[string]string `json:"provenance"`
			ProbeEntries []json.RawMessage `json:"probe_entries"`
		} `json:"run"`
	}
	if err := json.Unmarshal(f.final, &doc); err != nil {
		t.Fatal(err)
	}
	if len(doc.Run.Levels) != 1 || doc.Run.Levels[0] != 1 || len(doc.Run.ProbeEntries) != 0 || doc.Run.Provenance["executor"] != "server" {
		t.Fatalf("final run: %+v", doc.Run)
	}
	if strings.Contains(errb.String(), testRunnerToken) {
		t.Fatal("the token reached the log")
	}
}

func TestServeRunRefusals(t *testing.T) {
	cases := []struct {
		name   string
		target string
		mutate func(*agentapi.Job)
		extra  map[string]string
		reason string
	}{
		{"allowlist job on a public runner", "https://handler.example/", func(j *agentapi.Job) {
			j.Guard = "allowlist"
			j.Allowlist = []agentapi.AllowlistEntry{{CIDR: "10.0.0.0/8", PortFrom: 443, PortTo: 443}}
		}, nil, agentapi.AbortGuardExceedsCeiling},
		{"private literal", "http://10.1.2.3:8080/hook", nil, nil, agentapi.AbortTargetNotAllowed},
		{"metadata", "http://169.254.169.254/latest", nil, nil, agentapi.AbortTargetNotAllowed},
		{"loopback", "http://127.0.0.1:3000/", nil, nil, agentapi.AbortTargetNotAllowed},
		{"entry outside the local allow-list", "http://10.1.2.3:8080/hook", func(j *agentapi.Job) {
			j.Guard = "allowlist"
			j.Allowlist = []agentapi.AllowlistEntry{{CIDR: "10.0.0.0/8", PortFrom: 1, PortTo: 65535}}
		}, map[string]string{"RUNNER_GUARD_CEILING": "allowlist"}, agentapi.AbortGuardExceedsCeiling},
		{"the app's own address (RN-4)", "https://93.184.216.34/", nil, nil, agentapi.AbortTargetNotAllowed},
	}
	for _, c := range cases {
		t.Run(c.name, func(t *testing.T) {
			f := newFakeRunnerAPI(t)
			f.queue(t, c.target, c.mutate)
			tgt := &fakeTarget{}
			extra := c.extra
			if extra != nil && extra["RUNNER_GUARD_CEILING"] == "allowlist" {
				p := filepath.Join(t.TempDir(), "allow")
				_ = os.WriteFile(p, []byte("# local\n10.1.2.0/24:8080\n"), 0o600)
				extra["RUNNER_ALLOWLIST_FILE"] = p
			}
			e, errb := runnerEnv(t, f, tgt, extra)
			if strings.Contains(c.name, "RN-4") {
				inner := e.getenv
				e.getenv = func(k string) string {
					if k == "RUNNER_SERVER_ORIGIN" {
						return "https://app.example" // resolves to 93.184.216.34
					}
					return inner(k)
				}
				// The fake API is only reachable at its own URL: dial it there.
				e.http = &http.Client{Transport: rewriteTo(f.srv.URL)}
			}
			if code := serveRun(t, f, e); code != 0 {
				t.Fatalf("exit %d: %s", code, errb.String())
			}
			f.mu.Lock()
			defer f.mu.Unlock()
			if f.aborted != c.reason || f.fetched != 0 || f.final != nil || len(tgt.reqs) != 0 {
				t.Fatalf("aborted %q (want %q), fetched %d, deliveries %d\n%s", f.aborted, c.reason, f.fetched, len(tgt.reqs), errb.String())
			}
		})
	}
}

type rewriteTo string

func (u rewriteTo) RoundTrip(r *http.Request) (*http.Response, error) {
	r2 := r.Clone(r.Context())
	r2.URL.Scheme, r2.URL.Host = "http", strings.TrimPrefix(string(u), "http://")
	return http.DefaultTransport.RoundTrip(r2)
}

func TestServeRunFatalAnswers(t *testing.T) {
	for _, status := range []int{http.StatusUpgradeRequired, http.StatusUnauthorized} {
		f := newFakeRunnerAPI(t)
		f.hello = status
		e, errb := runnerEnv(t, f, &fakeTarget{}, nil)
		if code := serveRun(t, f, e); code != 1 {
			t.Fatalf("hello %d: exit %d, want 1: %s", status, code, errb.String())
		}
	}
}

func TestServeRunConfig(t *testing.T) {
	f := newFakeRunnerAPI(t)
	for name, vars := range map[string]map[string]string{
		"no token":            {"RUNNER_TOKEN_FILE": ""},
		"bad token":           {"RUNNER_TOKEN_FILE": "", "RUNNER_TOKEN": "hunter2"},
		"http public origin":  {"RUNNER_SERVER_ORIGIN": "http://app.example.com"},
		"no origin":           {"RUNNER_SERVER_ORIGIN": ""},
		"bad ceiling":         {"RUNNER_GUARD_CEILING": "local"},
		"allowlist, no file":  {"RUNNER_GUARD_CEILING": "allowlist"},
		"public with a list":  {"RUNNER_ALLOWLIST_FILE": "/nonexistent"},
		"too much concurrent": {"RUNNER_CONCURRENCY": "99"},
	} {
		e, errb := runnerEnv(t, f, &fakeTarget{}, vars)
		if code := runServeRun(context.Background(), nil, e); code != 2 {
			t.Errorf("%s: exit %d, want 2 (%s)", name, code, errb.String())
		}
	}
	for raw, want := range map[string]string{
		"http://app:8080":          "http://app:8080",
		"http://app":               "http://app",
		"https://webhooktrap.test": "https://webhooktrap.test",
		"http://localhost:8000":    "http://localhost:8000",
	} {
		if got, err := runnerOrigin(raw); err != nil || got != want {
			t.Errorf("runnerOrigin(%q) = %q, %v", raw, got, err)
		}
	}
	for _, raw := range []string{"http://10.0.0.2", "http://app/x", "ftp://app", "http://user@app"} {
		if _, err := runnerOrigin(raw); err == nil {
			t.Errorf("runnerOrigin(%q) accepted", raw)
		}
	}
}
