package scrub

import (
	"encoding/json"
	"fmt"
	"net/netip"
	"net/url"
	"regexp"
	"sort"
	"strings"

	"github.com/jiordiviera/webhooktrap/agent/internal/jsonpath"
	"github.com/jiordiviera/webhooktrap/agent/pack"
)

// Replacement is one scrubbed value (SCR-6). Before is local only.
type Replacement struct {
	File   string `json:"file"`
	Path   string `json:"path"`
	Before string `json:"-"`
	After  string `json:"after"`
	Rule   string `json:"rule"`
}

// Block is a residual match that stops the save (SCR-4).
type Block struct {
	File   string `json:"file"`
	Path   string `json:"path"`
	Reason string `json:"reason"`
}

// Session scrubs the files of one save. Replacement is consistent across
// every file: the same original always gets the same replacement (SCR-3).
type Session struct {
	Pack   *pack.Pack
	Keep   []string // scrub: keep: paths from webhooktrap.yml
	Masker *Masker  // configured signing secrets (SCR-4)

	mapping map[string]string
	counter map[string]int
}

// NewSession starts a save.
func NewSession(p *pack.Pack, keep []string, m *Masker) *Session {
	return &Session{Pack: p, Keep: keep, Masker: m, mapping: map[string]string{}, counter: map[string]int{}}
}

var (
	emailRe   = regexp.MustCompile(`^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$`)
	emailAny  = regexp.MustCompile(`[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}`)
	phoneRe   = regexp.MustCompile(`^\+[0-9]{8,15}$`)
	secretRe  = regexp.MustCompile(`(sk_live_|sk_test_|rk_(live|test)_|whsec_|ghp_|gho_|github_pat_|shpat_|shpss_|xox[abpr]-|AKIA[0-9A-Z]{12}|eyJ[A-Za-z0-9_-]+\.eyJ|-----BEGIN)`)
	randomSeg = regexp.MustCompile(`[A-Za-z0-9_-]{16,}`)
)

// Scrub returns the scrubbed body of one file, the replacements and any
// blocks. A non-empty block list means the save must not happen.
func (s *Session) Scrub(file string, body []byte) ([]byte, []Replacement, []Block, error) {
	doc, err := jsonpath.Scan(body)
	if err != nil {
		return nil, nil, nil, err
	}
	var reps []Replacement
	var blocks []Block
	out := body
	// Edit from the end so earlier offsets stay valid.
	scalars := append([]jsonpath.Scalar(nil), doc.Scalars...)
	sort.SliceStable(scalars, func(i, j int) bool { return scalars[i].Start > scalars[j].Start })
	for _, sc := range scalars {
		if sc.Kind != jsonpath.String {
			continue
		}
		v := sc.Text(body)
		if v == "" || s.kept(sc.Path) || s.Pack.IsID(v) {
			continue
		}
		if secretRe.MatchString(v) || s.Masker.Contains(v) {
			blocks = append(blocks, Block{file, sc.Path, "secret"})
			continue
		}
		rule, repl := s.replace(sc, v)
		if rule == "" {
			continue
		}
		enc, _ := json.Marshal(repl)
		out = jsonpath.Replace(out, sc.Start, sc.End, enc)
		reps = append(reps, Replacement{File: file, Path: sc.Path, Before: v, After: repl, Rule: rule})
	}
	sort.SliceStable(reps, func(i, j int) bool { return reps[i].Path < reps[j].Path })
	blocks = append(blocks, s.residual(file, out)...)
	return out, reps, blocks, nil
}

func (s *Session) kept(path string) bool {
	for _, k := range s.Keep {
		if jsonpath.Match(path, k) {
			return true
		}
	}
	return false
}

func (s *Session) next(kind string) int {
	s.counter[kind]++
	return s.counter[kind]
}

// consistent returns the replacement already chosen for v, or makes one.
func (s *Session) consistent(kind, v string, gen func(n int) string) string {
	key := kind + "\x00" + v
	if r, ok := s.mapping[key]; ok {
		return r
	}
	r := gen(s.next(kind))
	s.mapping[key] = r
	return r
}

