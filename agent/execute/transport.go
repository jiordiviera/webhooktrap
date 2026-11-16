package execute

import (
	"bufio"
	"bytes"
	"context"
	"crypto/sha256"
	"crypto/tls"
	"encoding/hex"
	"errors"
	"io"
	"net"
	"net/http"
	"strconv"
	"strings"
	"time"

	"github.com/jiordiviera/webhooktrap/agent/guard"
	"github.com/jiordiviera/webhooktrap/agent/observe"
)

// Limits (ADR-010 §7, TGT-5).
const (
	RequestTimeout  = 30 * time.Second
	ResponseCap     = 1 << 20 // bytes read and hashed per response
	dialTimeout     = 5 * time.Second
	maxHeaderSize   = 64 << 10
	connectRetry    = 500 * time.Millisecond
	connectRetryFor = 5 * time.Second
)

// HTTPTransport speaks HTTP/1.1 directly over a guarded connection: one
// connection per delivery, opened before the request (so Burst can open all
// of them before the barrier, BURST-2), no proxy, no redirects (TGT-4), pinned
// addresses (TGT-5).
type HTTPTransport struct {
	Target *guard.Target
	Clock  Clock
	// InsecureLocalhost skips TLS verification for local targets only (TGT-3).
	InsecureLocalhost bool
	// Timeout and ResponseCap narrow RequestTimeout and ResponseCap (a
	// runner job's limits, contracts §6); zero or larger values keep the
	// engine's own limits.
	Timeout     time.Duration
	ResponseCap int64
}

// Open implements Transport.
func (t *HTTPTransport) Open(ctx context.Context) (Conn, error) {
	d := &net.Dialer{Timeout: dialTimeout}
	c, err := t.Target.Dial(ctx, d)
	if err != nil {
		var ge *guard.Error
		if errors.As(err, &ge) {
			return nil, err
		}
		return nil, &UnreachableError{Kind: observe.TransportRefused}
	}
	if t.Target.Scheme == "https" {
		tc := tls.Client(c, &tls.Config{
			ServerName:         t.Target.Host,
			InsecureSkipVerify: t.InsecureLocalhost && t.Target.Local, //nolint:gosec // TGT-3, local only
			MinVersion:         tls.VersionTLS12,
		})
		_ = tc.SetDeadline(time.Now().Add(dialTimeout))
		if err := tc.HandshakeContext(ctx); err != nil {
			_ = c.Close()
			return nil, &UnreachableError{Kind: observe.TransportTLS}
		}
		_ = tc.SetDeadline(time.Time{})
		c = tc
	}
	hc := &httpConn{c: c, clock: t.Clock, timeout: RequestTimeout, cap: ResponseCap}
	if t.Timeout > 0 && t.Timeout < RequestTimeout {
		hc.timeout = t.Timeout
	}
	if t.ResponseCap > 0 && t.ResponseCap < ResponseCap {
		hc.cap = t.ResponseCap
	}
	return hc, nil
}

type httpConn struct {
	c       net.Conn
	clock   Clock
	timeout time.Duration
	cap     int64
}

func (h *httpConn) Close() error { return h.c.Close() }

// Do writes the request and reads the response headers, then up to
// ResponseCap bytes of body, which are hashed and dropped.
func (h *httpConn) Do(ctx context.Context, r *Request) Result {
	var res Result
	raw := encode(r)
	// Socket deadlines are an OS timeout, not a measured time: real time is right here.
	_ = h.c.SetDeadline(time.Now().Add(h.timeout))
	stop := context.AfterFunc(ctx, func() { _ = h.c.SetDeadline(time.Unix(1, 0)) })
	defer stop()

	res.FirstByteAt = h.clock.Mono()
	if _, err := h.c.Write(raw); err != nil {
		res.Transport = classify(err)
		return res
	}
	br := bufio.NewReaderSize(h.c, 4096)
	resp, err := http.ReadResponse(br, &http.Request{Method: r.Method})
	if err != nil {
		res.Transport = classify(err)
		return res
	}
	defer resp.Body.Close()
	res.Latency = h.clock.Mono() - res.FirstByteAt
	res.Status = resp.StatusCode
	res.Responded = true
	res.Framework = Framework(resp.Header)
	res.Header = resp.Header
	hash := sha256.New()
	n, _ := io.Copy(hash, io.LimitReader(resp.Body, h.cap))
	res.Response = observe.Response{
		ContentType: resp.Header.Get("Content-Type"),
		BodySHA256:  hex.EncodeToString(hash.Sum(nil)),
		BodyBytes:   n,
	}
	return res
}

func classify(err error) string {
	var ne net.Error
	if errors.As(err, &ne) && ne.Timeout() {
		return observe.TransportTimeout
	}
	return observe.TransportReset
}

// encode writes an HTTP/1.1 request. Host and Content-Length are always
// recomputed (TM-51); the connection closes after one exchange.
func encode(r *Request) []byte {
	var b bytes.Buffer
	b.WriteString(r.Method + " " + r.Path + " HTTP/1.1\r\n")
	b.WriteString("Host: " + r.Host + "\r\n")
	for _, kv := range r.Header {
		if strings.ContainsAny(kv[0]+kv[1], "\r\n") {
			continue // never let a captured value split a header
		}
		b.WriteString(kv[0] + ": " + kv[1] + "\r\n")
	}
	b.WriteString("Content-Length: " + strconv.Itoa(len(r.Body)) + "\r\n")
	b.WriteString("Connection: close\r\n\r\n")
	b.Write(r.Body)
	return b.Bytes()
}

// Framework detects the handler's framework from response headers (FND-9).
func Framework(h http.Header) string {
	pb := h.Get("X-Powered-By")
	switch {
	case strings.EqualFold(pb, "Express"):
		return "express"
	case strings.Contains(pb, "Next.js"):
		return "nextjs"
	case strings.Contains(pb, "Laravel"):
		return "laravel"
	}
	for _, c := range h.Values("Set-Cookie") {
		if strings.HasPrefix(strings.TrimSpace(c), "laravel_session=") {
			return "laravel"
		}
	}
	return ""
}
