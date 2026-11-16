package sandbox

import (
	"bytes"
	"context"
	"encoding/base64"
	"encoding/binary"
	"encoding/json"
	"io"
	"net"
	"os"
	"path/filepath"
	"strings"
	"sync"
	"testing"
	"time"

	"github.com/jiordiviera/webhooktrap/agent/internal/fixture"
	"github.com/jiordiviera/webhooktrap/agent/internal/packdata"
	"github.com/jiordiviera/webhooktrap/agent/internal/version"
)

func stripeCapture(t *testing.T) ([][2]string, string) {
	t.Helper()
	abs, _ := filepath.Abs(filepath.Join("..", "..", "testdata", "fixtures", "stripe", "invoice.paid.json"))
	c, err := fixture.Load(abs)
	if err != nil {
		t.Fatal(err)
	}
	return c.Headers, base64.StdEncoding.EncodeToString(c.Body)
}

func frame(t *testing.T, v any) []byte {
	t.Helper()
	b, err := json.Marshal(v)
	if err != nil {
		t.Fatal(err)
	}
	return b
}

func TestHandle(t *testing.T) {
	src := packdata.Source()
	h, b64 := stripeCapture(t)
	planIn := PlanInput{Captures: []CaptureIn{{Ref: "cap_1", Order: 1, Headers: h, BodyBase64: b64}}, Scenarios: []string{"once", "twice", "tampered"}, Pack: PackRef{ID: "stripe"}}

	r := Handle(frame(t, Request{Op: OpPing}), src)
	if !r.OK || r.Version != version.Current().Version {
		t.Fatalf("ping: %+v", r)
	}
	r = Handle(frame(t, map[string]any{"op": "plan", "input": planIn}), src)
	if !r.OK || r.Preview == nil || r.Preview.DeliveryCount != 4 || !r.Preview.NeedsSecret || len(r.Preview.Scenarios) != 3 {
		t.Fatalf("plan: %+v", r)
	}
	again := Handle(frame(t, map[string]any{"op": "plan", "input": planIn}), src)
	if again.Preview.PlanHash != r.Preview.PlanHash {
		t.Fatal("plan is not deterministic")
	}
	r = Handle(frame(t, map[string]any{"op": "verify", "input": VerifyInput{Pack: PackRef{ID: "stripe"}, Headers: h, BodyBase64: b64, ReceivedAt: 1794405483}}), src)
	if !r.OK || r.Verdict == nil {
		t.Fatalf("verify: %+v", r)
	}

	for name, c := range map[string]struct {
		frame []byte
		code  string
	}{
		"not json":        {[]byte("{"), CodeBadFrame},
		"unknown field":   {[]byte(`{"op":"ping","x":1}`), CodeBadFrame},
		"unknown op":      {[]byte(`{"op":"execute"}`), CodeUnknownOp},
		"probe op":        {[]byte(`{"op":"probe"}`), CodeUnknownOp},
		"plan + secret":   {frame(t, map[string]any{"op": "plan", "input": planIn, "secret": SecretIn{Name: "S", ValueBase64: "eA=="}}), CodeBadInput},
		"ping + secret":   {frame(t, map[string]any{"op": "ping", "secret": SecretIn{Name: "S", ValueBase64: "eA=="}}), CodeBadInput},
		"plan no input":   {[]byte(`{"op":"plan"}`), CodeBadInput},
		"unknown pack":    {frame(t, map[string]any{"op": "plan", "input": PlanInput{Captures: planIn.Captures, Scenarios: []string{"once"}, Pack: PackRef{ID: "nope"}}}), CodeNoPack},
		"bad scenario":    {frame(t, map[string]any{"op": "plan", "input": PlanInput{Captures: planIn.Captures, Scenarios: []string{"nuke"}, Pack: PackRef{ID: "stripe"}}}), "unknown_scenario"},
		"bad secret name": {frame(t, map[string]any{"op": "verify", "input": VerifyInput{Headers: h, BodyBase64: b64, ReceivedAt: 1}, "secret": SecretIn{Name: "lower", ValueBase64: "eA=="}}), CodeBadInput},
	} {
		r := Handle(c.frame, src)
		if r.OK || r.Error == nil || r.Error.Code != c.code || r.Proto != Proto {
			t.Errorf("%s: %+v, want %s", name, r, c.code)
		}
	}
}

