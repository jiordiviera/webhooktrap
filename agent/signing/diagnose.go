package signing

import (
	"bytes"
	"encoding/json"
	"regexp"
	"strconv"
	"strings"
	"unicode/utf8"
)

// Signature states and reasons (SJ SIG-1, schema captures.sig_state).
const (
	StateUnsigned      = "unsigned"
	StateUnknownScheme = "unknown_scheme"
	StateInvalid       = "invalid"
	StateNoSecret      = "no_secret"
	StateValid         = "valid"

	ReasonHeaderMalformed     = "header_malformed"
	ReasonTimestampTolerance  = "timestamp_outside_tolerance"
	ReasonBodyAltered         = "body_altered_before_capture"
	ReasonBodyReEncoded       = "body_re_encoded"
	ReasonDifferentSecret     = "different_secret"
	VariantCompactJSON        = "compact_json"
	VariantSortedKeys         = "sorted_keys"
	VariantPrettyJSON         = "pretty_json"
	VariantUnicodeUnescaped   = "unicode_unescaped"
	VariantNonASCIIEscaped    = "non_ascii_escaped"
	VariantCRLFToLF           = "crlf_to_lf"
	VariantLFToCRLF           = "lf_to_crlf"
	maxVariantBody            = 1 << 20
	signatureLikeHeaderSource = `(?i)(^|-)(signature|sig|hmac)(-|$)`
)

var signatureLike = regexp.MustCompile(signatureLikeHeaderSource)

// Scheme is what diagnosis needs from a pack.
type Scheme struct {
	Name            string   // stripe_v1, github_sha256, … ("" or "none": no pack scheme)
	Headers         []string // the pack's signature headers
	SignedTimestamp bool
	ToleranceS      int
}

// CheckResult is one of the seven SIG-2 checks.
type CheckResult struct {
	Check  string `json:"check"`
	Passed bool   `json:"passed"`
}

// Diagnosis is the SIG-1 verdict on one stored capture. It never carries a
// secret value, any part of one, or an expected signature (SIG-3).
type Diagnosis struct {
	State       string        `json:"state"`
	Reason      string        `json:"reason,omitempty"`
	Variant     string        `json:"variant,omitempty"`
	Checks      []CheckResult `json:"checks"`
	SecretNames []string      `json:"secret_names"`
}

// Diagnose runs SIG-1 in order on a stored capture. receivedAt (unix
// seconds) is the capture time: check 7 compares the signed timestamp with
// it, not with now. Check 4 (no agent connected) belongs to the caller.
func Diagnose(s Scheme, secrets []Secret, headers [][2]string, body []byte, receivedAt int64) Diagnosis {
	d := Diagnosis{Checks: []CheckResult{}, SecretNames: []string{}}
	for _, sec := range secrets {
		if !sec.IsZero() {
			d.SecretNames = append(d.SecretNames, sec.Name)
		}
	}
	known := len(Headers(s.Name)) > 0
	present := false
	if known {
		for _, h := range s.Headers {
			if header(headers, h) != "" {
				present = true
			}
		}
	}
	integrity := integrityChecks(headers, body)
	if !present { // checks 1 and 2
		d.State = StateUnsigned
		for _, kv := range headers {
			if signatureLike.MatchString(kv[0]) {
				d.State = StateUnknownScheme
				break
			}
		}
		d.Checks = sig2(integrity, true, true, false, false)
		return d
	}
	// Check 3: the header's form, independent of any secret.
	form := Verify(s.Name, Secret{value: []byte{0}}, headers, body, receivedAt, s.ToleranceS)
	if !form.WellFormed {
		d.State, d.Reason = StateInvalid, ReasonHeaderMalformed
		d.Checks = sig2(integrity, true, true, false, true)
		return d
	}
	if len(d.SecretNames) == 0 { // check 5
		d.State = StateNoSecret
		d.Checks = sig2(integrity, true, form.TimestampOK, true, true)
		return d
	}
	// Check 6: any secret matches the stored body.
	for _, sec := range secrets {
		if sec.IsZero() {
			continue
		}
		c := Verify(s.Name, sec, headers, body, receivedAt, s.ToleranceS)
		if c.SignatureOK {
			d.Checks = sig2(integrity, true, c.TimestampOK, true, true)
			if !c.TimestampOK { // check 7
				d.State, d.Reason = StateInvalid, ReasonTimestampTolerance
				return d
			}
			d.State = StateValid
			return d
		}
	}
	// Check 8: integrity.
	if !integrity[0] || !integrity[1] || !integrity[2] {
		d.State, d.Reason = StateInvalid, ReasonBodyAltered
		d.Checks = sig2(integrity, true, form.TimestampOK, true, true)
		return d
	}
	// Check 9: a re-encoded body matches.
	for _, v := range variants(body) {
		for _, sec := range secrets {
			if !sec.IsZero() && Verify(s.Name, sec, headers, v.body, receivedAt, s.ToleranceS).SignatureOK {
				d.State, d.Reason, d.Variant = StateInvalid, ReasonBodyReEncoded, v.name
				d.Checks = sig2(integrity, false, form.TimestampOK, true, true)
				return d
			}
		}
	}
	d.State, d.Reason = StateInvalid, ReasonDifferentSecret // check 10
	d.Checks = sig2(integrity, true, form.TimestampOK, true, true)
	return d
}

