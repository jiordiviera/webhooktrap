package agentapi

import (
	"context"
	"crypto/ed25519"
	"crypto/rand"
	"crypto/sha256"
	"encoding/base64"
	"encoding/hex"
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"net/url"
	"strings"
	"sync"
	"testing"
	"time"
)

func TestNormalizeOrigin(t *testing.T) {
	for in, want := range map[string]string{
		"https://WebhookTrap.dev":       "https://webhooktrap.dev",
		"https://webhooktrap.dev:443/":  "https://webhooktrap.dev",
		"https://webhooktrap.test:8443": "https://webhooktrap.test:8443",
		"https://[::1]:8443":            "https://[::1]:8443",
	} {
		if got, err := NormalizeOrigin(in, false); err != nil || got != want {
			t.Errorf("%s → %q, %v; want %q", in, got, err, want)
		}
	}
	for _, c := range []struct {
		in    string
		allow bool
	}{
		{"http://webhooktrap.dev", true}, // hosted never over http
		{"http://localhost:8000", false}, // dev only with the flag
		{"https://user:pw@webhooktrap.dev", false},
		{"https://webhooktrap.dev/path", false},
		{"https://webhooktrap.dev?x=1", false},
		{"ftp://webhooktrap.dev", false},
	} {
		if got, err := NormalizeOrigin(c.in, c.allow); err == nil {
			t.Errorf("%s accepted as %q", c.in, got)
		}
	}
	for _, in := range []string{"http://localhost:8000", "http://webhooktrap.test", "http://127.0.0.1:9", "http://app.localhost"} {
		if _, err := NormalizeOrigin(in, true); err != nil {
			t.Errorf("%s refused with the dev flag: %v", in, err)
		}
	}
}

func TestAuthMessageLayout(t *testing.T) {
	nonce := make([]byte, 32)
	m := AuthMessage("https://a.test", "agt_1", nonce, "2026-11-15T10:00:00.000Z")
	want := []byte{0, 25}
	want = append(want, "webhooktrap-agent-auth/v1"...)
	want = append(want, 0, 14)
	want = append(want, "https://a.test"...)
	want = append(want, 0, 5)
	want = append(want, "agt_1"...)
	want = append(want, 0, 32)
	want = append(want, nonce...)
	want = append(want, 0, 24)
	want = append(want, "2026-11-15T10:00:00.000Z"...)
	if string(m) != string(want) {
		t.Fatalf("got %x\nwant %x", m, want)
	}
}

// authServer issues challenges and tokens and verifies signatures against
// its own origin, like the server does.
type authServer struct {
	mu         sync.Mutex
	origin     string
	pub        ed25519.PublicKey
	now        func() time.Time
	challenges int
	tokens     map[string]bool
	expireNext bool
	verified   int
	failed     int
	srv        *httptest.Server
}

func newAuthServer(t *testing.T, pub ed25519.PublicKey, now func() time.Time) *authServer {
	a := &authServer{pub: pub, now: now, tokens: map[string]bool{}}
	nonces := map[string]string{}
	a.srv = httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		a.mu.Lock()
		defer a.mu.Unlock()
		w.Header().Set("Content-Type", "application/json")
		switch r.URL.Path {
		case "/agent/v1/auth/challenge":
			a.challenges++
			n := make([]byte, 32)
			_, _ = rand.Read(n)
			nb := base64.StdEncoding.EncodeToString(n)
			exp := a.now().Add(time.Minute).UTC().Format("2006-01-02T15:04:05.000Z")
			nonces[nb] = exp
			_ = json.NewEncoder(w).Encode(map[string]any{"proto": 1, "nonce": nb, "expires_at": exp})
		case "/agent/v1/auth/token":
			var req tokenRequest
			_ = json.NewDecoder(r.Body).Decode(&req)
			raw, _ := base64.StdEncoding.DecodeString(req.Nonce)
			sig, _ := base64.StdEncoding.DecodeString(req.Signature)
			exp, ok := nonces[req.Nonce]
			delete(nonces, req.Nonce)
			if !ok || !ed25519.Verify(a.pub, AuthMessage(a.origin, req.AgentID, raw, exp), sig) {
				a.failed++
				w.WriteHeader(401)
				_, _ = w.Write([]byte(`{"error":"unauthenticated"}`))
				return
			}
			a.verified++
			tok := "wt_agt_" + strings.Repeat("a", 39) + string(rune('a'+a.verified))
			a.tokens[tok] = true
			_ = json.NewEncoder(w).Encode(map[string]any{"proto": 1, "access_token": tok,
				"expires_at": a.now().Add(15 * time.Minute).UTC().Format(time.RFC3339), "channel": "c", "broadcast": map[string]any{}})
		case "/agent/v1/confirm":
			tok := strings.TrimPrefix(r.Header.Get("Authorization"), "Bearer ")
			if !a.tokens[tok] || a.expireNext {
				a.expireNext = false
				w.WriteHeader(401)
				_, _ = w.Write([]byte(`{"error":"unauthenticated"}`))
				return
			}
			_, _ = w.Write([]byte(`{"proto":1}`))
		}
	}))
	t.Cleanup(a.srv.Close)
	a.origin = a.srv.URL
	return a
}

