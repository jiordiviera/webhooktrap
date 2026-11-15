package main

import (
	"bytes"
	"context"
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"os"
	"path/filepath"
	"strings"
	"testing"
	"time"

	"github.com/jiordiviera/webhooktrap/agent/internal/packdata"
	"github.com/jiordiviera/webhooktrap/agent/internal/refhandler"
)

// TestHelperReset is not a test: the real-time test uses this binary as its
// reset command (`reset:` in webhooktrap.yml), so no curl is needed.
func TestHelperReset(t *testing.T) {
	u := os.Getenv("WT_HELPER_RESET_URL")
	if u == "" {
		t.Skip("helper process only")
	}
	resp, err := http.Post(u, "text/plain", nil)
	if err != nil || resp.StatusCode != 200 {
		os.Exit(1)
	}
	os.Exit(0)
}

// TestRealTimeRun3 is the real-time suite (golden-files §6.3): a real clock,
// real sleeps in the handler, a real cmd: reset and a url: probe. It asserts
// structure, never bytes: F1 to F4 with RD's evidence and exit 1.
func TestRealTimeRun3(t *testing.T) {
	if testing.Short() {
		t.Skip("real-time suite (about 15 s)")
	}
	src, _ := filepath.Abs(filepath.Join("..", "..", "testdata", "fixtures", "stripe"))
	self, _ := filepath.Abs(os.Args[0])
	t.Chdir(t.TempDir())
	_ = os.MkdirAll(filepath.Join("webhooktrap", "fixtures", "stripe"), 0o755)
	for _, f := range fixtures {
		raw, _ := os.ReadFile(filepath.Join(src, filepath.Base(f)))
		_ = os.WriteFile(f, raw, 0o644)
	}
	h := refhandler.New(false, []byte(testSecret), time.Now)
	h.Latency = map[string]time.Duration{"D10": 400 * time.Millisecond, "D16": 350 * time.Millisecond}
	srv := httptest.NewServer(h)
	defer srv.Close()
	cfg := "target: " + srv.URL + "/webhooks/stripe\n" +
		"fixtures:\n  - " + strings.Join(fixtures, "\n  - ") + "\n" +
		"scenarios: [" + run3Scenarios + "]\n" +
		"budget: 300\n" +
		"probe:\n  - as: orders.count\n    url: " + srv.URL + "/_debug/orders?invoice={{invoice.id}}\n" +
		"reset: \"'" + self + "' -test.run=TestHelperReset\"\n"
	_ = os.WriteFile("webhooktrap.yml", []byte(cfg), 0o644)
	var out, errb bytes.Buffer
	e := env{stdout: &out, stderr: &errb, packs: packdata.Source(),
		getenv: func(k string) string {
			if k == "STRIPE_WEBHOOK_SECRET" {
				return testSecret
			}
			return ""
		},
		environ: append(os.Environ(), "WT_HELPER_RESET_URL="+srv.URL+"/_debug/reset", "STRIPE_WEBHOOK_SECRET="+testSecret),
	}
	code := runTest(context.Background(), []string{"--yes", "--out", "run.json"}, e)
	if code != 1 {
		t.Fatalf("exit %d\n%s\n%s", code, out.String(), errb.String())
	}
	var d docView
	raw, _ := os.ReadFile("run.json")
	if err := json.Unmarshal(raw, &d); err != nil {
		t.Fatal(err)
	}
	got := []string{}
	for _, f := range d.Findings {
		got = append(got, f.ID+" "+f.Type)
	}
	want := "F1 accepts_tampered,F2 duplicate_changed_state,F3 over_budget,F4 probe_gap"
	if strings.Join(got, ",") != want {
		t.Fatalf("findings %v, want %s\n%s", got, want, out.String())
	}
	if bytes.Contains(raw, []byte(testSecret)) {
		t.Fatal("secret in the run document")
	}
}
