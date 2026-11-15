package execute

import (
	"context"
	"sync"
	"time"

	"github.com/jiordiviera/webhooktrap/agent/observe"
)

// Clock is the only source of time in the engine (D-2): wall time for
// signature timestamps, monotonic time for latency and offsets.
type Clock interface {
	Wall() time.Time
	Mono() time.Duration
}

// Scheduler performs every wait: gaps, connect retries, settle polls, the
// async re-read and the stability check (D-4).
type Scheduler interface {
	Sleep(ctx context.Context, d time.Duration) error
}

// Request is one HTTP request, already signed.
type Request struct {
	N      int
	Method string
	Host   string // Host header value
	Path   string // path and query
	Header [][2]string
	Body   []byte
}

// Result is what the transport observed for one request.
type Result struct {
	Status      int
	Transport   string // "", timeout, reset
	Responded   bool
	Latency     time.Duration
	FirstByteAt time.Duration // Clock.Mono() when the first request byte was written
	Response    observe.Response
	Framework   string
	// Header is the response header, for live output and test seams. Never serialised.
	Header map[string][]string
}

// Transport opens connections to the pinned target (D-6).
type Transport interface {
	// Open connects once, sending no request bytes. The error is an
	// observe.Transport* unreachable kind wrapped in *UnreachableError.
	Open(ctx context.Context) (Conn, error)
}

// Conn sends exactly one request.
type Conn interface {
	Do(ctx context.Context, r *Request) Result
	Close() error
}

// UnreachableError is a connection that could not be established.
type UnreachableError struct{ Kind string }

func (e *UnreachableError) Error() string { return "unreachable: " + e.Kind }

// Prober reads the developer's probe (D-7). Values are one per entry, in
// configuration order, trailing whitespace trimmed.
type Prober interface {
	Names() []string
	Read(ctx context.Context) ([]string, error)
}

// Resetter runs the reset hook (PRB-9).
type Resetter interface {
	Reset(ctx context.Context) error
}

// SystemClock is the real clock adapter; the only place that reads time.Now.
type SystemClock struct{ origin time.Time }

// NewSystemClock starts a monotonic origin now.
func NewSystemClock() *SystemClock { return &SystemClock{origin: time.Now()} }

func (c *SystemClock) Wall() time.Time     { return time.Now().UTC() }
func (c *SystemClock) Mono() time.Duration { return time.Since(c.origin) }

// SystemScheduler sleeps on real timers.
type SystemScheduler struct{}

func (SystemScheduler) Sleep(ctx context.Context, d time.Duration) error {
	if d <= 0 {
		return ctx.Err()
	}
	t := time.NewTimer(d)
	defer t.Stop()
	select {
	case <-ctx.Done():
		return ctx.Err()
	case <-t.C:
		return nil
	}
}

// VirtualClock is a deterministic Clock and Scheduler for tests and goldens:
// time moves only when something sleeps or calls Advance.
type VirtualClock struct {
	mu    sync.Mutex
	wall0 time.Time
	now   time.Duration
}

// NewVirtualClock starts at wall time w.
func NewVirtualClock(w time.Time) *VirtualClock { return &VirtualClock{wall0: w.UTC()} }

func (v *VirtualClock) Wall() time.Time {
	v.mu.Lock()
	defer v.mu.Unlock()
	return v.wall0.Add(v.now)
}

func (v *VirtualClock) Mono() time.Duration {
	v.mu.Lock()
	defer v.mu.Unlock()
	return v.now
}

// Sleep advances virtual time.
func (v *VirtualClock) Sleep(ctx context.Context, d time.Duration) error {
	if err := ctx.Err(); err != nil {
		return err
	}
	v.Advance(d)
	return nil
}

// Advance moves virtual time forward.
func (v *VirtualClock) Advance(d time.Duration) {
	if d <= 0 {
		return
	}
	v.mu.Lock()
	v.now += d
	v.mu.Unlock()
}
