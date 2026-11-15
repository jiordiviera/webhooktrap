package scrub

import (
	"encoding/base64"
	"encoding/hex"
	"net/url"
	"strings"

	"github.com/jiordiviera/webhooktrap/agent/signing"
)

// MinEncodedLen is the secret length from which encoded forms are masked too (SCR-4).
const MinEncodedLen = 16

// Masker replaces secret values in any text the engine prints or writes. A
// secret is replaced raw; secrets of 16 characters or more are also replaced
// in base64 (standard and URL), hex (both cases) and URL-encoded form.
type Masker struct {
	forms []form
}

type form struct{ value, name string }

// NewMasker builds a masker for the held secrets.
func NewMasker(secrets ...signing.Secret) *Masker {
	m := &Masker{}
	for _, s := range secrets {
		v := string(s.Reveal())
		if v == "" {
			continue
		}
		label := "[" + s.Name + "]"
		m.forms = append(m.forms, form{v, label})
		if len(v) >= MinEncodedLen {
			for _, e := range []string{
				base64.StdEncoding.EncodeToString([]byte(v)),
				base64.RawStdEncoding.EncodeToString([]byte(v)),
				base64.URLEncoding.EncodeToString([]byte(v)),
				base64.RawURLEncoding.EncodeToString([]byte(v)),
				hex.EncodeToString([]byte(v)),
				strings.ToUpper(hex.EncodeToString([]byte(v))),
				url.QueryEscape(v),
			} {
				if e != v {
					m.forms = append(m.forms, form{e, label})
				}
			}
		}
	}
	return m
}

// Mask returns s with every known secret form replaced by its name.
func (m *Masker) Mask(s string) string {
	if m == nil {
		return s
	}
	for _, f := range m.forms {
		s = strings.ReplaceAll(s, f.value, f.name)
	}
	return s
}

// Contains reports whether s holds any known secret form.
func (m *Masker) Contains(s string) bool {
	if m == nil {
		return false
	}
	for _, f := range m.forms {
		if strings.Contains(s, f.value) {
			return true
		}
	}
	return false
}
