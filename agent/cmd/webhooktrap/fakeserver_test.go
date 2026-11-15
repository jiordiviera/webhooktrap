package main

import (
	"bytes"
	"context"
	"crypto/ed25519"
	"crypto/rand"
	"crypto/sha256"
	"encoding/base64"
	"encoding/hex"
	"encoding/json"
	"io"
	"net/http"
	"net/http/httptest"
	"regexp"
	"strconv"
	"strings"
	"sync"
	"testing"
	"time"

	"github.com/jiordiviera/webhooktrap/agent/internal/agentapi"
	"github.com/jiordiviera/webhooktrap/agent/internal/schematest"
)

// fakeServer implements the /agent/v1 contract (contracts.md §5) closely
// enough to drive the agent: code HMAC lookup is a map, tokens are random,
// every request body is checked for "proto": 1, and security-relevant
// objects are closed (unknown fields refused).
type fakeServer struct {
	t      *testing.T
	srv    *httptest.Server
	origin string // what the server believes its origin is (signature binding)

	mu         sync.Mutex
	codes      map[string]bool
	guest      bool
	label      string
	echoOrigin string // pair's server.origin; defaults to origin
	agents     map[string]*fakeAgent
	nonces     map[string]fakeNonce
	tokens     map[string]fakeToken
	jobs       []*agentapi.Job
	issued     map[string]*agentapi.Job
	captures   map[string]fakeCapture
	tamper     map[string]bool // capture ids whose body is altered on fetch
	accepted   map[string]bool
	declined   map[string]string
	declines   int               // decline calls, all jobs
	aborted    map[string]string // job id → abort reason
	kind       string            // pair's server.kind (default selfhost)
	helloKind  string            // hello's optional server.kind; "" omits the object
	finalFail  int               // final answers this status (0: accept)
	replaces   map[string]string // pair's replaces object (nil: null)
	obs        map[string]map[int]agentapi.Observations
	finals     map[string][]byte
	hellos     [][]byte
	calls      []string
	fetched    []string
	confirms   int
	deletes    int
	challenges int
	retry503   int // jobs/next answers 503 this many times
	redirect   map[string]string
	helloHook  func(n int) (int, string) // status, body override for the n-th hello
	onDecline  func(jobID, reason string)
	onFinal    func(runID string)
	now        func() time.Time
	schema     *schematest.Validator
}

type fakeAgent struct {
	pub            ed25519.PublicKey
	confirmed      bool
	revoked        string
	needsReconfirm bool
	name           string
}

type fakeNonce struct {
	agent, exp string
	used       bool
}

type fakeToken struct {
	agent string
	exp   time.Time
}

type fakeCapture struct {
	headers [][2]string
	body    []byte
}

func newFakeServer(t *testing.T) *fakeServer {
	f := &fakeServer{t: t, codes: map[string]bool{}, label: "Brindle Coffee", agents: map[string]*fakeAgent{},
		nonces: map[string]fakeNonce{}, tokens: map[string]fakeToken{}, issued: map[string]*agentapi.Job{},
		captures: map[string]fakeCapture{}, tamper: map[string]bool{}, accepted: map[string]bool{}, declined: map[string]string{}, aborted: map[string]string{},
		obs: map[string]map[int]agentapi.Observations{}, finals: map[string][]byte{}, redirect: map[string]string{},
		now: time.Now}
	if schemaDir != "" {
		f.schema = schematest.New(schemaDir)
	}
	f.srv = httptest.NewServer(http.HandlerFunc(f.serve))
	t.Cleanup(f.srv.Close)
	f.origin = f.srv.URL
	return f
}

func (f *fakeServer) json(w http.ResponseWriter, status int, v any) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(status)
	_ = json.NewEncoder(w).Encode(v)
}

func (f *fakeServer) fail(w http.ResponseWriter, status int, code string, extra ...string) {
	body := map[string]any{"error": code}
	for i := 0; i+1 < len(extra); i += 2 {
		body[extra[i]] = extra[i+1]
	}
	f.json(w, status, body)
}

