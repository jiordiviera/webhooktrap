// Package schematest validates JSON documents against the JSON Schemas under
// contracts/ (A-12: the engine validates its messages against the server's
// schemas in its own tests). It implements the subset of draft 2020-12 those
// schemas use: $ref (same file or sibling file, #/$defs/...), type, const,
// enum, properties, required, additionalProperties (bool or schema),
// propertyNames, maxProperties, items, prefixItems, minItems, maxItems,
// pattern, minLength, maxLength, minimum, maximum, anyOf, oneOf. Unknown
// keywords are ignored. Test-only: imported from _test files.
package schematest

import (
	"encoding/json"
	"fmt"
	"os"
	"path/filepath"
	"regexp"
	"sort"
	"strings"
	"unicode/utf8"
)

// Dir returns contracts/<sub> from the repository root, or "" when absent.
func Dir(sub string) string {
	wd, _ := os.Getwd()
	for d := wd; ; d = filepath.Dir(d) {
		p := filepath.Join(d, "contracts", sub)
		if st, err := os.Stat(p); err == nil && st.IsDir() {
			return p
		}
		if filepath.Dir(d) == d {
			return ""
		}
	}
}

// Validator loads schema files from one directory.
type Validator struct {
	dir   string
	files map[string]map[string]any
}

// New returns a validator over dir.
func New(dir string) *Validator { return &Validator{dir: dir, files: map[string]map[string]any{}} }

func (v *Validator) file(name string) (map[string]any, error) {
	if f, ok := v.files[name]; ok {
		return f, nil
	}
	raw, err := os.ReadFile(filepath.Join(v.dir, name))
	if err != nil {
		return nil, err
	}
	var m map[string]any
	if err := json.Unmarshal(raw, &m); err != nil {
		return nil, fmt.Errorf("%s: %w", name, err)
	}
	v.files[name] = m
	return m, nil
}

// Validate checks doc (JSON bytes) against schema file name. It returns every
// violation found.
func (v *Validator) Validate(name string, doc []byte) ([]string, error) {
	root, err := v.file(name)
	if err != nil {
		return nil, err
	}
	var x any
	dec := json.NewDecoder(strings.NewReader(string(doc)))
	dec.UseNumber()
	if err := dec.Decode(&x); err != nil {
		return nil, err
	}
	var errs []string
	v.check(name, root, x, "$", &errs)
	return errs, nil
}

func (v *Validator) resolve(cur string, ref string) (string, map[string]any, error) {
	file, frag, _ := strings.Cut(ref, "#")
	if file == "" {
		file = cur
	}
	node, err := v.file(file)
	if err != nil {
		return "", nil, err
	}
	var n any = node
	for _, part := range strings.Split(strings.TrimPrefix(frag, "/"), "/") {
		if part == "" {
			continue
		}
		m, ok := n.(map[string]any)
		if !ok {
			return "", nil, fmt.Errorf("bad ref %s", ref)
		}
		n = m[part]
	}
	m, ok := n.(map[string]any)
	if !ok {
		return "", nil, fmt.Errorf("bad ref %s", ref)
	}
	return file, m, nil
}

func typeOf(x any) string {
	switch t := x.(type) {
	case nil:
		return "null"
	case bool:
		return "boolean"
	case string:
		return "string"
	case json.Number:
		if !strings.ContainsAny(t.String(), ".eE") {
			return "integer"
		}
		return "number"
	case []any:
		return "array"
	case map[string]any:
		return "object"
	}
	return "?"
}

func equal(a, b any) bool {
	ja, _ := json.Marshal(a)
	jb, _ := json.Marshal(b)
	return string(ja) == string(jb)
}

func num(x any) (float64, bool) {
	switch t := x.(type) {
	case json.Number:
		f, err := t.Float64()
		return f, err == nil
	case float64:
		return t, true
	}
	return 0, false
}

var reCache = map[string]*regexp.Regexp{}