func TestRunChild(t *testing.T) {
	var out bytes.Buffer
	if code := RunChild(strings.NewReader(`{"op":"ping"}`), &out, packdata.Source()); code != 0 {
		t.Fatalf("exit %d", code)
	}
	if !strings.HasSuffix(out.String(), "\n") || strings.Count(out.String(), "\n") != 1 || !strings.Contains(out.String(), `"ok":true`) {
		t.Fatalf("reply %q", out.String())
	}
}

// inProcess runs Handle in the server's process: the server logic without
// the re-exec (cmd/webhooktrap tests the real child).
func inProcess(ctx context.Context, stdin io.Reader, stdout io.Writer) error {
	RunChild(stdin, stdout, packdata.Source())
	return nil
}

func startServer(t *testing.T, spawn Spawner, conc int, timeout time.Duration) (string, *syncBuf) {
	t.Helper()
	dir, err := os.MkdirTemp("", "wtsb")
	if err != nil {
		t.Fatal(err)
	}
	t.Cleanup(func() { _ = os.RemoveAll(dir) })
	path := filepath.Join(dir, "e.sock")
	var log syncBuf
	s := &Server{Path: path, Spawn: spawn, Concurrency: conc, Timeout: timeout, Log: &log}
	ln, err := s.Listen()
	if err != nil {
		t.Fatal(err)
	}
	ctx, cancel := context.WithCancel(context.Background())
	done := make(chan struct{})
	go func() { _ = s.Serve(ctx, ln); close(done) }()
	t.Cleanup(func() { cancel(); <-done })
	return path, &log
}

type syncBuf struct {
	mu sync.Mutex
	b  bytes.Buffer
}

func (s *syncBuf) Write(p []byte) (int, error) {
	s.mu.Lock()
	defer s.mu.Unlock()
	return s.b.Write(p)
}

// String waits briefly for a log line: the server logs after it replies.
func (s *syncBuf) String() string {
	for i := 0; i < 50; i++ {
		s.mu.Lock()
		out := s.b.String()
		s.mu.Unlock()
		if strings.Count(out, "\n") >= 2 {
			return out
		}
		time.Sleep(10 * time.Millisecond)
	}
	s.mu.Lock()
	defer s.mu.Unlock()
	return s.b.String()
}

func TestServerCalls(t *testing.T) {
	path, log := startServer(t, inProcess, 2, time.Second)
	fi, err := os.Stat(path)
	if err != nil || fi.Mode().Perm() != 0o660 {
		t.Fatalf("socket mode %v, %v", fi.Mode(), err)
	}
	r, err := Call(context.Background(), path, Request{Op: OpPing})
	if err != nil || !r.OK {
		t.Fatalf("ping: %+v %v", r, err)
	}
	h, b64 := stripeCapture(t)
	secret := "whsec_never_logged_value"
	r, err = Call(context.Background(), path, Request{Op: OpVerify,
		Input:  frame(t, VerifyInput{Pack: PackRef{ID: "stripe"}, Headers: h, BodyBase64: b64, ReceivedAt: 1794405483}),
		Secret: &SecretIn{Name: "STRIPE_WEBHOOK_SECRET", ValueBase64: base64.StdEncoding.EncodeToString([]byte(secret))}})
	if err != nil || !r.OK || r.Verdict == nil {
		t.Fatalf("verify: %+v %v", r, err)
	}
	if strings.Contains(log.String(), secret) || strings.Contains(log.String(), "invoice") {
		t.Fatalf("log carries input: %s", log.String())
	}
	if !strings.Contains(log.String(), "op=verify outcome=ok") {
		t.Fatalf("log: %s", log.String())
	}
}

