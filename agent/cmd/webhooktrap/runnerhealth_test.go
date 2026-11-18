package main

import (
	"bytes"
	"os"
	"path/filepath"
	"testing"
	"time"
)

func TestServeRunWritesHealthFile(t *testing.T) {
	health := filepath.Join(t.TempDir(), "runner.health")
	f := newFakeRunnerAPI(t)
	f.queue(t, "https://handler.example/webhooks/stripe", nil)
	e, errb := runnerEnv(t, f, &fakeTarget{}, map[string]string{"RUNNER_HEALTH_FILE": health})
	if code := serveRun(t, f, e); code != 0 {
		t.Fatalf("exit %d: %s", code, errb.String())
	}
	if _, err := os.Stat(health); err != nil {
		t.Fatalf("no health file after an accepted hello: %v", err)
	}
	var out bytes.Buffer
	if code := runRunnerHealth([]string{"--file", health}, env{stdout: &out, stderr: &out}); code != 0 {
		t.Fatalf("runner-health exit %d: %s", code, out.String())
	}
}

func TestServeRunNoHealthFileWhenRefused(t *testing.T) {
	health := filepath.Join(t.TempDir(), "runner.health")
	f := newFakeRunnerAPI(t)
	f.hello = 401
	e, _ := runnerEnv(t, f, &fakeTarget{}, map[string]string{"RUNNER_HEALTH_FILE": health})
	if code := serveRun(t, f, e); code != 1 {
		t.Fatalf("exit %d, want 1", code)
	}
	if _, err := os.Stat(health); err == nil {
		t.Fatal("health file written although the token was refused")
	}
}

func TestRunnerHealth(t *testing.T) {
	health := filepath.Join(t.TempDir(), "runner.health")
	now := time.Unix(1794406143, 0)
	run := func(args ...string) (int, string) {
		var out bytes.Buffer
		code := runRunnerHealth(args, env{stdout: &out, stderr: &out, now: func() time.Time { return now }})
		return code, out.String()
	}
	if code, _ := run("--file", health); code != 1 {
		t.Fatalf("missing file: exit %d, want 1", code)
	}
	if err := os.WriteFile(health, nil, 0o600); err != nil {
		t.Fatal(err)
	}
	if err := os.Chtimes(health, now.Add(-30*time.Second), now.Add(-30*time.Second)); err != nil {
		t.Fatal(err)
	}
	if code, out := run("--file", health); code != 0 || out != "ok\n" {
		t.Fatalf("fresh file: exit %d %q", code, out)
	}
	if code, _ := run("--file", health, "--max-age", "10s"); code != 1 {
		t.Fatalf("stale file: exit %d, want 1", code)
	}
	if code, _ := run(); code != 2 {
		t.Fatalf("no file configured: exit %d, want 2", code)
	}
	if code, _ := run("--max-age", "0s", "--file", health); code != 2 {
		t.Fatalf("zero max-age: exit %d, want 2", code)
	}
}