func (s *Session) replace(sc jsonpath.Scalar, v string) (string, string) {
	key := strings.ToLower(sc.Key)
	path := strings.ToLower(sc.Path)
	switch {
	case emailRe.MatchString(v):
		return "email", s.consistent("email", v, func(n int) string { return fmt.Sprintf("customer%d@example.test", n) })
	case phoneRe.MatchString(v):
		return "phone", s.consistent("phone", v, func(n int) string { return fmt.Sprintf("+1555555%04d", n) })
	case isIP(v):
		return "ip", s.consistent("ip", v, func(n int) string {
			if strings.Contains(v, ":") {
				return fmt.Sprintf("2001:db8::%d", n)
			}
			return fmt.Sprintf("192.0.2.%d", n)
		})
	case key == "fingerprint":
		return "card_fingerprint", s.consistent("fp", v, func(n int) string { return fmt.Sprintf("fp_redacted%d", n) })
	case key == "last4":
		return "card_last4", "4242"
	case personName(key, path):
		return "name", s.consistent("name", v, func(n int) string { return fmt.Sprintf("Test Customer %d", n) })
	}
	if u, ok := randomURL(v); ok {
		return "url", u
	}
	for _, rule := range s.Pack.Scrub.Paths {
		if jsonpath.Match(sc.Path, rule) {
			return "pack_path", s.consistent("value", v, func(n int) string { return fmt.Sprintf("redacted%d", n) })
		}
	}
	return "", ""
}

func personName(key, path string) bool {
	switch key {
	case "name", "first_name", "last_name", "customer_name":
		return key != "name" || strings.Contains(path, "customer") || strings.Contains(path, "billing") || strings.Contains(path, "shipping")
	}
	return strings.HasSuffix(key, "_name") && (strings.Contains(path, "customer") || strings.Contains(path, "billing") || strings.Contains(path, "shipping"))
}

func isIP(v string) bool {
	_, err := netip.ParseAddr(v)
	return err == nil
}

// randomURL applies the URL rule: a path segment of ≥ 16 random-looking
// characters → host's last label becomes .test, path becomes /redacted.
func randomURL(v string) (string, bool) {
	u, err := url.Parse(v)
	if err != nil || (u.Scheme != "http" && u.Scheme != "https") || u.Host == "" {
		return "", false
	}
	random := false
	for _, seg := range strings.Split(u.Path, "/") {
		if randomSeg.MatchString(seg) && hasDigit(seg) && hasLetter(seg) {
			random = true
		}
	}
	if !random {
		return "", false
	}
	host := u.Hostname()
	if i := strings.LastIndexByte(host, '.'); i >= 0 {
		host = host[:i] + ".test"
	} else {
		host += ".test"
	}
	return u.Scheme + "://" + host + "/redacted", true
}

func hasDigit(s string) bool { return strings.ContainsAny(s, "0123456789") }
func hasLetter(s string) bool {
	return strings.ContainsFunc(s, func(r rune) bool { return r >= 'A' && r <= 'z' })
}

// residual runs the detector again on the output (SCR-4).
func (s *Session) residual(file string, out []byte) []Block {
	doc, err := jsonpath.Scan(out)
	if err != nil {
		return []Block{{file, "", "invalid_output"}}
	}
	var blocks []Block
	for _, sc := range doc.Scalars {
		if sc.Kind != jsonpath.String || s.kept(sc.Path) {
			continue
		}
		v := sc.Text(out)
		switch {
		case secretRe.MatchString(v), s.Masker.Contains(v):
			// already reported as a secret block
		case emailAny.MatchString(v) && !strings.HasSuffix(v, "@example.test"):
			blocks = append(blocks, Block{file, sc.Path, "email"})
		case phoneRe.MatchString(v) && !strings.HasPrefix(v, "+1555555"):
			blocks = append(blocks, Block{file, sc.Path, "phone"})
		}
	}
	return blocks
}