// integrityChecks: Content-Length matches the stored length, valid UTF-8,
// no BOM (SIG-1 check 8). An absent Content-Length passes.
func integrityChecks(headers [][2]string, body []byte) [3]bool {
	cl := true
	if v := header(headers, "Content-Length"); v != "" {
		n, err := strconv.Atoi(strings.TrimSpace(v))
		cl = err == nil && n == len(body)
	}
	return [3]bool{cl, utf8.Valid(body), !bytes.HasPrefix(body, []byte{0xEF, 0xBB, 0xBF})}
}

// sig2 is the SIG-2 list in its fixed order.
func sig2(integrity [3]bool, noReEncoding, timestamp, wellFormed, schemeKnown bool) []CheckResult {
	return []CheckResult{
		{"content_length", integrity[0]},
		{"valid_utf8", integrity[1]},
		{"no_bom", integrity[2]},
		{"no_re_encoding", noReEncoding},
		{"timestamp", timestamp},
		{"header_well_formed", wellFormed},
		{"scheme_known", schemeKnown},
	}
}

type variant struct {
	name string
	body []byte
}

// variants are the re-encodings of SIG-1 check 9, each only when it differs
// from the stored body.
func variants(body []byte) []variant {
	if len(body) > maxVariantBody {
		return nil
	}
	var out []variant
	add := func(name string, b []byte, ok bool) {
		if ok && !bytes.Equal(b, body) {
			out = append(out, variant{name, b})
		}
	}
	if json.Valid(body) {
		var c bytes.Buffer
		add(VariantCompactJSON, c.Bytes(), json.Compact(&c, body) == nil)
		c.Reset()
		var v any
		dec := json.NewDecoder(bytes.NewReader(body))
		dec.UseNumber()
		if dec.Decode(&v) == nil {
			enc := json.NewEncoder(&c)
			enc.SetEscapeHTML(false)
			if enc.Encode(v) == nil {
				add(VariantSortedKeys, bytes.TrimRight(c.Bytes(), "\n"), true)
			}
		}
		var p bytes.Buffer
		add(VariantPrettyJSON, p.Bytes(), json.Indent(&p, body, "", "  ") == nil)
		u, ok := mapStrings(body, func(s string) []byte {
			var b bytes.Buffer
			enc := json.NewEncoder(&b)
			enc.SetEscapeHTML(false)
			_ = enc.Encode(s)
			return bytes.TrimRight(b.Bytes(), "\n")
		})
		add(VariantUnicodeUnescaped, u, ok)
		e, ok := mapStrings(body, asciiString)
		add(VariantNonASCIIEscaped, e, ok)
	}
	add(VariantCRLFToLF, bytes.ReplaceAll(body, []byte("\r\n"), []byte("\n")), true)
	add(VariantLFToCRLF, bytes.ReplaceAll(bytes.ReplaceAll(body, []byte("\r\n"), []byte("\n")), []byte("\n"), []byte("\r\n")), true)
	return out
}

// mapStrings rewrites every JSON string literal of raw with f, keeping
// everything else (key order, spacing) as it is.
func mapStrings(raw []byte, f func(string) []byte) ([]byte, bool) {
	var out bytes.Buffer
	for i := 0; i < len(raw); {
		if raw[i] != '"' {
			out.WriteByte(raw[i])
			i++
			continue
		}
		j := i + 1
		for j < len(raw) && raw[j] != '"' {
			if raw[j] == '\\' {
				j++
			}
			j++
		}
		if j >= len(raw) {
			return nil, false
		}
		var s string
		if json.Unmarshal(raw[i:j+1], &s) != nil {
			return nil, false
		}
		out.Write(f(s))
		i = j + 1
	}
	return out.Bytes(), true
}

// asciiString encodes s as a JSON string with every non-ASCII rune escaped.
func asciiString(s string) []byte {
	var b bytes.Buffer
	b.WriteByte('"')
	for _, r := range s {
		switch {
		case r == '"' || r == '\\':
			b.WriteByte('\\')
			b.WriteRune(r)
		case r < 0x20:
			b.WriteString(`\u00`)
			b.WriteString(strconv.FormatInt(int64(r)>>4, 16) + strconv.FormatInt(int64(r)&0xf, 16))
		case r < 0x80:
			b.WriteRune(r)
		case r > 0xFFFF:
			r -= 0x10000
			writeU(&b, 0xD800+(r>>10))
			writeU(&b, 0xDC00+(r&0x3FF))
		default:
			writeU(&b, r)
		}
	}
	b.WriteByte('"')
	return b.Bytes()
}

func writeU(b *bytes.Buffer, r rune) {
	h := strconv.FormatInt(int64(r), 16)
	b.WriteString(`\u` + strings.Repeat("0", 4-len(h)) + h)
}
