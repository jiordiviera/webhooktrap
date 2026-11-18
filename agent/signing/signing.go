package signing

import (
	"crypto/hmac"
	"crypto/sha1"
	"crypto/sha256"
	"encoding/base64"
	"encoding/hex"
	"errors"
	"fmt"
	"strconv"
	"strings"
)

// Secret is a signing secret. Its value never leaves this process: String,
// GoString, Format and MarshalJSON print the name only, so a secret can't
// reach a log line, an error or the run document by accident (JSON-2, SIG-3).
type Secret struct {
	Name  string // environment variable name, the only part ever shown
	value []byte
}

// NewSecret wraps a value read from the environment or a keychain.
func NewSecret(name string, value []byte) Secret {
	return Secret{Name: name, value: append([]byte(nil), value...)}
}

// IsZero reports whether no secret is held.
func (s Secret) IsZero() bool { return len(s.value) == 0 }

// Reveal returns the value. Only signing and scrub (masking) call it.
func (s Secret) Reveal() []byte { return s.value }

func (s Secret) String() string   { return s.Name + "=[redacted]" }
func (s Secret) GoString() string { return s.String() }

// Format makes every fmt verb print the redacted form.
func (s Secret) Format(f fmt.State, _ rune) { _, _ = f.Write([]byte(s.String())) }

// MarshalJSON writes the name only.
func (s Secret) MarshalJSON() ([]byte, error) { return []byte(strconv.Quote(s.Name)), nil }

// Header names per scheme.
const (
	StripeHeader         = "Stripe-Signature"
	GitHubHeader         = "X-Hub-Signature-256"
	GitHubLegacyHeader   = "X-Hub-Signature"
	ShopifyHeader        = "X-Shopify-Hmac-Sha256"
	StdIDHeader          = "webhook-id"
	StdTimestampHeader   = "webhook-timestamp"
	StdSignatureHeader   = "webhook-signature"
	SlackSignatureHeader = "X-Slack-Signature"
	SlackTimestampHeader = "X-Slack-Request-Timestamp"
	PaddleHeader         = "Paddle-Signature"
)

// ErrUnsupported is returned for a scheme this engine can't sign.
var ErrUnsupported = errors.New("signing scheme not supported")

// Sign returns the signature headers a provider would send for body at unix
// time ts. eventID is the Standard Webhooks message id.
func Sign(scheme string, secret Secret, eventID string, body []byte, ts int64) ([][2]string, error) {
	if secret.IsZero() {
		return nil, errors.New("no secret")
	}
	t := strconv.FormatInt(ts, 10)
	switch scheme {
	case "stripe_v1":
		mac := hmacSHA256(secret.value, []byte(t+"."+string(body)))
		return [][2]string{{StripeHeader, "t=" + t + ",v1=" + hex.EncodeToString(mac)}}, nil
	case "github_sha256":
		return [][2]string{{GitHubHeader, "sha256=" + hex.EncodeToString(hmacSHA256(secret.value, body))}}, nil
	case "shopify_hmac_sha256":
		return [][2]string{{ShopifyHeader, base64.StdEncoding.EncodeToString(hmacSHA256(secret.value, body))}}, nil
	case "standard_webhooks_v1":
		if eventID == "" {
			return nil, errors.New("standard webhooks needs a webhook-id")
		}
		mac := hmacSHA256(standardKey(secret.value), []byte(eventID+"."+t+"."+string(body)))
		return [][2]string{
			{StdIDHeader, eventID},
			{StdTimestampHeader, t},
			{StdSignatureHeader, "v1," + base64.StdEncoding.EncodeToString(mac)},
		}, nil
	case "slack_v0":
		// docs.slack.dev › Verifying requests from Slack: v0=hex(HMAC-SHA256("v0:" + ts + ":" + body)).
		mac := hmacSHA256(secret.value, []byte("v0:"+t+":"+string(body)))
		return [][2]string{
			{SlackTimestampHeader, t},
			{SlackSignatureHeader, "v0=" + hex.EncodeToString(mac)},
		}, nil
	case "paddle_h1":
		// developer.paddle.com › Verify webhook signatures: ts=<ts>;h1=hex(HMAC-SHA256(ts + ":" + body)).
		mac := hmacSHA256(secret.value, []byte(t+":"+string(body)))
		return [][2]string{{PaddleHeader, "ts=" + t + ";h1=" + hex.EncodeToString(mac)}}, nil
	}
	return nil, ErrUnsupported
}

// Check is the outcome of verifying one request the way the provider's own
// verifier does (SIGN-4).
type Check struct {
	HeaderPresent bool
	WellFormed    bool
	SignatureOK   bool
	TimestampOK   bool // true when the scheme signs no timestamp
}

// Valid reports whether a provider verifier would accept the request.
func (c Check) Valid() bool { return c.HeaderPresent && c.WellFormed && c.SignatureOK && c.TimestampOK }

