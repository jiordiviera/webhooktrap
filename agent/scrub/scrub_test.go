package scrub_test

import (
	"encoding/base64"
	"encoding/hex"
	"encoding/json"
	"os"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jiordiviera/webhooktrap/agent/internal/packdata"
	"github.com/jiordiviera/webhooktrap/agent/scrub"
	"github.com/jiordiviera/webhooktrap/agent/signing"
)

func TestDisplay(t *testing.T) {
	salt := []byte("0123456789abcdef")
	for raw, want := range map[string]string{"0": "0", "2": "2", "-9999": "-9999", "9999": "9999"} {
		if got := scrub.Display("orders.count", raw, salt, 24); got != want {
			t.Errorf("%s → %s", raw, got)
		}
	}
	for _, raw := range []string{"10000", "past_due", "007", "-0", "1.5", "a\nb"} {
		got := scrub.Display("x", raw, salt, 24)
		if len(got) != 7 || got[0] != '#' {
			t.Errorf("%q → %q, want a 6-hex code (PV-2)", raw, got)
		}
	}
	if scrub.Display("x", "past_due", salt, 24) == scrub.Display("x", "past_due", []byte("other-salt-12345"), 24) {
		t.Error("codes must not link across runs (PV-3)")
	}
	if len(scrub.Display("x", "v", salt, 48)) != 13 {
		t.Error("48 bits is 12 hex (PV-4)")
	}
}

func TestMaskerEncodedForms(t *testing.T) {
	v := "whsec_ABCDEFGHIJKLMNOP1234"
	m := scrub.NewMasker(signing.NewSecret("STRIPE_WEBHOOK_SECRET", []byte(v)))
	for _, s := range []string{v, base64.StdEncoding.EncodeToString([]byte(v)), hex.EncodeToString([]byte(v))} {
		out := m.Mask("x " + s + " y")
		if strings.Contains(out, s) || !strings.Contains(out, "[STRIPE_WEBHOOK_SECRET]") {
			t.Errorf("not masked: %s", out)
		}
	}
}

// TestFixtureScrubRD reproduces RD §4.5's preview on capture #01.
func TestFixtureScrubRD(t *testing.T) {
	raw, err := os.ReadFile(filepath.Join("..", "testdata", "fixtures", "stripe", "invoice.paid.json"))
	if err != nil {
		t.Fatal(err)
	}
	var f struct{ Body string }
	_ = json.Unmarshal(raw, &f)
	pk, _ := packdata.Source().Load("stripe", "")
	s := scrub.NewSession(pk, nil, nil)
	out, reps, blocks, err := s.Scrub("invoice.paid.json", []byte(f.Body))
	if err != nil || len(blocks) != 0 {
		t.Fatalf("%v %v", err, blocks)
	}
	got := map[string]string{}
	for _, r := range reps {
		got[r.Path] = r.After
	}
	want := map[string]string{
		"data.object.customer_email":     "customer1@example.test",
		"data.object.customer_name":      "Test Customer 1",
		"data.object.hosted_invoice_url": "https://invoice.stripe.test/redacted",
	}
	for k, v := range want {
		if got[k] != v {
			t.Errorf("%s = %q, want %q", k, got[k], v)
		}
	}
	if !json.Valid(out) || !strings.Contains(string(out), `"id": "in_1QKImRcZJ8HR9BZYostEV4ui"`) {
		t.Error("ids must be kept (SCR-2) and the output valid JSON")
	}
	// Consistent across files of one save (SCR-3).
	_, reps2, _, _ := s.Scrub("other.json", []byte(`{"email":"jonah.reyes@example.com"}`))
	if len(reps2) != 1 || reps2[0].After != "customer1@example.test" {
		t.Errorf("not consistent: %+v", reps2)
	}
}

func TestFixtureScrubBlocksSecrets(t *testing.T) {
	pk, _ := packdata.Source().Load("stripe", "")
	sec := "whsec_ABCDEFGHIJKLMNOP1234"
	s := scrub.NewSession(pk, nil, scrub.NewMasker(signing.NewSecret("S", []byte(sec))))
	_, _, blocks, _ := s.Scrub("f.json", []byte(`{"a":"sk_live_abc","b":"`+sec+`","c":"ok"}`))
	if len(blocks) != 2 {
		t.Fatalf("SCR-4 blocks: %+v", blocks)
	}
}
