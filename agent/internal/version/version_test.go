package version

import (
	"encoding/json"
	"strings"
	"testing"
)

func TestCurrentDefaults(t *testing.T) {
	info := Current()
	if info.Version != Version || info.Commit != Commit {
		t.Fatalf("Current() = %+v, want version %q commit %q", info, Version, Commit)
	}
	if info.Protocol != "v1" {
		t.Fatalf("protocol = %q, want v1", info.Protocol)
	}
	if !strings.HasPrefix(info.String(), "webhooktrap "+Version) {
		t.Fatalf("String() = %q", info.String())
	}
}

func TestJSONRoundTrip(t *testing.T) {
	raw, err := Current().JSON()
	if err != nil {
		t.Fatal(err)
	}
	var got Info
	if err := json.Unmarshal(raw, &got); err != nil {
		t.Fatal(err)
	}
	if got != Current() {
		t.Fatalf("round trip = %+v, want %+v", got, Current())
	}
}
