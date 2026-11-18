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

// Slack's documented example (docs.slack.dev, "Verifying requests from Slack").
func TestSlackVector(t *testing.T) {
	s := NewSecret("SLACK_SIGNING_SECRET", []byte("8f742231b10e8888abcd99yyyzzz85a5"))
	body := []byte("token=xyzz0WbapA4vBCDEFasx0q6G&team_id=T1DC2JH3J&team_domain=testteamnow&channel_id=G8PSS9T3V&channel_name=foobar&user_id=U2CERLKJA&user_name=roadrunner&command=%2Fwebhook-collect&text=&response_url=https%3A%2F%2Fhooks.slack.com%2Fcommands%2FT1DC2JH3J%2F397700885554%2F96rGlfmibIGlgcZRskXaIFfN&trigger_id=398738663015.47445629121.803a0bc887a14d10d2c447fce8b6703c")
	h, err := Sign("slack_v0", s, "", body, 1531420618)
	if err != nil {
		t.Fatal(err)
	}
	if got := h[1][1]; got != "v0=a2114d57b48eac39b9ad189dd8316235a7b4a8d21a10bd27519666489c69b503" {
		t.Fatalf("got %s", got)
	}
	if c := Verify("slack_v0", s, h, body, 1531420618, 300); !c.Valid() {
		t.Fatalf("documented request rejected: %+v", c)
	}
}

// Paddle signs ts + ":" + body; a header may carry several h1 values while a
// secret is rotated out, and any one matching is enough.
func TestPaddleRotation(t *testing.T) {
	old := NewSecret("PADDLE_WEBHOOK_SECRET", []byte("pdl_ntfset_old_synthetic"))
	cur := NewSecret("PADDLE_WEBHOOK_SECRET", []byte("pdl_ntfset_new_synthetic"))
	body := []byte(`{"event_id":"evt_01hv8x2a8x3hf8v9m3zd3q7p5n","event_type":"transaction.billed"}`)
	a, _ := Sign("paddle_h1", old, "", body, 1700000000)
	b, _ := Sign("paddle_h1", cur, "", body, 1700000000)
	_, h1, _ := strings.Cut(b[0][1], ";")
	both := [][2]string{{PaddleHeader, a[0][1] + ";" + h1}}
	if c := Verify("paddle_h1", cur, both, body, 1700000003, 5); !c.Valid() {
		t.Fatalf("rotated header rejected: %+v", c)
	}
	if c := Verify("paddle_h1", cur, a, body, 1700000003, 5); c.SignatureOK {
		t.Fatal("old secret only must fail")
	}
	if c := Verify("paddle_h1", cur, [][2]string{{PaddleHeader, "ts=abc;h1=00"}}, body, 0, 5); c.WellFormed {
		t.Fatal("malformed header accepted")
	}
}

func TestRoundTripAndFailureModes(t *testing.T) {
	body := []byte(`{"id":"evt_1","amount":2400}`)
	tampered := []byte(`{"id":"evt_1","amount":0}`)
	const now, tol = 1794406143, 300
	for _, scheme := range []string{"stripe_v1", "github_sha256", "shopify_hmac_sha256", "standard_webhooks_v1", "slack_v0", "paddle_h1"} {
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
		signsTime := scheme != "github_sha256" && scheme != "shopify_hmac_sha256"
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
