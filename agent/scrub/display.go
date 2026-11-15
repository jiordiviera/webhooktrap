package scrub

import (
	"crypto/hmac"
	"crypto/sha256"
	"encoding/hex"
	"regexp"
)

var smallCount = regexp.MustCompile(`^-?(0|[1-9][0-9]{0,3})$`)

// Display is the single choke point (PV-6) that turns a raw probe value into
// what may leave the machine (PV-2, PV-3, PV-4): a small count (−9 999 to
// 9 999) as is, anything else a code "#" + hex of HMAC-SHA-256(salt, name ‖ 0 ‖
// value), 6 hex characters (bits 24) or 12 (bits 48). The salt is per run,
// from the OS CSPRNG, never derived from the seed and never written (D-3a).
func Display(name, raw string, salt []byte, bits int) string {
	if smallCount.MatchString(raw) && raw != "-0" {
		return raw
	}
	m := hmac.New(sha256.New, salt)
	m.Write([]byte(name))
	m.Write([]byte{0})
	m.Write([]byte(raw))
	n := 6
	if bits == 48 {
		n = 12
	}
	return "#" + hex.EncodeToString(m.Sum(nil))[:n]
}

// LabelBits returns 24, or 48 when two distinct values of the run share a
// 6-hex code (PV-4).
func LabelBits(values map[string][]string, salt []byte) int {
	for name, vs := range values {
		seen := map[string]string{}
		for _, v := range vs {
			c := Display(name, v, salt, 24)
			if c[0] != '#' {
				continue
			}
			if prev, ok := seen[c]; ok && prev != v {
				return 48
			}
			seen[c] = v
		}
	}
	return 24
}