var requestSchemas = map[string]string{
	"/agent/v1/pair": "pair.request.schema.json", "/agent/v1/auth/challenge": "auth-challenge.request.schema.json",
	"/agent/v1/auth/token": "auth-token.request.schema.json", "/agent/v1/confirm": "confirm.request.schema.json",
	"/agent/v1/hello": "hello.request.schema.json", "decline": "decline.request.schema.json",
	"accept": "confirm.request.schema.json", "observations": "observations.request.schema.json", "final": "final.request.schema.json",
	"abort": "abort.request.schema.json",
}

func schemaFor(path string) string {
	if n, ok := requestSchemas[path]; ok {
		return n
	}
	return requestSchemas[path[strings.LastIndex(path, "/")+1:]]
}

// decode reads a body that must carry proto 1; closed refuses unknown fields.
func decode(r *http.Request, v any, closed bool) bool {
	raw, err := io.ReadAll(io.LimitReader(r.Body, 3<<20))
	if err != nil {
		return false
	}
	if f, ok := r.Context().Value(fakeKey{}).(*fakeServer); ok {
		if n := schemaFor(r.URL.Path); n != "" {
			f.checkSchema(n, raw)
		}
	}
	var p struct {
		Proto *int `json:"proto"`
	}
	if json.Unmarshal(raw, &p) != nil || p.Proto == nil || *p.Proto != 1 {
		return false
	}
	dec := json.NewDecoder(bytes.NewReader(raw))
	if closed {
		dec.DisallowUnknownFields()
	}
	return dec.Decode(v) == nil
}

var envNameContract = regexp.MustCompile(`^[A-Z_][A-Z0-9_]{0,63}$`)

func (f *fakeServer) authed(r *http.Request) (string, *fakeAgent, bool) {
	tok, ok := strings.CutPrefix(r.Header.Get("Authorization"), "Bearer ")
	if !ok {
		return "", nil, false
	}
	ft, ok := f.tokens[tok]
	if !ok || f.now().After(ft.exp) {
		return "", nil, false
	}
	return ft.agent, f.agents[ft.agent], true
}

type fakeKey struct{}

