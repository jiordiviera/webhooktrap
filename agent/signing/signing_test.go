package signing

import (
	"encoding/json"
	"fmt"
	"strings"
	"testing"
)

// GitHub's documented test vector (docs.github.com, "Validating webhook deliveries").
func TestGitHubVector(t *testing.T) {
	s := NewSecret("GITHUB_WEBHOOK_SECRET", []byte("It's a Secret to Everybody"))
	h, err := Sign("github_sha256", s, "", []byte("Hello, World!"), 0)
	if err != nil {
		t.Fatal(err)
	}
	want := "sha256=757107ea0eb2509fc211221cce984b8a37570b6d7586c22c46f4379c8b043e17"
	if h[0][1] != want {
		t.Fatalf("got %s", h[0][1])
	}
}

// Standard Webhooks reference vector (standard-webhooks spec test suite).
func TestStandardWebhooksVector(t *testing.T) {
	s := NewSecret("WEBHOOK_SECRET", []byte("whsec_MfKQ9r8GKYqrTwjUPD8ILPZIo2LaLaSw"))
	h, err := Sign("standard_webhooks_v1", s, "msg_p5jXN8AQM9LWM0D4loKWxJek", []byte(`{"test": 2432232314}`), 1614265330)
	if err != nil {
		t.Fatal(err)
	}
	if got := h[2][1]; got != "v1,g0hM9SsE+OTPJTGt/tmIKtSyZlE3uFJELVlNIOLJ1OE=" {
		t.Fatalf("got %s", got)
	}
}

func TestRoundTripAndFailureModes(t *testing.T) {
	body := []byte(`{"id":"evt_1","amount":2400}`)
	tampered := []byte(`{"id":"evt_1","amount":0}`)
	const now, tol = 1794406143, 300
	for _, scheme := range []string{"stripe_v1", "github_sha256", "shopify_hmac_sha256", "standard_webhooks_v1"} {
		s := NewSecret("X", []byte("whsec_c2VjcmV0LXZhbHVlLWZvci10ZXN0cw=="))
		h, err := Sign(scheme, s, "msg_1", body, now)
		if err != nil {
			t.Fatal(scheme, err)
		}
		if c := Verify(scheme, s, h, body, now, tol); !c.Valid() {
			t.Errorf("%s: valid delivery rejected: %+v", scheme, c)
		}
		if c := Verify(scheme, s, h, tampered, now, tol); c.SignatureOK || !c.TimestampOK || !c.HeaderPresent {
			t.Errorf("%s: tampered must fail on the signature only: %+v", scheme, c)
		}
		if c := Verify(scheme, s, nil, body, now, tol); c.HeaderPresent {
			t.Errorf("%s: missing header not detected", scheme)
		}
		late, _ := Sign(scheme, s, "msg_1", body, now-360)
		c := Verify(scheme, s, late, body, now, tol)
		signsTime := scheme == "stripe_v1" || scheme == "standard_webhooks_v1"
		if signsTime && (c.TimestampOK || !c.SignatureOK) {
			t.Errorf("%s: late must fail on the timestamp only: %+v", scheme, c)
		}
	}
}

func TestSecretNeverPrinted(t *testing.T) {
	s := NewSecret("STRIPE_WEBHOOK_SECRET", []byte("whsec_TOPSECRETVALUE1234"))
	raw, _ := json.Marshal(struct{ S Secret }{s})
	for _, out := range []string{fmt.Sprint(s), fmt.Sprintf("%v %+v %#v %s %q", s, s, s, s, s), string(raw), fmt.Sprint(struct{ S Secret }{s})} {
		if strings.Contains(out, "TOPSECRET") || strings.Contains(out, "1234") {
			t.Fatalf("secret leaked: %s", out)
		}
	}
}
