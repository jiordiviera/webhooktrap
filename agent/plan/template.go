package plan

import (
	"fmt"
	"regexp"
	"sort"
	"strings"

	"github.com/jiordiviera/webhooktrap/agent/internal/jsonpath"
	"github.com/jiordiviera/webhooktrap/agent/pack"
)

var templateRe = regexp.MustCompile(`\{\{\s*([a-z0-9_]+)\.id\s*\}\}`)

// TemplateNames returns the object types used as {{T.id}} in s, sorted.
func TemplateNames(s string) []string {
	seen := map[string]bool{}
	for _, m := range templateRe.FindAllStringSubmatch(s, -1) {
		seen[m[1]] = true
	}
	out := make([]string, 0, len(seen))
	for k := range seen {
		out = append(out, k)
	}
	sort.Strings(out)
	return out
}

// MaxTemplateValue is the longest id a probe template accepts.
const MaxTemplateValue = 128

// PlainID reports whether v may fill a probe template: 1 to 128 characters
// from [A-Za-z0-9_-] (AC-1). Template values come from captured bodies, which
// anyone holding a capture URL can forge, so nothing else ever reaches a probe.
func PlainID(v string) bool {
	if v == "" || len(v) > MaxTemplateValue {
		return false
	}
	for i := 0; i < len(v); i++ {
		c := v[i]
		if !(c == '_' || c == '-' || c >= '0' && c <= '9' || c >= 'a' && c <= 'z' || c >= 'A' && c <= 'Z') {
			return false
		}
	}
	return true
}

// templateValue reports whether v is a plain id that also matches the pack's
// id_pattern when the pack has one.
func templateValue(v string, p *pack.Pack) bool {
	return PlainID(v) && (p.IDPattern == "" || p.IsID(v))
}

// ResolveTemplate resolves every {{T.id}} once, from the selection, in
// capture order (PRB-4): the id of the first object of type T among the
// selected events (pack objects), else the first string field named T whose
// value is an id. Both lookups accept only a plain id (PlainID) that matches
// the pack's id_pattern when there is one (AC-1): a pack object whose id is
// anything else is an error, not skipped. It returns T → id; how the value
// reaches the probe is the caller's job (never inside a shell string). An
// unresolvable template is a definition error (exit 3).
func ResolveTemplate(s string, caps []Capture, p *pack.Pack) (map[string]string, error) {
	out := map[string]string{}
	sorted := append([]Capture(nil), caps...)
	sort.SliceStable(sorted, func(i, j int) bool { return sorted[i].CaptureOrder < sorted[j].CaptureOrder })
	docs := make([]*jsonpath.Doc, len(sorted))
	for i, c := range sorted {
		docs[i], _ = jsonpath.Scan(c.Body)
	}
	for _, t := range TemplateNames(s) {
		v, ok, bad := resolveOne(t, sorted, docs, p)
		switch {
		case bad:
			return nil, &Error{"template_value", fmt.Sprintf("Probe template {{%s.id}}: the value in the selected events is not a plain id.", t)}
		case ok:
			out[t] = v
			continue
		}
		return nil, &Error{"template", fmt.Sprintf("Probe template {{%s.id}} has no value in the selected events.", t)}
	}
	return out, nil
}

// resolveOne returns the value, whether one was found, and whether the pack
// object lookup found a value that is not an id (bad).
func resolveOne(t string, caps []Capture, docs []*jsonpath.Doc, p *pack.Pack) (string, bool, bool) {
	for i, d := range docs {
		if d == nil {
			continue
		}
		for _, o := range p.Objects {
			typ, ok := d.First(join(o.Path, o.TypeField))
			if !ok || typ.Text(caps[i].Body) != t {
				continue
			}
			idField := o.IDField
			if idField == "" {
				idField = "id"
			}
			if id, ok := d.First(join(o.Path, idField)); ok && id.Kind == jsonpath.String {
				v := id.Text(caps[i].Body)
				if !templateValue(v, p) {
					return "", false, true
				}
				return v, true, false
			}
		}
	}
	for i, d := range docs {
		if d == nil {
			continue
		}
		for _, s := range d.Scalars {
			if s.Key == t && s.Kind == jsonpath.String {
				if v := s.Text(caps[i].Body); templateValue(v, p) {
					return v, true, false
				}
			}
		}
	}
	return "", false, false
}

func join(a, b string) string {
	if a == "" {
		return b
	}
	return a + "." + b
}

// ReplaceTemplates replaces every {{T.id}} in s (any spacing the template
// syntax allows) with f(T, offset of the template in s).
func ReplaceTemplates(s string, f func(t string, offset int) string) string {
	var b strings.Builder
	last := 0
	for _, m := range templateRe.FindAllStringSubmatchIndex(s, -1) {
		b.WriteString(s[last:m[0]])
		b.WriteString(f(s[m[2]:m[3]], m[0]))
		last = m[1]
	}
	b.WriteString(s[last:])
	return b.String()
}
