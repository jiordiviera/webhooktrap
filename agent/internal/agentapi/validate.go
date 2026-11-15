package agentapi

import (
	"bytes"
	"crypto/sha256"
	"encoding/base64"
	"encoding/hex"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"net/url"
	"regexp"
	"strings"
)

// Capture limits checked before a fetched capture is used (S-4, ADR-010 §6).
const (
	MaxCaptureBody    = 5 << 20
	MaxCaptureHeaders = 256
	MaxHeaderBytes    = 16 << 10
	MaxJSONDepth      = 64
	MaxSelection      = 5  // DEF-1
	MaxDeliveries     = 50 // job.response.schema.json
)

var (
	idRe = func(prefix string) *regexp.Regexp {
		return regexp.MustCompile(`^` + prefix + `_[0-7][0-9A-HJKMNP-TV-Z]{25}$`)
	} // UUIDv7, Crockford base32
	jobIDRe   = idRe("job")
	runIDRe   = idRe("run")
	capIDRe   = idRe("cap")
	agentIDRe = idRe("agt")
	sha256Re  = regexp.MustCompile(`^[0-9a-f]{64}$`)
	packIDRe  = regexp.MustCompile(`^[a-z][a-z0-9_]{1,31}$`)
	semverRe  = regexp.MustCompile(`^[0-9]+\.[0-9]+\.[0-9]+([-+][0-9A-Za-z.-]{1,40})?$`)
	seedRe    = regexp.MustCompile(`^[0-9]{1,20}$`)
	headerRe  = regexp.MustCompile("^[!#$%&'*+.^_`|~0-9A-Za-z-]{1,256}$")
)

// ValidAgentID checks an agent id from the server.
func ValidAgentID(s string) bool { return agentIDRe.MatchString(s) }

// ValidateJob checks a job's shape before anything acts on it.
func ValidateJob(j *Job) error {
	if !jobIDRe.MatchString(j.JobID) {
		return errors.New("job_id is not valid")
	}
	switch j.Kind {
	case "verify":
		if len(j.Captures) == 0 || len(j.Captures) > 50 {
			return errors.New("verify job has no or too many captures")
		}
		for _, c := range j.Captures {
			if !capIDRe.MatchString(c.CaptureID) || !sha256Re.MatchString(c.BodySHA256) {
				return errors.New("verify job capture reference is not valid")
			}
		}
		return nil
	case "run":
	default:
		return fmt.Errorf("unknown job kind %q", j.Kind)
	}
	if !runIDRe.MatchString(j.RunID) || j.RunNumber < 1 {
		return errors.New("run_id or run_number is not valid")
	}
	if len(j.Selection) == 0 || len(j.Selection) > MaxSelection {
		return fmt.Errorf("selection must hold 1 to %d captures", MaxSelection)
	}
	seen := map[string]bool{}
	for _, s := range j.Selection {
		if !capIDRe.MatchString(s.CaptureID) || !sha256Re.MatchString(s.BodySHA256) || s.Seq < 1 || seen[s.CaptureID] {
			return errors.New("selection holds an invalid or repeated capture reference")
		}
		seen[s.CaptureID] = true
	}
	if len(j.Scenarios) == 0 || len(j.Scenarios) > 9 {
		return errors.New("scenarios missing")
	}
	if !packIDRe.MatchString(j.Pack.ID) || !semverRe.MatchString(j.Pack.Version) {
		return errors.New("pack reference is not valid")
	}
	switch j.FailOn {
	case "critical", "high", "medium", "never":
	default:
		return errors.New("fail_on is not valid")
	}
	if !seedRe.MatchString(j.Seed) {
		return errors.New("seed is not valid")
	}
	if j.DeliveryCount != nil && (*j.DeliveryCount < 1 || *j.DeliveryCount > MaxDeliveries) {
		return errors.New("delivery_count is not valid")
	}
	if len(j.TargetRef) > 2048 {
		return errors.New("target_ref is too long")
	}
	u, err := url.Parse(j.TargetRef)
	if err != nil || (u.Scheme != "http" && u.Scheme != "https") || u.Host == "" || u.User != nil {
		return errors.New("target_ref is not an http(s) URL")
	}
	if len(u.EscapedPath()) > 512 || !targetChars(j.TargetRef) {
		return errors.New("target path and query must be printable ASCII without spaces, at most 512 bytes (§5.11)")
	}
	return nil
}