func (v *Validator) check(file string, s map[string]any, x any, at string, errs *[]string) {
	bad := func(f string, a ...any) { *errs = append(*errs, at+": "+fmt.Sprintf(f, a...)) }
	if ref, ok := s["$ref"].(string); ok {
		f, rs, err := v.resolve(file, ref)
		if err != nil {
			bad("%v", err)
			return
		}
		v.check(f, rs, x, at, errs)
	}
	if t, ok := s["type"]; ok {
		var types []string
		switch tt := t.(type) {
		case string:
			types = []string{tt}
		case []any:
			for _, e := range tt {
				types = append(types, e.(string))
			}
		}
		got, match := typeOf(x), false
		for _, w := range types {
			if w == got || (w == "number" && got == "integer") {
				match = true
			}
		}
		if !match {
			bad("type %s, want %v", got, types)
			return
		}
	}
	if c, ok := s["const"]; ok && !equal(c, x) {
		bad("const %v, got %v", c, x)
	}
	if e, ok := s["enum"].([]any); ok {
		found := false
		for _, w := range e {
			found = found || equal(w, x)
		}
		if !found {
			bad("%v not in enum %v", x, e)
		}
	}
	for _, kw := range []string{"anyOf", "oneOf"} {
		alts, ok := s[kw].([]any)
		if !ok {
			continue
		}
		n := 0
		var sub []string
		for _, a := range alts {
			var e []string
			v.check(file, a.(map[string]any), x, at, &e)
			if len(e) == 0 {
				n++
			}
			sub = append(sub, e...)
		}
		if (kw == "anyOf" && n == 0) || (kw == "oneOf" && n != 1) {
			bad("%s matched %d alternatives: %s", kw, n, strings.Join(sub, "; "))
		}
	}
	switch t := x.(type) {
	case string:
		if p, ok := s["pattern"].(string); ok {
			re := reCache[p]
			if re == nil {
				re = regexp.MustCompile(p)
				reCache[p] = re
			}
			if !re.MatchString(t) {
				bad("%q doesn't match %s", t, p)
			}
		}
		if m, ok := num(s["maxLength"]); ok && float64(utf8.RuneCountInString(t)) > m {
			bad("longer than %v", m)
		}
		if m, ok := num(s["minLength"]); ok && float64(utf8.RuneCountInString(t)) < m {
			bad("shorter than %v", m)
		}
	case json.Number:
		f, _ := t.Float64()
		if m, ok := num(s["minimum"]); ok && f < m {
			bad("%v below %v", f, m)
		}
		if m, ok := num(s["maximum"]); ok && f > m {
			bad("%v above %v", f, m)
		}
	case []any:
		if m, ok := num(s["maxItems"]); ok && float64(len(t)) > m {
			bad("%d items, max %v", len(t), m)
		}
		if m, ok := num(s["minItems"]); ok && float64(len(t)) < m {
			bad("%d items, min %v", len(t), m)
		}
		prefix, _ := s["prefixItems"].([]any)
		for i, e := range t {
			if i < len(prefix) {
				v.check(file, prefix[i].(map[string]any), e, fmt.Sprintf("%s[%d]", at, i), errs)
			} else if it, ok := s["items"].(map[string]any); ok {
				v.check(file, it, e, fmt.Sprintf("%s[%d]", at, i), errs)
			}
		}
	case map[string]any:
		props, _ := s["properties"].(map[string]any)
		if req, ok := s["required"].([]any); ok {
			for _, r := range req {
				if _, has := t[r.(string)]; !has {
					bad("missing %q", r)
				}
			}
		}
		if m, ok := num(s["maxProperties"]); ok && float64(len(t)) > m {
			bad("more than %v properties", m)
		}
		keys := make([]string, 0, len(t))
		for k := range t {
			keys = append(keys, k)
		}
		sort.Strings(keys)
		for _, k := range keys {
			if pn, ok := s["propertyNames"].(map[string]any); ok {
				v.check(file, pn, k, at+"."+k+"(name)", errs)
			}
			if ps, ok := props[k].(map[string]any); ok {
				v.check(file, ps, t[k], at+"."+k, errs)
				continue
			}
			switch ap := s["additionalProperties"].(type) {
			case bool:
				if !ap {
					bad("unknown field %q", k)
				}
			case map[string]any:
				v.check(file, ap, t[k], at+"."+k, errs)
			}
		}
	}
}