// Verify checks headers and body against the secret at unix time now.
func Verify(scheme string, secret Secret, headers [][2]string, body []byte, now int64, toleranceS int) Check {
	var c Check
	c.TimestampOK = true
	switch scheme {
	case "stripe_v1":
		h := header(headers, StripeHeader)
		if h == "" {
			return c
		}
		c.HeaderPresent = true
		var t string
		var sigs []string
		for _, part := range strings.Split(h, ",") {
			k, v, ok := strings.Cut(part, "=")
			if !ok {
				continue
			}
			switch k {
			case "t":
				t = v
			case "v1":
				sigs = append(sigs, v)
			}
		}
		ts, err := strconv.ParseInt(t, 10, 64)
		if err != nil || len(sigs) == 0 {
			return c
		}
		c.WellFormed = true
		want := hmacSHA256(secret.value, []byte(t+"."+string(body)))
		for _, s := range sigs {
			if got, err := hex.DecodeString(s); err == nil && hmac.Equal(got, want) {
				c.SignatureOK = true
			}
		}
		c.TimestampOK = abs(now-ts) <= int64(toleranceS)
	case "github_sha256":
		h := header(headers, GitHubHeader)
		if h == "" {
			if l := header(headers, GitHubLegacyHeader); l != "" {
				c.HeaderPresent = true
				hexsig, ok := strings.CutPrefix(l, "sha1=")
				got, err := hex.DecodeString(hexsig)
				c.WellFormed = ok && err == nil && len(got) == sha1.Size
				m := hmac.New(sha1.New, secret.value)
				m.Write(body)
				c.SignatureOK = c.WellFormed && hmac.Equal(got, m.Sum(nil))
			}
			return c
		}
		c.HeaderPresent = true
		hexsig, ok := strings.CutPrefix(h, "sha256=")
		got, err := hex.DecodeString(hexsig)
		c.WellFormed = ok && err == nil && len(got) == sha256.Size
		c.SignatureOK = c.WellFormed && hmac.Equal(got, hmacSHA256(secret.value, body))
	case "shopify_hmac_sha256":
		h := header(headers, ShopifyHeader)
		if h == "" {
			return c
		}
		c.HeaderPresent = true
		got, err := base64.StdEncoding.DecodeString(h)
		c.WellFormed = err == nil && len(got) == sha256.Size
		c.SignatureOK = c.WellFormed && hmac.Equal(got, hmacSHA256(secret.value, body))
	case "standard_webhooks_v1":
		id, t, sig := header(headers, StdIDHeader), header(headers, StdTimestampHeader), header(headers, StdSignatureHeader)
		if sig == "" {
			return c
		}
		c.HeaderPresent = true
		ts, err := strconv.ParseInt(t, 10, 64)
		if err != nil || id == "" {
			return c
		}
		c.WellFormed = true
		want := hmacSHA256(standardKey(secret.value), []byte(id+"."+t+"."+string(body)))
		for _, s := range strings.Fields(sig) {
			v, b64, ok := strings.Cut(s, ",")
			if !ok || v != "v1" {
				continue
			}
			if got, err := base64.StdEncoding.DecodeString(b64); err == nil && hmac.Equal(got, want) {
				c.SignatureOK = true
			}
		}
		c.TimestampOK = abs(now-ts) <= int64(toleranceS)
	case "slack_v0":
		sig := header(headers, SlackSignatureHeader)
		if sig == "" {
			return c
		}
		c.HeaderPresent = true
		t := header(headers, SlackTimestampHeader)
		ts, err := strconv.ParseInt(t, 10, 64)
		hexsig, ok := strings.CutPrefix(sig, "v0=")
		got, herr := hex.DecodeString(hexsig)
		if err != nil || !ok || herr != nil || len(got) != sha256.Size {
			return c
		}
		c.WellFormed = true
		c.SignatureOK = hmac.Equal(got, hmacSHA256(secret.value, []byte("v0:"+t+":"+string(body))))
		c.TimestampOK = abs(now-ts) <= int64(toleranceS)
	case "paddle_h1":
		h := header(headers, PaddleHeader)
		if h == "" {
			return c
		}
		c.HeaderPresent = true
		var t string
		var sigs []string
		for _, part := range strings.Split(h, ";") {
			k, v, ok := strings.Cut(strings.TrimSpace(part), "=")
			if !ok {
				continue
			}
			switch k {
			case "ts":
				t = v
			case "h1": // more than one h1 while a secret is rotated out
				sigs = append(sigs, v)
			}
		}
		ts, err := strconv.ParseInt(t, 10, 64)
		if err != nil || len(sigs) == 0 {
			return c
		}
		c.WellFormed = true
		want := hmacSHA256(secret.value, []byte(t+":"+string(body)))
		for _, s := range sigs {
			if got, err := hex.DecodeString(s); err == nil && hmac.Equal(got, want) {
				c.SignatureOK = true
			}
		}
		c.TimestampOK = abs(now-ts) <= int64(toleranceS)
	default:
		c.HeaderPresent, c.WellFormed, c.SignatureOK = true, true, true
	}
	return c
}

// Headers returns every signature header a scheme uses, for stripping (UNS-1).
func Headers(scheme string) []string {
	switch scheme {
	case "stripe_v1":
		return []string{StripeHeader}
	case "github_sha256":
		return []string{GitHubHeader, GitHubLegacyHeader}
	case "shopify_hmac_sha256":
		return []string{ShopifyHeader}
	case "standard_webhooks_v1":
		return []string{StdSignatureHeader} // webhook-id and webhook-timestamp stay (UNS-1)
	case "slack_v0":
		return []string{SlackSignatureHeader} // the timestamp header stays (UNS-1)
	case "paddle_h1":
		return []string{PaddleHeader}
	}
	return nil
}

func hmacSHA256(key, msg []byte) []byte {
	m := hmac.New(sha256.New, key)
	m.Write(msg)
	return m.Sum(nil)
}

// standardKey decodes a "whsec_" base64 secret (Standard Webhooks spec).
func standardKey(secret []byte) []byte {
	s := strings.TrimPrefix(string(secret), "whsec_")
	if k, err := base64.StdEncoding.DecodeString(s); err == nil && len(k) > 0 {
		return k
	}
	return secret
}

func header(h [][2]string, name string) string {
	for _, kv := range h {
		if strings.EqualFold(kv[0], name) {
			return kv[1]
		}
	}
	return ""
}

func abs(x int64) int64 {
	if x < 0 {
		return -x
	}
	return x
}