// targetChars is the server's path and query charset: printable ASCII
// without space (0x21–0x7E), so no raw space, control or non-ASCII byte
// ever reaches a request line (AC-15).
func targetChars(s string) bool {
	for i := 0; i < len(s); i++ {
		if s[i] <= 0x20 || s[i] > 0x7e {
			return false
		}
	}
	return true
}

// VerifyCapture checks a fetched capture against the job's reference:
// id, body_sha256 over the decoded bytes, header count and sizes, and JSON
// nesting depth. It returns the raw body.
func VerifyCapture(ref SelectionRef, c *CaptureRequest) ([]byte, error) {
	if c.CaptureID != ref.CaptureID {
		return nil, errors.New("the server sent a different capture than the job references")
	}
	if base64.StdEncoding.DecodedLen(len(c.BodyBase64)) > MaxCaptureBody+3 {
		return nil, fmt.Errorf("capture body is larger than %d bytes", MaxCaptureBody)
	}
	body, err := base64.StdEncoding.DecodeString(c.BodyBase64)
	if err != nil {
		return nil, errors.New("capture body is not valid base64")
	}
	if len(body) > MaxCaptureBody {
		return nil, fmt.Errorf("capture body is larger than %d bytes", MaxCaptureBody)
	}
	sum := sha256.Sum256(body)
	if got := hex.EncodeToString(sum[:]); got != ref.BodySHA256 {
		return nil, errors.New("capture body_sha256 doesn't match the job (refused, S-4)")
	}
	if len(c.Headers) > MaxCaptureHeaders {
		return nil, fmt.Errorf("capture has more than %d headers", MaxCaptureHeaders)
	}
	for _, h := range c.Headers {
		if !headerRe.MatchString(h[0]) || len(h[1]) > MaxHeaderBytes || strings.ContainsAny(h[1], "\r\n\x00") {
			return nil, errors.New("capture has a malformed header")
		}
	}
	if looksJSON(body) {
		if d, err := JSONDepth(body); err != nil || d > MaxJSONDepth {
			if err == nil {
				err = fmt.Errorf("capture body nests deeper than %d levels", MaxJSONDepth)
			}
			return nil, err
		}
	}
	if c.BodyBytes != len(body) {
		return nil, errors.New("capture body_bytes doesn't match the body")
	}
	return body, nil
}

// ResendableHeaders drops the headers the server stored redacted: their
// values are placeholders and are never re-sent as such.
func ResendableHeaders(c *CaptureRequest) [][2]string {
	red := map[string]bool{}
	for _, h := range c.RedactedHeaders {
		red[strings.ToLower(h)] = true
	}
	out := make([][2]string, 0, len(c.Headers))
	for _, h := range c.Headers {
		if !red[strings.ToLower(h[0])] {
			out = append(out, h)
		}
	}
	return out
}

func looksJSON(b []byte) bool {
	t := bytes.TrimLeft(b, " \t\r\n")
	return len(t) > 0 && (t[0] == '{' || t[0] == '[')
}

// JSONDepth returns the maximum nesting depth of a JSON document, stopping
// early past MaxJSONDepth. A document that isn't JSON returns depth 0 and no
// error: the engine treats it as an opaque body.
func JSONDepth(b []byte) (int, error) {
	dec := json.NewDecoder(bytes.NewReader(b))
	depth, maxD := 0, 0
	for {
		tok, err := dec.Token()
		if errors.Is(err, io.EOF) {
			return maxD, nil
		}
		if err != nil {
			return 0, nil
		}
		if d, ok := tok.(json.Delim); ok {
			if d == '{' || d == '[' {
				depth++
				if depth > maxD {
					maxD = depth
				}
				if maxD > MaxJSONDepth {
					return maxD, nil
				}
			} else {
				depth--
			}
		}
	}
}