func (f *fakeServer) serve(w http.ResponseWriter, r *http.Request) {
	r = r.WithContext(context.WithValue(r.Context(), fakeKey{}, f))
	f.mu.Lock()
	defer f.mu.Unlock()
	path := r.URL.Path
	f.calls = append(f.calls, r.Method+" "+path)
	if to, ok := f.redirect[path]; ok {
		http.Redirect(w, r, to, http.StatusTemporaryRedirect)
		return
	}
	switch {
	case r.Method == http.MethodPost && path == "/agent/v1/pair":
		var req agentapi.PairRequest
		if !decode(r, &req, true) {
			f.fail(w, 422, "invalid")
			return
		}
		if !f.codes[req.Code] {
			f.fail(w, 400, "invalid_code")
			return
		}
		delete(f.codes, req.Code)
		pub, err := base64.StdEncoding.DecodeString(req.PublicKey)
		if err != nil || len(pub) != ed25519.PublicKeySize {
			f.fail(w, 422, "invalid")
			return
		}
		id := "agt_" + randID()
		name := req.Name
		if f.replaces != nil {
			name = f.replaces["name"]
		} else {
			for n := 2; f.nameTaken(name); n++ { // AC-3: first free suffix
				name = req.Name + "-" + strconv.Itoa(n)
			}
		}
		f.agents[id] = &fakeAgent{pub: pub, needsReconfirm: true, name: name}
		echo := f.echoOrigin
		if echo == "" {
			echo = f.origin
		}
		var replaces any
		if f.replaces != nil {
			replaces = f.replaces
		}
		f.json(w, 201, map[string]any{"proto": 1, "agent_id": id, "name": name, "replaces": replaces,
			"workspace":            map[string]any{"label": f.label, "guest": f.guest},
			"server":               map[string]any{"origin": echo, "name": "local", "kind": f.serverKind(), "min_agent_version": "0.0.0"},
			"probe_display_policy": "strict", "guest": f.guest, "needs_reconfirm": true})
	case r.Method == http.MethodPost && path == "/agent/v1/auth/challenge":
		var req struct {
			Proto   int    `json:"proto"`
			AgentID string `json:"agent_id"`
		}
		if !decode(r, &req, true) {
			f.fail(w, 422, "invalid")
			return
		}
		a := f.agents[req.AgentID]
		if a == nil {
			f.fail(w, 401, "unauthenticated")
			return
		}
		if a.revoked != "" {
			f.fail(w, 401, "revoked", "reason", a.revoked)
			return
		}
		f.challenges++
		n := make([]byte, 32)
		_, _ = rand.Read(n)
		nb := base64.StdEncoding.EncodeToString(n)
		exp := f.now().Add(time.Minute).UTC().Format(time.RFC3339)
		f.nonces[nb] = fakeNonce{agent: req.AgentID, exp: exp}
		f.json(w, 200, map[string]any{"proto": 1, "nonce": nb, "expires_at": exp})
	case r.Method == http.MethodPost && path == "/agent/v1/auth/token":
		var req struct {
			Proto     int    `json:"proto"`
			AgentID   string `json:"agent_id"`
			Nonce     string `json:"nonce"`
			Signature string `json:"signature"`
		}
		if !decode(r, &req, true) {
			f.fail(w, 422, "invalid")
			return
		}
		n, ok := f.nonces[req.Nonce]
		a := f.agents[req.AgentID]
		if !ok || n.used || n.agent != req.AgentID || a == nil {
			f.fail(w, 401, "unauthenticated")
			return
		}
		n.used = true
		f.nonces[req.Nonce] = n
		raw, _ := base64.StdEncoding.DecodeString(req.Nonce)
		sig, _ := base64.StdEncoding.DecodeString(req.Signature)
		if !ed25519.Verify(a.pub, agentapi.AuthMessage(f.origin, req.AgentID, raw, n.exp), sig) {
			f.fail(w, 401, "unauthenticated")
			return
		}
		tok := "wt_agt_" + randAlnum(40)
		exp := f.now().Add(15 * time.Minute)
		f.tokens[tok] = fakeToken{agent: req.AgentID, exp: exp}
		f.json(w, 200, map[string]any{"proto": 1, "access_token": tok, "expires_at": exp.UTC().Format(time.RFC3339),
			"channel": "private-agent.0193a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5b", "broadcast": map[string]any{"host": nil, "key": nil, "auth_endpoint": "/agent/v1/broadcasting/auth"}})
	default:
		f.authedRoute(w, r, path)
	}
}

