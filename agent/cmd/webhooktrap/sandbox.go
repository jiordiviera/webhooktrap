package main

import (
	"context"
	"errors"
	"flag"
	"fmt"
	"io"
	"os"
	"time"

	"github.com/jiordiviera/webhooktrap/agent/internal/sandbox"
	"github.com/jiordiviera/webhooktrap/agent/internal/version"
)

const usageServeSandbox = `usage: webhooktrap serve-sandbox --socket PATH [--concurrency N]

The network-free engine service for the app (ADR-010 §1, ENG-1, ENG-2).
Listens on a Unix socket (mode 0660) and answers plan, verify and ping,
nothing else. Every call runs in a fresh child process with an empty
environment, a 5 s deadline and a memory cap; calls over the concurrency
cap are refused with "busy", never queued. Logs carry call number, op,
outcome and duration only. SIGTERM or Ctrl-C stops it.
`

const usageSandboxPing = `usage: webhooktrap sandbox-ping --socket PATH [--timeout 4s]

Health check of serve-sandbox: sends one ping through the socket (the
answer comes from a fresh child, as for a real call) and exits 0 when the
sandbox answers with this binary's version, 1 otherwise.
`

const defaultSocket = "/run/engine/engine.sock"

func runServeSandbox(ctx context.Context, args []string, e env) int {
	fs := flag.NewFlagSet("serve-sandbox", flag.ContinueOnError)
	fs.SetOutput(io.Discard)
	socket := fs.String("socket", defaultSocket, "")
	conc := fs.Int("concurrency", sandbox.DefaultConcurrency, "")
	if err := fs.Parse(args); err != nil || fs.NArg() > 0 {
		if errors.Is(err, flag.ErrHelp) {
			fmt.Fprint(e.stdout, usageServeSandbox)
			return 0
		}
		fmt.Fprint(e.stderr, usageServeSandbox)
		return 2
	}
	if *socket == "" || *conc < 1 || *conc > 16 {
		fmt.Fprint(e.stderr, usageServeSandbox)
		return 2
	}
	self, err := os.Executable()
	if err != nil {
		fmt.Fprintf(e.stderr, "webhooktrap serve-sandbox: cannot find this binary: %s\n", clean(err.Error()))
		return 1
	}
	s := &sandbox.Server{Path: *socket, Spawn: sandbox.ExecSpawner(self, "sandbox-call"), Concurrency: *conc, Log: e.stderr}
	ln, err := s.Listen()
	if err != nil {
		fmt.Fprintf(e.stderr, "webhooktrap serve-sandbox: %s\n", clean(err.Error()))
		return 1
	}
	fmt.Fprintf(e.stderr, "sandbox listening socket=%s version=%s concurrency=%d\n", *socket, version.Current().Version, *conc)
	err = s.Serve(ctx, ln)
	_ = os.Remove(*socket)
	if err != nil {
		fmt.Fprintf(e.stderr, "webhooktrap serve-sandbox: %s\n", clean(err.Error()))
		return 1
	}
	fmt.Fprintln(e.stderr, "sandbox stopped")
	return 0
}

// runSandboxCall is the per-call child of serve-sandbox. It is not a user
// command: it reads one frame on stdin and writes one reply line.
func runSandboxCall(e env) int {
	return sandbox.RunChild(e.stdin, e.stdout, e.packs)
}

func runSandboxPing(ctx context.Context, args []string, e env) int {
	fs := flag.NewFlagSet("sandbox-ping", flag.ContinueOnError)
	fs.SetOutput(io.Discard)
	socket := fs.String("socket", defaultSocket, "")
	timeout := fs.Duration("timeout", 4*time.Second, "")
	if err := fs.Parse(args); err != nil || fs.NArg() > 0 || *timeout <= 0 {
		if errors.Is(err, flag.ErrHelp) {
			fmt.Fprint(e.stdout, usageSandboxPing)
			return 0
		}
		fmt.Fprint(e.stderr, usageSandboxPing)
		return 2
	}
	cctx, cancel := context.WithTimeout(ctx, *timeout)
	defer cancel()
	r, err := sandbox.Call(cctx, *socket, sandbox.Request{Op: sandbox.OpPing})
	switch {
	case err != nil:
		fmt.Fprintf(e.stderr, "sandbox-ping: %s\n", clean(err.Error()))
		return 1
	case !r.OK:
		code := "unknown"
		if r.Error != nil {
			code = r.Error.Code
		}
		fmt.Fprintf(e.stderr, "sandbox-ping: %s\n", clean(code))
		return 1
	case r.Version != version.Current().Version:
		fmt.Fprintf(e.stderr, "sandbox-ping: sandbox runs %s, this binary is %s\n", clean(display(r.Version, 40)), version.Current().Version)
		return 1
	}
	fmt.Fprintln(e.stdout, "ok")
	return 0
}
