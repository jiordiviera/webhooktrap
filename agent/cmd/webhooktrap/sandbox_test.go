package main

import (
	"bytes"
	"context"
	"os"
	"path/filepath"
	"strings"
	"testing"
	"time"

	"github.com/jiordiviera/webhooktrap/agent/internal/packdata"
	"github.com/jiordiviera/webhooktrap/agent/internal/sandbox"
)

// TestMain lets the test binary act as serve-sandbox's child: the server
// re-executes os.Executable() with "sandbox-call", as in production.
func TestMain(m *testing.M) {
	if len(os.Args) > 1 && os.Args[1] == "sandbox-call" {
		os.Exit(run(os.Args[1:], os.Stdout, os.Stderr))
	}
	os.Exit(m.Run())
}

func TestServeSandboxAndPing(t *testing.T) {
	dir, err := os.MkdirTemp("", "wtsb") // short: socket paths are capped near 108 bytes
	if err != nil {
		t.Fatal(err)
	}
	defer os.RemoveAll(dir)
	sock := filepath.Join(dir, "engine.sock")

	// Ping before the sandbox runs: unhealthy.
	var out, errb bytes.Buffer
	if code := runSandboxPing(context.Background(), []string{"--socket=" + sock, "--timeout=1s"}, env{stdout: &out, stderr: &errb}); code != 1 {
		t.Fatalf("ping without a sandbox: exit %d", code)
	}

	ctx, cancel := context.WithCancel(context.Background())
	var log syncWriter
	var logb bytes.Buffer
	log.w = &logb
	done := make(chan int)
	go func() {
		done <- runServeSandbox(ctx, []string{"--socket=" + sock}, env{stdout: &out, stderr: &log, packs: packdata.Source()})
	}()
	deadline := time.Now().Add(5 * time.Second)
	for {
		if _, err := os.Stat(sock); err == nil {
			break
		}
		if time.Now().After(deadline) {
			t.Fatal("socket never appeared")
		}
		time.Sleep(10 * time.Millisecond)
	}
	out.Reset()
	if code := runSandboxPing(context.Background(), []string{"--socket=" + sock}, env{stdout: &out, stderr: &errb}); code != 0 || out.String() != "ok\n" {
		t.Fatalf("ping: exit %d, %q %q", code, out.String(), errb.String())
	}
	// A real plan call through the re-executed child.
	r, err := sandbox.Call(context.Background(), sock, sandbox.Request{Op: sandbox.OpPlan,
		Input: []byte(`{"captures":[{"ref":"cap_1","order":1,"headers":[["Content-Type","application/json"]],"body_base64":"eyJpZCI6ImV2dF8xIiwidHlwZSI6Imludm9pY2UucGFpZCIsImRhdGEiOnsib2JqZWN0Ijp7ImlkIjoiaW5fMSJ9fX0="}],"scenarios":["once","twice"],"pack":{"id":"stripe"}}`)})
	if err != nil || !r.OK || r.Preview == nil || r.Preview.DeliveryCount != 3 {
		t.Fatalf("plan: %+v %v", r, err)
	}
	cancel()
	if code := <-done; code != 0 {
		t.Fatalf("serve-sandbox exit %d", code)
	}
	if _, err := os.Stat(sock); !os.IsNotExist(err) {
		t.Fatal("socket left behind")
	}
	log.mu.Lock()
	defer log.mu.Unlock()
	if !strings.Contains(logb.String(), "op=ping outcome=ok") || !strings.Contains(logb.String(), "op=plan outcome=ok") || strings.Contains(logb.String(), "evt_1") {
		t.Fatalf("log: %s", logb.String())
	}
}