type fakeClock struct {
	mu sync.Mutex
	t  time.Time
}

func (c *fakeClock) Now() time.Time      { c.mu.Lock(); defer c.mu.Unlock(); return c.t }
func (c *fakeClock) Add(d time.Duration) { c.mu.Lock(); c.t = c.t.Add(d); c.mu.Unlock() }

func TestSignatureBoundToOrigin(t *testing.T) {
	pub, priv, _ := ed25519.GenerateKey(rand.Reader)
	clk := &fakeClock{t: time.Now()}
	s := newAuthServer(t, pub, clk.Now)
	ctx := context.Background()

	c := New(s.srv.URL, s.srv.Client(), "test", clk.Now)
	c.AgentID, c.Key = "agt_1", priv
	if err := c.Authenticate(ctx); err != nil {
		t.Fatal(err)
	}
	// Same server, but the agent believes it pinned another origin: the
	// signature covers the wrong origin and fails.
	wrong := New("https://webhooktrap.dev", s.srv.Client(), "test", clk.Now)
	wrong.Origin = "https://webhooktrap.dev"
	wrong.HTTP.Transport = rewrite{s.srv.URL}
	wrong.AgentID, wrong.Key = "agt_1", priv
	if err := wrong.Authenticate(ctx); err == nil {
		t.Fatal("a signature for another origin was accepted")
	}
	if s.verified != 1 || s.failed != 1 {
		t.Fatalf("verified %d failed %d", s.verified, s.failed)
	}
}

// rewrite sends every request to base while keeping the signed origin.
type rewrite struct{ base string }

func (r rewrite) RoundTrip(req *http.Request) (*http.Response, error) {
	u := *req.URL
	u.Scheme, u.Host = "http", strings.TrimPrefix(r.base, "http://")
	req2 := req.Clone(req.Context())
	req2.URL = &u
	return http.DefaultTransport.RoundTrip(req2)
}

func TestTokenRefreshAndRetry(t *testing.T) {
	pub, priv, _ := ed25519.GenerateKey(rand.Reader)
	clk := &fakeClock{t: time.Now()}
	s := newAuthServer(t, pub, clk.Now)
	c := New(s.srv.URL, s.srv.Client(), "test", clk.Now)
	c.AgentID, c.Key = "agt_1", priv
	ctx := context.Background()
	if err := c.Confirm(ctx); err != nil {
		t.Fatal(err)
	}
	clk.Add(10 * time.Minute)
	if err := c.Confirm(ctx); err != nil || s.challenges != 1 {
		t.Fatalf("token renewed too early: challenges %d, %v", s.challenges, err)
	}
	clk.Add(4 * time.Minute) // 14 min: inside the 2-minute refresh window
	if err := c.Confirm(ctx); err != nil || s.challenges != 2 {
		t.Fatalf("token not renewed before expiry: challenges %d, %v", s.challenges, err)
	}
	s.expireNext = true // server-side expiry: one re-auth and retry
	if err := c.Confirm(ctx); err != nil || s.challenges != 3 {
		t.Fatalf("no retry after 401: challenges %d, %v", s.challenges, err)
	}
}

func TestRedirectNotFollowed(t *testing.T) {
	var hits int
	other := httptest.NewServer(http.HandlerFunc(func(http.ResponseWriter, *http.Request) { hits++ }))
	defer other.Close()
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		http.Redirect(w, r, other.URL+r.URL.Path, http.StatusFound)
	}))
	defer srv.Close()
	c := New(srv.URL, srv.Client(), "test", time.Now)
	if _, err := c.Pair(context.Background(), PairRequest{Code: "X"}); err != ErrRedirect || hits != 0 {
		t.Fatalf("err %v, hits %d", err, hits)
	}
}

func TestResponseProtoAndSize(t *testing.T) {
	body := `{"agent_id":"agt_1"}`
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(201)
		_, _ = w.Write([]byte(body))
	}))
	defer srv.Close()
	c := New(srv.URL, srv.Client(), "test", time.Now)
	if _, err := c.Pair(context.Background(), PairRequest{}); err == nil || !strings.Contains(err.Error(), "proto") {
		t.Fatalf("answer without proto accepted: %v", err)
	}
	body = `{"proto":1,"pad":"` + strings.Repeat("x", maxSmallResponse) + `"}`
	if _, err := c.Pair(context.Background(), PairRequest{}); err == nil || !strings.Contains(err.Error(), "larger") {
		t.Fatalf("oversized answer accepted: %v", err)
	}
}

func capture(body []byte, headers [][2]string) (SelectionRef, *CaptureRequest) {
	sum := sha256.Sum256(body)
	h := hex.EncodeToString(sum[:])
	return SelectionRef{CaptureID: "cap_1", Seq: 1, BodySHA256: h},
		&CaptureRequest{CaptureID: "cap_1", Headers: headers, BodyBase64: base64.StdEncoding.EncodeToString(body), BodySHA256: h, BodyBytes: len(body)}
}

