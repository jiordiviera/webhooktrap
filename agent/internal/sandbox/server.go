package sandbox

import (
	"bytes"
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"io/fs"
	"net"
	"os"
	"os/exec"
	"sync"
	"sync/atomic"
	"time"
)

// Spawner runs one call in a fresh child: the frame on stdin, the reply on
// stdout. It must return when ctx ends (the child is killed).
type Spawner func(ctx context.Context, stdin io.Reader, stdout io.Writer) error

// ExecSpawner re-executes the engine binary per call (ENG-2): an empty
// environment (GOMAXPROCS=1 only), working directory "/", stderr dropped,
// killed at the deadline.
func ExecSpawner(path string, args ...string) Spawner {
	return func(ctx context.Context, stdin io.Reader, stdout io.Writer) error {
		cmd := exec.CommandContext(ctx, path, args...)
		cmd.Stdin, cmd.Stdout, cmd.Stderr = stdin, stdout, nil
		cmd.Env = []string{"GOMAXPROCS=1", "GOTRACEBACK=none"}
		cmd.Dir = "/"
		cmd.WaitDelay = time.Second
		return cmd.Run()
	}
}

// Server is `serve-sandbox`.
type Server struct {
	Path        string
	Spawn       Spawner
	Concurrency int           // plan/verify calls at once; ping has one slot of its own
	Timeout     time.Duration // wall clock per call
	Log         io.Writer

	slots, pingSlot chan struct{}
	calls           atomic.Uint64
	wg              sync.WaitGroup
}

// Listen creates the socket. A stale socket left by a stopped engine is
// removed; a live one, or any other file at the path, is an error.
func (s *Server) Listen() (net.Listener, error) {
	if fi, err := os.Lstat(s.Path); err == nil {
		if fi.Mode().Type() != fs.ModeSocket {
			return nil, fmt.Errorf("%s exists and is not a socket", s.Path)
		}
		if c, err := net.DialTimeout("unix", s.Path, 200*time.Millisecond); err == nil {
			_ = c.Close()
			return nil, fmt.Errorf("another sandbox is listening on %s", s.Path)
		}
		if err := os.Remove(s.Path); err != nil {
			return nil, fmt.Errorf("removing the stale socket: %w", err)
		}
	}
	ln, err := listenUnix(s.Path)
	if err != nil {
		return nil, err
	}
	if err := os.Chmod(s.Path, 0o660); err != nil {
		_ = ln.Close()
		return nil, err
	}
	return ln, nil
}

// Serve accepts calls until ctx ends, then waits for the calls in flight
// (each is bounded by Timeout).
func (s *Server) Serve(ctx context.Context, ln net.Listener) error {
	if s.Concurrency <= 0 {
		s.Concurrency = DefaultConcurrency
	}
	if s.Timeout <= 0 {
		s.Timeout = CallTimeout
	}
	if s.Log == nil {
		s.Log = io.Discard
	}
	s.slots, s.pingSlot = make(chan struct{}, s.Concurrency), make(chan struct{}, 1)
	stop := context.AfterFunc(ctx, func() { _ = ln.Close() })
	defer stop()
	for {
		c, err := ln.Accept()
		if err != nil {
			if ctx.Err() != nil {
				s.wg.Wait()
				return nil
			}
			var ne net.Error
			if errors.As(err, &ne) && ne.Timeout() {
				time.Sleep(50 * time.Millisecond)
				continue
			}
			s.wg.Wait()
			return err
		}
		s.wg.Add(1)
		go func() {
			defer s.wg.Done()
			s.handle(c)
		}()
	}
}

// handle runs one call. The frame (which may hold a verify secret) is held
// in one buffer, zeroed once the child has read it; it is never logged.
func (s *Server) handle(c net.Conn) {
	start := time.Now()
	id := s.calls.Add(1)
	op, outcome := "-", ""
	defer func() {
		_ = c.Close()
		fmt.Fprintf(s.Log, "sandbox call=%d op=%s outcome=%s ms=%d\n", id, op, outcome, time.Since(start).Milliseconds())
	}()
	_ = c.SetDeadline(start.Add(s.Timeout + 2*time.Second))
	reply := func(r Reply) {
		if r.Op == "" && op != "-" {
			r.Op = op
		}
		outcome = r.Error.Code
		_, _ = c.Write(encodeReply(r))
	}

	n, err := ReadFrameLen(c, MaxFrame)
	if err != nil {
		if errors.Is(err, ErrFrameTooLarge) {
			reply(errorReply("", CodeFrameTooLarge))
		} else {
			reply(errorReply("", CodeBadFrame))
		}
		return
	}
	buf := make([]byte, n)
	defer clear(buf)
	if _, err := io.ReadFull(c, buf); err != nil {
		reply(errorReply("", CodeBadFrame))
		return
	}
	var head struct {
		Op string `json:"op"`
	}
	_ = json.Unmarshal(buf, &head)
	switch head.Op {
	case OpPlan, OpVerify, OpPing:
		op = head.Op
	}

	slot := s.slots
	if op == OpPing {
		slot = s.pingSlot
	}
	select {
	case slot <- struct{}{}:
		defer func() { <-slot }()
	default: // refused, not queued (ENG-2)
		reply(errorReply("", CodeBusy))
		return
	}

	ctx, cancel := context.WithTimeout(context.Background(), s.Timeout)
	defer cancel()
	out := &capped{max: MaxReply}
	err = s.Spawn(ctx, bytes.NewReader(buf), out)
	clear(buf)
	if ctx.Err() != nil {
		reply(errorReply("", CodeTimeout))
		return
	}
	line := bytes.TrimRight(out.b.Bytes(), "\n")
	var r Reply
	if out.over || len(line) == 0 || bytes.IndexByte(line, '\n') >= 0 || json.Unmarshal(line, &r) != nil || r.Proto != Proto {
		reply(errorReply("", CodeInternal))
		return
	}
	if err != nil && r.OK { // a child that exits non-zero has not answered
		reply(errorReply("", CodeInternal))
		return
	}
	outcome = "ok"
	if r.Error != nil {
		outcome = r.Error.Code
	}
	_, _ = c.Write(append(line, '\n'))
}

// capped is a bounded reply buffer.
type capped struct {
	b    bytes.Buffer
	max  int
	over bool
}

func (w *capped) Write(p []byte) (int, error) {
	if w.b.Len()+len(p) > w.max {
		w.over = true
		return 0, errors.New("reply too large")
	}
	return w.b.Write(p)
}