func rawCall(t *testing.T, path string, prefix uint32, body []byte) Reply {
	t.Helper()
	c, err := net.Dial("unix", path)
	if err != nil {
		t.Fatal(err)
	}
	defer c.Close()
	_ = c.SetDeadline(time.Now().Add(5 * time.Second))
	var l [4]byte
	binary.BigEndian.PutUint32(l[:], prefix)
	_, _ = c.Write(append(l[:], body...))
	var r Reply
	line, _ := io.ReadAll(c)
	if err := json.Unmarshal(line, &r); err != nil {
		t.Fatalf("reply %q: %v", line, err)
	}
	return r
}

func TestServerLimits(t *testing.T) {
	path, _ := startServer(t, inProcess, 1, time.Second)
	if r := rawCall(t, path, MaxFrame+1, nil); r.Error == nil || r.Error.Code != CodeFrameTooLarge {
		t.Fatalf("oversized frame: %+v", r)
	}
	if r := rawCall(t, path, 0, nil); r.Error == nil || r.Error.Code != CodeBadFrame {
		t.Fatalf("empty frame: %+v", r)
	}

	// A child that never answers is killed at the deadline.
	hang := func(ctx context.Context, _ io.Reader, _ io.Writer) error { <-ctx.Done(); return ctx.Err() }
	path, _ = startServer(t, hang, 1, 100*time.Millisecond)
	if r, _ := Call(context.Background(), path, Request{Op: OpPlan}); r.Error == nil || r.Error.Code != CodeTimeout {
		t.Fatalf("timeout: %+v", r)
	}

	// Over the concurrency cap: busy at once, never queued; ping has its own slot.
	release := make(chan struct{})
	block := func(ctx context.Context, in io.Reader, out io.Writer) error {
		var head struct{ Op string }
		b, _ := io.ReadAll(in)
		_ = json.Unmarshal(b, &head)
		if head.Op == OpPlan {
			select {
			case <-release:
			case <-ctx.Done():
			}
		}
		RunChild(bytes.NewReader(b), out, packdata.Source())
		return nil
	}
	path, _ = startServer(t, block, 1, 5*time.Second)
	go func() { _, _ = Call(context.Background(), path, Request{Op: OpPlan}) }()
	time.Sleep(100 * time.Millisecond)
	if r, _ := Call(context.Background(), path, Request{Op: OpVerify}); r.Error == nil || r.Error.Code != CodeBusy {
		t.Fatalf("busy: %+v", r)
	}
	if r, err := Call(context.Background(), path, Request{Op: OpPing}); err != nil || !r.OK {
		t.Fatalf("ping while busy: %+v %v", r, err)
	}
	close(release)

	// A child that writes garbage, or two lines, has not answered.
	for _, out := range []string{"not json\n", `{"proto":1,"op":"ping","ok":true}` + "\n" + `{"proto":1}` + "\n", `{"proto":2,"ok":true}` + "\n"} {
		bad := func(_ context.Context, _ io.Reader, w io.Writer) error { _, _ = io.WriteString(w, out); return nil }
		path, _ = startServer(t, bad, 1, time.Second)
		if r, _ := Call(context.Background(), path, Request{Op: OpPing}); r.Error == nil || r.Error.Code != CodeInternal {
			t.Fatalf("child output %q: %+v", out, r)
		}
	}
}

func TestListen(t *testing.T) {
	dir, err := os.MkdirTemp("", "wtsb")
	if err != nil {
		t.Fatal(err)
	}
	defer os.RemoveAll(dir)
	path := filepath.Join(dir, "e.sock")
	// A regular file at the path is never removed.
	_ = os.WriteFile(path, nil, 0o600)
	if _, err := (&Server{Path: path}).Listen(); err == nil {
		t.Fatal("listened over a regular file")
	}
	_ = os.Remove(path)
	// A stale socket (nobody listening) is replaced.
	ln, err := net.Listen("unix", path)
	if err != nil {
		t.Fatal(err)
	}
	ln.(*net.UnixListener).SetUnlinkOnClose(false)
	_ = ln.Close()
	ln2, err := (&Server{Path: path}).Listen()
	if err != nil {
		t.Fatalf("stale socket: %v", err)
	}
	// A live one is refused.
	if _, err := (&Server{Path: path}).Listen(); err == nil {
		t.Fatal("listened over a live sandbox")
	}
	_ = ln2.Close()
}
