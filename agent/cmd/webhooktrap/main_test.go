package main

import (
	"bytes"
	"encoding/json"
	"strings"
	"testing"
)

func TestVersion(t *testing.T) {
	for _, args := range [][]string{nil, {"version"}, {"--version"}} {
		var out, errOut bytes.Buffer
		if code := run(args, &out, &errOut); code != 0 {
			t.Fatalf("run(%v) = %d, stderr %q", args, code, errOut.String())
		}
		if !strings.HasPrefix(out.String(), "webhooktrap ") {
			t.Fatalf("run(%v) printed %q", args, out.String())
		}
	}
}

func TestVersionJSON(t *testing.T) {
	var out, errOut bytes.Buffer
	if code := run([]string{"version", "--json"}, &out, &errOut); code != 0 {
		t.Fatalf("exit %d", code)
	}
	var got map[string]string
	if err := json.Unmarshal(out.Bytes(), &got); err != nil {
		t.Fatalf("invalid JSON %q: %v", out.String(), err)
	}
	if got["protocol"] != "v1" || got["version"] == "" {
		t.Fatalf("unexpected %v", got)
	}
}

func TestUnknownCommand(t *testing.T) {
	var out, errOut bytes.Buffer
	if code := run([]string{"nope"}, &out, &errOut); code != 2 {
		t.Fatalf("exit %d, want 2", code)
	}
}