func TestVerifyCapture(t *testing.T) {
	ref, c := capture([]byte(`{"a":1}`), [][2]string{{"Content-Type", "application/json"}})
	if _, err := VerifyCapture(ref, c); err != nil {
		t.Fatal(err)
	}
	tampered := *c
	tampered.BodyBase64 = base64.StdEncoding.EncodeToString([]byte(`{"a":2}`))
	if _, err := VerifyCapture(ref, &tampered); err == nil || !strings.Contains(err.Error(), "body_sha256") {
		t.Fatalf("tampered body accepted: %v", err)
	}
	other := *c
	other.CaptureID = "cap_2"
	if _, err := VerifyCapture(ref, &other); err == nil {
		t.Fatal("another capture accepted")
	}
	many := make([][2]string, MaxCaptureHeaders+1)
	for i := range many {
		many[i] = [2]string{"X-H", "v"}
	}
	r2, c2 := capture([]byte(`{}`), many)
	if _, err := VerifyCapture(r2, c2); err == nil {
		t.Fatal("too many headers accepted")
	}
	r3, c3 := capture([]byte(`{}`), [][2]string{{"X-H", "a\r\nInjected: 1"}})
	if _, err := VerifyCapture(r3, c3); err == nil {
		t.Fatal("header with CRLF accepted")
	}
	deep := strings.Repeat("[", MaxJSONDepth+1) + strings.Repeat("]", MaxJSONDepth+1)
	r4, c4 := capture([]byte(deep), nil)
	if _, err := VerifyCapture(r4, c4); err == nil {
		t.Fatal("deep JSON accepted")
	}
	ok := strings.Repeat("[", MaxJSONDepth) + strings.Repeat("]", MaxJSONDepth)
	r5, c5 := capture([]byte(ok), nil)
	if _, err := VerifyCapture(r5, c5); err != nil {
		t.Fatalf("depth %d refused: %v", MaxJSONDepth, err)
	}
}

func TestResendableHeadersDropsRedacted(t *testing.T) {
	c := &CaptureRequest{Headers: [][2]string{{"Authorization", "[redacted · 40 bytes]"}, {"Stripe-Signature", "t=1,v1=x"}},
		RedactedHeaders: []string{"authorization"}}
	got := ResendableHeaders(c)
	if len(got) != 1 || got[0][0] != "Stripe-Signature" {
		t.Fatalf("%v", got)
	}
}

func validJob(target string) *Job {
	id := func(p string) string { return p + "_01JC0000000000000000000000" }
	return &Job{JobID: id("job"), Kind: "run", RunID: id("run"), RunNumber: 1,
		Selection: []SelectionRef{{CaptureID: id("cap"), Seq: 1, BodySHA256: strings.Repeat("a", 64)}},
		Scenarios: []string{"once"}, TargetRef: target, Pack: PackRef{ID: "stripe", Version: "1.0.0"},
		FailOn: "high", Seed: "1"}
}

// AC-15: the target path and query follow the server's charset
// (printable ASCII without space).
func TestValidateJobTargetCharset(t *testing.T) {
	if err := ValidateJob(validJob("http://localhost:3000/webhooks/stripe?x=1&y=%20")); err != nil {
		t.Fatalf("valid target refused: %v", err)
	}
	for _, bad := range []string{
		"http://localhost:3000/webhooks?x=a b",
		"http://localhost:3000/web hooks",
		"http://localhost:3000/webhooks?x=a\tb",
		"http://localhost:3000/webhooks?x=é",
		"http://localhost:3000/webhooks?x=\x7f",
	} {
		if err := ValidateJob(validJob(bad)); err == nil {
			t.Errorf("target %q accepted", bad)
		}
	}
}

// AC-15: a development origin never goes through HTTP_PROXY.
func TestDevAwareProxy(t *testing.T) {
	proxy, _ := http.NewRequest(http.MethodGet, "http://proxy.example:3128", nil)
	next := func(*http.Request) (*url.URL, error) { return proxy.URL, nil }
	f := DevAwareProxy(next)
	for _, u := range []string{"http://webhooktrap.test/agent/v1/hello", "http://app.localhost:8000/x", "http://localhost/x", "http://127.0.0.1:8000/x", "http://[::1]:8000/x"} {
		r, _ := http.NewRequest(http.MethodGet, u, nil)
		if p, err := f(r); p != nil || err != nil {
			t.Errorf("%s goes through %v", u, p)
		}
	}
	r, _ := http.NewRequest(http.MethodGet, "https://webhooktrap.dev/agent/v1/hello", nil)
	if p, _ := f(r); p == nil || p.Host != "proxy.example:3128" {
		t.Errorf("hosted origin skipped the proxy: %v", p)
	}
	tr := NewHTTPClient().Transport.(*http.Transport)
	r, _ = http.NewRequest(http.MethodGet, "http://webhooktrap.test/x", nil)
	if p, err := tr.Proxy(r); p != nil || err != nil {
		t.Errorf("production client proxies a dev origin: %v %v", p, err)
	}
}
