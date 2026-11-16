package agentapi

import (
	"bytes"
	"context"
	"crypto/ed25519"
	"crypto/tls"
	"encoding/base64"
	"encoding/binary"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"net/http"
	"net/url"
	"strconv"
	"strings"
	"sync"
	"time"
)

// Response size limits per endpoint family (contracts §5: every endpoint has
// one). Capture bodies are ≤ 5 MiB raw, so the base64 JSON envelope gets 8 MiB.
const (
	maxSmallResponse   = 64 << 10
	maxJobResponse     = 256 << 10
	maxCaptureResponse = 8 << 20
	// MaxFinalDocument is the run document cap (contracts §5.7).
	MaxFinalDocument = 2 << 20
)

// refreshBefore is how long before expiry the access token is renewed
// (tokens last 15 minutes, contracts §5.2).
const refreshBefore = 2 * time.Minute

// ErrRedirect is returned when the server answers with a redirect: the agent
// follows none (S-4).
var ErrRedirect = errors.New("the server answered with a redirect; the agent follows no redirects (S-4)")

// APIError is a non-2xx answer.
type APIError struct {
	Status          int
	Code            string // the "error" field
	Reason          string // the "reason" field (revoked)
	MinAgentVersion string
	RetryAfter      time.Duration
}

func (e *APIError) Error() string {
	s := "server answered " + strconv.Itoa(e.Status)
	if e.Code != "" {
		s += " " + e.Code
	}
	if e.Reason != "" {
		s += " (" + e.Reason + ")"
	}
	return s
}

// IsCode reports whether err is an APIError with that "error" code.
func IsCode(err error, code string) bool {
	var ae *APIError
	return errors.As(err, &ae) && ae.Code == code
}

// Revoked reports a revoked agent (401 revoked): the local identity is dead.
func Revoked(err error) (string, bool) {
	var ae *APIError
	if errors.As(err, &ae) && ae.Status == http.StatusUnauthorized && ae.Code == "revoked" {
		return ae.Reason, true
	}
	return "", false
}

// NewHTTPClient is the production HTTP client: system roots, TLS 1.2+, no
// redirects, bounded time, and no proxy for a local development origin.
func NewHTTPClient() *http.Client {
	tr := http.DefaultTransport.(*http.Transport).Clone()
	tr.TLSClientConfig = &tls.Config{MinVersion: tls.VersionTLS12}
	tr.Proxy = DevAwareProxy(http.ProxyFromEnvironment)
	return &http.Client{Transport: tr, Timeout: 90 * time.Second}
}

// DevAwareProxy wraps a proxy function so that a local development origin
// (DevHost: localhost, *.localhost, *.test, loopback) is always dialled
// directly: HTTP_PROXY must not see a plain-http dev origin (AC-15).
func DevAwareProxy(next func(*http.Request) (*url.URL, error)) func(*http.Request) (*url.URL, error) {
	return func(r *http.Request) (*url.URL, error) {
		if r.URL != nil && DevHost(r.URL.Hostname()) {
			return nil, nil
		}
		if next == nil {
			return nil, nil
		}
		return next(r)
	}
}

// Client talks to one pinned origin.
type Client struct {
	Origin    string // canonical, pinned at pairing
	HTTP      *http.Client
	UserAgent string
	Now       func() time.Time

	AgentID string
	Key     ed25519.PrivateKey

	// Base is the API base path: /agent/v1 (default) or /runner/v1.
	Base string
	// Bearer is a static token (the runner token, contracts §6). When set,
	// no challenge-response runs and a 401 is never retried.
	Bearer string

	authMu   sync.Mutex // one challenge-response at a time
	mu       sync.Mutex
	token    string
	tokenExp time.Time
}

// New returns a client for origin. hc may be nil for the production client.
func New(origin string, hc *http.Client, userAgent string, now func() time.Time) *Client {
	if hc == nil {
		hc = NewHTTPClient()
	}
	// Never follow a redirect, whatever client was passed in.
	c := *hc
	c.CheckRedirect = func(*http.Request, []*http.Request) error { return http.ErrUseLastResponse }
	return &Client{Origin: origin, HTTP: &c, UserAgent: userAgent, Now: now}
}

type callOpts struct {
	auth    bool
	limit   int64
	query   url.Values
	noRetry bool
}