func (f *fakeServer) authedRoute(w http.ResponseWriter, r *http.Request, path string) {
	id, a, ok := f.authed(r)
	if !ok {
		f.fail(w, 401, "unauthenticated")
		return
	}
	if a.revoked != "" {
		f.fail(w, 401, "revoked", "reason", a.revoked)
		return
	}
	switch {
	case r.Method == http.MethodDelete && path == "/agent/v1/agent":
		f.deletes++
		a.revoked = "user"
		w.WriteHeader(204)
		return
	case r.Method == http.MethodPost && path == "/agent/v1/confirm":
		var req struct {
			Proto int `json:"proto"`
		}
		if !decode(r, &req, true) {
			f.fail(w, 422, "invalid")
			return
		}
		f.confirms++
		a.confirmed, a.needsReconfirm = true, false
		f.json(w, 200, map[string]any{"proto": 1})
		return
	}
	if !a.confirmed {
		f.fail(w, 403, "needs_reconfirm")
		return
	}
	switch {
	case r.Method == http.MethodPost && path == "/agent/v1/hello":
		raw, _ := io.ReadAll(r.Body)
		f.hellos = append(f.hellos, raw)
		r.Body = io.NopCloser(bytes.NewReader(raw))
		var h agentapi.Hello
		if !decode(r, &h, true) || strings.Contains(string(raw), "hint_last4") {
			f.fail(w, 422, "invalid")
			return
		}
		for _, s := range h.Secrets {
			if !envNameContract.MatchString(s.EnvName) {
				f.fail(w, 422, "invalid")
				return
			}
		}
		if f.helloHook != nil {
			if status, code := f.helloHook(len(f.hellos)); status != 0 {
				if status == 401 {
					a.revoked = code
					f.fail(w, 401, "revoked", "reason", code)
					return
				}
				if status == 200 && code == "needs_reconfirm" {
					a.needsReconfirm = true
				} else {
					f.fail(w, status, code)
					return
				}
			}
		}
		body := map[string]any{"proto": 1, "guest": f.guest, "needs_reconfirm": a.needsReconfirm,
			"workspace": map[string]any{"label": f.label}, "probe_display_policy": "strict", "min_agent_version": "0.0.0",
			"deprecation": nil, "pending_jobs": len(f.jobs), "signature_checks_pending": 0}
		if f.helloKind != "" {
			body["server"] = map[string]any{"kind": f.helloKind}
		}
		f.json(w, 200, body)
	case r.Method == http.MethodGet && path == "/agent/v1/jobs/next":
		if r.URL.Query().Get("wait") != "25" {
			f.fail(w, 422, "invalid")
			return
		}
		if f.retry503 > 0 {
			f.retry503--
			w.Header().Set("Retry-After", "7")
			f.fail(w, 503, "busy")
			return
		}
		if a.needsReconfirm {
			f.fail(w, 403, "needs_reconfirm")
			return
		}
		if len(f.jobs) == 0 {
			f.mu.Unlock()
			time.Sleep(20 * time.Millisecond)
			f.mu.Lock()
			w.WriteHeader(204)
			return
		}
		j := f.jobs[0]
		f.jobs = f.jobs[1:]
		f.issued[j.JobID] = j
		body, _ := json.Marshal(j)
		var m map[string]any
		_ = json.Unmarshal(body, &m)
		m["proto"] = 1
		f.json(w, 200, m)
	default:
		f.jobRoute(w, r, id, path)
	}
}

var (
	captureRe = regexp.MustCompile(`^/agent/v1/jobs/([^/]+)/captures/([^/]+)/request$`)
	jobActRe  = regexp.MustCompile(`^/agent/v1/jobs/([^/]+)/(accept|decline|abort)$`)
	runActRe  = regexp.MustCompile(`^/agent/v1/runs/([^/]+)/(observations|final)$`)
)

func (f *fakeServer) jobRoute(w http.ResponseWriter, r *http.Request, _ string, path string) {
	if m := captureRe.FindStringSubmatch(path); m != nil && r.Method == http.MethodGet {
		j := f.issued[m[1]]
		ref := false
		if j != nil {
			for _, s := range j.Selection {
				ref = ref || s.CaptureID == m[2]
			}
		}
		c, ok := f.captures[m[2]]
		if !ref || !ok { // TM-35: only captures referenced by a job issued to this agent
			f.fail(w, 404, "not_found")
			return
		}
		f.fetched = append(f.fetched, m[2])
		body := c.body
		if f.tamper[m[2]] {
			body = append(append([]byte{}, body...), ' ')
		}
		sum := sha256.Sum256(c.body)
		f.json(w, 200, map[string]any{"proto": 1, "capture_id": m[2], "seq": 1, "method": "POST", "raw_target": "/c/x",
			"headers": c.headers, "redacted_headers": []string{}, "content_type": "application/json",
			"body_base64": base64.StdEncoding.EncodeToString(body), "body_sha256": hex.EncodeToString(sum[:]), "body_bytes": len(body)})
		return
	}
	if m := jobActRe.FindStringSubmatch(path); m != nil && r.Method == http.MethodPost {
		if f.issued[m[1]] == nil {
			f.fail(w, 404, "not_found")
			return
		}
		if m[2] == "accept" {
			if f.accepted[m[1]] {
				f.fail(w, 409, "conflict")
				return
			}
			if raw, _ := io.ReadAll(r.Body); len(raw) > 0 {
				f.checkSchema("confirm.request.schema.json", raw)
			}
			f.accepted[m[1]] = true
			f.json(w, 200, map[string]any{"proto": 1})
			return
		}
		var req struct {
			Proto  int    `json:"proto"`
			Reason string `json:"reason"`
		}
		if !decode(r, &req, true) {
			f.fail(w, 422, "invalid")
			return
		}
		if m[2] == "abort" {
			if !f.accepted[m[1]] {
				f.fail(w, 409, "job_unavailable")
				return
			}
			f.aborted[m[1]] = req.Reason
			f.json(w, 200, map[string]any{"proto": 1})
			return
		}
		f.declined[m[1]] = req.Reason
		f.declines++
		if req.Reason == agentapi.DeclineNeedsConfirmation { // the run stays Queued: the job is offered again
			f.jobs = append(f.jobs, f.issued[m[1]])
		}
		if f.onDecline != nil {
			go f.onDecline(m[1], req.Reason)
		}
		f.json(w, 200, map[string]any{"proto": 1})
		return
	}
	if m := runActRe.FindStringSubmatch(path); m != nil && r.Method == http.MethodPost {
		var job *agentapi.Job
		for id, j := range f.issued {
			if j.RunID == m[1] && f.accepted[id] {
				job = j
			}
		}
		if job == nil { // TM-37
			f.fail(w, 404, "not_found")
			return
		}
		if _, done := f.finals[m[1]]; done && m[2] == "observations" {
			f.fail(w, 409, "run_final")
			return
		}
		raw, _ := io.ReadAll(io.LimitReader(r.Body, 3<<20))
		if m[2] == "final" {
			if prev, ok := f.finals[m[1]]; ok {
				if bytes.Equal(prev, raw) {
					f.json(w, 200, map[string]any{"proto": 1})
				} else {
					f.fail(w, 409, "run_final")
				}
				return
			}
			f.checkSchema("final.request.schema.json", raw)
			if f.finalFail != 0 {
				f.fail(w, f.finalFail, "level1_inconsistent")
				return
			}
			f.finals[m[1]] = raw
			if f.onFinal != nil {
				go f.onFinal(m[1])
			}
			f.json(w, 200, map[string]any{"proto": 1})
			return
		}
		r.Body = io.NopCloser(bytes.NewReader(raw))
		var o agentapi.Observations
		if !decode(r, &o, false) || o.Seq < 1 {
			f.fail(w, 422, "invalid")
			return
		}
		if f.obs[m[1]] == nil {
			f.obs[m[1]] = map[int]agentapi.Observations{}
		}
		f.obs[m[1]][o.Seq] = o
		f.json(w, 202, map[string]any{"proto": 1, "seq": o.Seq})
		return
	}
	f.fail(w, 404, "not_found")
}

func (f *fakeServer) nameTaken(name string) bool {
	for _, a := range f.agents {
		if a.revoked == "" && strings.EqualFold(a.name, name) {
			return true
		}
	}
	return false
}

func (f *fakeServer) serverKind() string {
	if f.kind == "" {
		return "selfhost"
	}
	return f.kind
}

// randID is the external id body: 26 Crockford base32 symbols, first 0-7.
func randID() string {
	const cf = "0123456789ABCDEFGHJKMNPQRSTVWXYZ"
	b := make([]byte, 26)
	_, _ = rand.Read(b)
	for i := range b {
		b[i] = cf[int(b[i])%32]
	}
	b[0] = cf[int(b[0])%8]
	return string(b)
}

func randAlnum(n int) string {
	const al = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"
	b := make([]byte, n)
	_, _ = rand.Read(b)
	for i := range b {
		b[i] = al[int(b[i])%len(al)]
	}
	return string(b)
}

// checkSchema validates a request body against A06's schema when the
// contracts directory exists (consumer-driven contract, A-12).
func (f *fakeServer) checkSchema(name string, raw []byte) {
	if f.schema == nil {
		return
	}
	errs, err := f.schema.Validate(name, raw)
	if err != nil {
		f.t.Errorf("schema %s: %v", name, err)
		return
	}
	for _, e := range errs {
		f.t.Errorf("%s: %s", name, e)
	}
}

// addCode registers a pairing code (normalised form).
func (f *fakeServer) addCode(code string) {
	f.mu.Lock()
	f.codes[code] = true
	f.mu.Unlock()
}

func (f *fakeServer) lock() func() {
	f.mu.Lock()
	return f.mu.Unlock
}