// call sends one request to the pinned origin and decodes a 2xx JSON body
// into out. It returns (status, error); 204 leaves out untouched.
func (c *Client) call(ctx context.Context, method, path string, in, out any, o callOpts) (int, error) {
	if !strings.HasPrefix(path, c.base()+"/") {
		return 0, errors.New("agentapi: path outside " + c.base())
	}
	u, err := url.Parse(c.Origin + path)
	if err != nil {
		return 0, err
	}
	if o.query != nil {
		u.RawQuery = o.query.Encode()
	}
	var body []byte
	if in != nil {
		if body, err = json.Marshal(in); err != nil {
			return 0, err
		}
	}
	for attempt := 0; ; attempt++ {
		req, err := http.NewRequestWithContext(ctx, method, u.String(), bytes.NewReader(body))
		if err != nil {
			return 0, err
		}
		if in != nil {
			req.Header.Set("Content-Type", "application/json")
		}
		req.Header.Set("Accept", "application/json")
		req.Header.Set("User-Agent", c.UserAgent)
		if o.auth {
			tok, err := c.accessToken(ctx)
			if err != nil {
				return 0, err
			}
			req.Header.Set("Authorization", "Bearer "+tok)
		}
		resp, err := c.HTTP.Do(req)
		if err != nil {
			return 0, scrubURLError(err)
		}
		status, err := c.read(resp, out, o.limit)
		// One retry with a fresh token when the server says the token is no
		// longer valid but the agent isn't revoked.
		var ae *APIError
		if o.auth && !o.noRetry && c.Bearer == "" && attempt == 0 && errors.As(err, &ae) && ae.Status == http.StatusUnauthorized && ae.Code != "revoked" {
			c.dropToken()
			continue
		}
		return status, err
	}
}

func (c *Client) read(resp *http.Response, out any, limit int64) (int, error) {
	defer resp.Body.Close()
	if limit <= 0 {
		limit = maxSmallResponse
	}
	if resp.StatusCode >= 300 && resp.StatusCode < 400 {
		_, _ = io.Copy(io.Discard, io.LimitReader(resp.Body, 4096))
		return resp.StatusCode, ErrRedirect
	}
	raw, err := io.ReadAll(io.LimitReader(resp.Body, limit+1))
	if err != nil {
		return resp.StatusCode, fmt.Errorf("reading the server's answer: %w", err)
	}
	if int64(len(raw)) > limit {
		return resp.StatusCode, fmt.Errorf("the server's answer is larger than %d bytes", limit)
	}
	if resp.StatusCode < 200 || resp.StatusCode >= 300 {
		ae := &APIError{Status: resp.StatusCode}
		var eb struct {
			Error           string `json:"error"`
			Reason          string `json:"reason"`
			MinAgentVersion string `json:"min_agent_version"`
		}
		if json.Unmarshal(raw, &eb) == nil {
			ae.Code, ae.Reason, ae.MinAgentVersion = eb.Error, eb.Reason, eb.MinAgentVersion
		}
		if ra := resp.Header.Get("Retry-After"); ra != "" {
			if s, err := strconv.Atoi(strings.TrimSpace(ra)); err == nil && s >= 0 {
				ae.RetryAfter = time.Duration(s) * time.Second
			} else if t, err := http.ParseTime(ra); err == nil && c.Now != nil {
				ae.RetryAfter = max(0, t.Sub(c.Now()))
			}
		}
		return resp.StatusCode, ae
	}
	if resp.StatusCode == http.StatusNoContent || out == nil {
		return resp.StatusCode, nil
	}
	var p struct {
		Proto *int `json:"proto"`
	}
	if err := json.Unmarshal(raw, &p); err != nil {
		return resp.StatusCode, errors.New("the server's answer is not JSON")
	}
	if p.Proto == nil || *p.Proto != Proto {
		return resp.StatusCode, errors.New(`the server's answer doesn't carry "proto": 1`)
	}
	if err := json.Unmarshal(raw, out); err != nil {
		return resp.StatusCode, errors.New("the server's answer doesn't match the protocol")
	}
	return resp.StatusCode, nil
}

// scrubURLError keeps transport errors free of query strings (never a token,
// but the rule is cheap).
func scrubURLError(err error) error {
	var ue *url.Error
	if errors.As(err, &ue) {
		if u, perr := url.Parse(ue.URL); perr == nil {
			u.RawQuery = ""
			return fmt.Errorf("%s %s: %w", ue.Op, u.String(), ue.Err)
		}
	}
	return err
}

// ---- authentication (contracts §5.2) ----

// AuthMessage is the signed byte string: each field length-prefixed with a
// 2-byte big-endian length.
func AuthMessage(origin, agentID string, nonce []byte, expiresAt string) []byte {
	var b bytes.Buffer
	for _, f := range [][]byte{[]byte("webhooktrap-agent-auth/v1"), []byte(origin), []byte(agentID), nonce, []byte(expiresAt)} {
		var l [2]byte
		binary.BigEndian.PutUint16(l[:], uint16(len(f)))
		b.Write(l[:])
		b.Write(f)
	}
	return b.Bytes()
}

func (c *Client) dropToken() {
	c.mu.Lock()
	c.token, c.tokenExp = "", time.Time{}
	c.mu.Unlock()
}

// base is the API base path.
func (c *Client) base() string {
	if c.Base == "" {
		return "/agent/v1"
	}
	return c.Base
}

// accessToken returns a valid token, renewing it before expiry.
func (c *Client) accessToken(ctx context.Context) (string, error) {
	if c.Bearer != "" {
		return c.Bearer, nil
	}
	c.mu.Lock()
	tok, exp := c.token, c.tokenExp
	c.mu.Unlock()
	if tok != "" && c.Now().Before(exp.Add(-refreshBefore)) {
		return tok, nil
	}
	c.authMu.Lock()
	defer c.authMu.Unlock()
	c.mu.Lock()
	tok, exp = c.token, c.tokenExp
	c.mu.Unlock()
	if tok != "" && c.Now().Before(exp.Add(-refreshBefore)) {
		return tok, nil // another caller renewed it meanwhile
	}
	if err := c.authenticate(ctx); err != nil {
		return "", err
	}
	c.mu.Lock()
	defer c.mu.Unlock()
	return c.token, nil
}

// Authenticate runs challenge-response and stores a fresh access token.
func (c *Client) Authenticate(ctx context.Context) error {
	c.authMu.Lock()
	defer c.authMu.Unlock()
	return c.authenticate(ctx)
}

func (c *Client) authenticate(ctx context.Context) error {
	if c.AgentID == "" || len(c.Key) != ed25519.PrivateKeySize {
		return errors.New("agentapi: no agent identity")
	}
	var ch challengeResponse
	if _, err := c.call(ctx, http.MethodPost, "/agent/v1/auth/challenge", challengeRequest{Proto, c.AgentID}, &ch, callOpts{}); err != nil {
		return err
	}
	nonce, err := base64.StdEncoding.DecodeString(ch.Nonce)
	if err != nil || len(nonce) != 32 {
		return errors.New("the server's challenge nonce is not 32 bytes of base64")
	}
	if _, err := time.Parse(time.RFC3339, ch.ExpiresAt); err != nil {
		return errors.New("the server's challenge has no valid expires_at")
	}
	sig := ed25519.Sign(c.Key, AuthMessage(c.Origin, c.AgentID, nonce, ch.ExpiresAt))
	var tr tokenResponse
	if _, err := c.call(ctx, http.MethodPost, "/agent/v1/auth/token", tokenRequest{Proto, c.AgentID, ch.Nonce, base64.StdEncoding.EncodeToString(sig)}, &tr, callOpts{}); err != nil {
		return err
	}
	exp, err := time.Parse(time.RFC3339, tr.ExpiresAt)
	if tr.AccessToken == "" || err != nil {
		return errors.New("the server's token answer is incomplete")
	}
	c.mu.Lock()
	c.token, c.tokenExp = tr.AccessToken, exp
	c.mu.Unlock()
	return nil
}

// TokenExpiry is the current token's expiry (zero when none). For tests.
func (c *Client) TokenExpiry() time.Time {
	c.mu.Lock()
	defer c.mu.Unlock()
	return c.tokenExp
}

// ---- endpoints ----

// Pair sends the pairing code with the public key (no auth).
func (c *Client) Pair(ctx context.Context, r PairRequest) (*PairResponse, error) {
	r.Proto, r.ProtoMin, r.ProtoMax = Proto, Proto, Proto
	var out PairResponse
	status, err := c.call(ctx, http.MethodPost, c.base()+"/pair", r, &out, callOpts{})
	if err != nil {
		return nil, err
	}
	if status != http.StatusCreated && status != http.StatusOK {
		return nil, fmt.Errorf("unexpected pair status %d", status)
	}
	return &out, nil
}

// Confirm clears needs_reconfirm after the local [y/N].
func (c *Client) Confirm(ctx context.Context) error {
	_, err := c.call(ctx, http.MethodPost, c.base()+"/confirm", protoOnly{Proto}, nil, callOpts{auth: true})
	return err
}

// Unpair is DELETE /agent/v1/agent.
func (c *Client) Unpair(ctx context.Context) error {
	_, err := c.call(ctx, http.MethodDelete, c.base()+"/agent", nil, nil, callOpts{auth: true})
	return err
}

// Hello reports the mirrors (names, declared targets, probe hash).
func (c *Client) Hello(ctx context.Context, h Hello) (*HelloResponse, error) {
	h.Proto, h.ProtoMin, h.ProtoMax = Proto, Proto, Proto
	if h.Secrets == nil {
		h.Secrets = []SecretName{}
	}
	if h.Targets == nil {
		h.Targets = []TargetMirror{}
	}
	var out HelloResponse
	if _, err := c.call(ctx, http.MethodPost, c.base()+"/hello", h, &out, callOpts{auth: true}); err != nil {
		return nil, err
	}
	return &out, nil
}

// NewRunner returns a client for the runner API (/runner/v1) that sends the
// runner token on every call (contracts §6). The token is never logged.
func NewRunner(origin, token string, hc *http.Client, userAgent string, now func() time.Time) *Client {
	c := New(origin, hc, userAgent, now)
	c.Base, c.Bearer = "/runner/v1", token
	return c
}

// HelloRunner is the runner's hello and heartbeat (426 when its version
// differs from the app's).
func (c *Client) HelloRunner(ctx context.Context, version string) error {
	_, err := c.call(ctx, http.MethodPost, c.base()+"/hello", RunnerHello{Proto, version, Proto, Proto}, nil, callOpts{auth: true})
	return err
}

// NextJob long-polls for a job; (nil, nil) on 204.
func (c *Client) NextJob(ctx context.Context, wait int) (*Job, error) {
	var out Job
	status, err := c.call(ctx, http.MethodGet, c.base()+"/jobs/next", nil, &out, callOpts{auth: true, limit: maxJobResponse, query: url.Values{"wait": {strconv.Itoa(wait)}}})
	if err != nil {
		return nil, err
	}
	if status == http.StatusNoContent {
		return nil, nil
	}
	return &out, nil
}

// FetchCapture fetches one capture referenced by the job.
func (c *Client) FetchCapture(ctx context.Context, jobID, captureID string) (*CaptureRequest, error) {
	var out CaptureRequest
	p := c.base() + "/jobs/" + url.PathEscape(jobID) + "/captures/" + url.PathEscape(captureID) + "/request"
	if _, err := c.call(ctx, http.MethodGet, p, nil, &out, callOpts{auth: true, limit: maxCaptureResponse}); err != nil {
		return nil, err
	}
	return &out, nil
}

// Accept takes the job.
func (c *Client) Accept(ctx context.Context, jobID string) error {
	_, err := c.call(ctx, http.MethodPost, c.base()+"/jobs/"+url.PathEscape(jobID)+"/accept", protoOnly{Proto}, nil, callOpts{auth: true})
	return err
}

// Decline refuses the job with one of the Decline* reasons.
func (c *Client) Decline(ctx context.Context, jobID, reason string) error {
	_, err := c.call(ctx, http.MethodPost, c.base()+"/jobs/"+url.PathEscape(jobID)+"/decline", declineRequest{Proto, reason}, nil, callOpts{auth: true})
	return err
}

// Abort ends an accepted run job the agent cannot finish (agent connection
// notes): the run becomes Aborted with one of the Abort* reasons. 409 means
// the job wasn't accepted or is already finished.
func (c *Client) Abort(ctx context.Context, jobID, reason string) error {
	_, err := c.call(ctx, http.MethodPost, c.base()+"/jobs/"+url.PathEscape(jobID)+"/abort", declineRequest{Proto, reason}, nil, callOpts{auth: true})
	return err
}

// PostObservations sends one batch (idempotent on seq).
func (c *Client) PostObservations(ctx context.Context, runID string, o Observations) error {
	o.Proto = Proto
	_, err := c.call(ctx, http.MethodPost, c.base()+"/runs/"+url.PathEscape(runID)+"/observations", o, nil, callOpts{auth: true})
	return err
}

// PostFinal sends the hosted-profile run document (first write wins).
func (c *Client) PostFinal(ctx context.Context, runID string, doc json.RawMessage) error {
	if len(doc) > MaxFinalDocument {
		return fmt.Errorf("the run document is larger than %d bytes", MaxFinalDocument)
	}
	_, err := c.call(ctx, http.MethodPost, c.base()+"/runs/"+url.PathEscape(runID)+"/final", doc, nil, callOpts{auth: true})
	return err
}
