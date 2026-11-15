// Package jsonpath walks a raw JSON document and reports every scalar with its
// path and exact byte span, so callers can edit values in place without
// re-serialising (TAMP-1, SCR-1). Pure: no I/O.
//
// Paths join object keys with "." and write array elements as "[]" appended to
// the array's path (SHP-1): data.object.lines.data[].amount.
package jsonpath

import (
	"encoding/json"
	"errors"
	"strings"
)

// Kind is the JSON type of a scalar.
type Kind byte

const (
	String Kind = 's'
	Number Kind = 'n'
	Bool   Kind = 'b'
	Null   Kind = 'z'
)

// Scalar is one scalar value of the document.
type Scalar struct {
	Path  string // full path, "data.object.amount_paid"
	Key   string // last object key ("" for array elements and the root)
	Kind  Kind
	Start int // byte offset of the token (strings include their quotes)
	End   int // byte offset after the token
}

// Raw returns the scalar's token bytes.
func (s Scalar) Raw(body []byte) []byte { return body[s.Start:s.End] }

// Text returns the decoded value of a string scalar, or the raw token otherwise.
func (s Scalar) Text(body []byte) string {
	raw := body[s.Start:s.End]
	if s.Kind == String {
		var v string
		if json.Unmarshal(raw, &v) == nil {
			return v
		}
	}
	return string(raw)
}

// Doc is a scanned document.
type Doc struct {
	Scalars []Scalar
	// RootObject is true when the top-level value is an object.
	RootObject bool
	// RootClose is the offset of the top-level object's closing brace.
	RootClose int
	// RootEmpty is true for "{}".
	RootEmpty bool
}

// ErrInvalid is returned for anything that is not one well-formed JSON value.
var ErrInvalid = errors.New("body isn't JSON")

// Scan parses body. It refuses anything json.Valid refuses.
func Scan(body []byte) (*Doc, error) {
	if !json.Valid(body) {
		return nil, ErrInvalid
	}
	s := &scanner{b: body, doc: &Doc{}}
	s.ws()
	if s.i < len(body) && body[s.i] == '{' {
		s.doc.RootObject = true
	}
	if err := s.value("", ""); err != nil {
		return nil, err
	}
	return s.doc, nil
}

// First returns the first scalar (document order) whose path equals path.
func (d *Doc) First(path string) (Scalar, bool) {
	for _, s := range d.Scalars {
		if s.Path == path {
			return s, true
		}
	}
	return Scalar{}, false
}

// Match reports whether a scalar path matches a rule path. A rule matches when
// its segments equal the last segments of the path ("customer_email" matches
// "data.object.customer_email"); a trailing ".*" matches any descendant
// ("billing_details.*"). Array markers are ignored for matching.
func Match(path, rule string) bool {
	p := splitPath(path)
	desc := strings.HasSuffix(rule, ".*")
	r := splitPath(strings.TrimSuffix(rule, ".*"))
	if len(r) == 0 {
		return false
	}
	for start := 0; start+len(r) <= len(p); start++ {
		ok := true
		for j := range r {
			if p[start+j] != r[j] {
				ok = false
				break
			}
		}
		if !ok {
			continue
		}
		end := start + len(r)
		if desc && end < len(p) {
			return true
		}
		if !desc && end == len(p) {
			return true
		}
	}
	return false
}

func splitPath(p string) []string {
	p = strings.ReplaceAll(p, "[]", "")
	if p == "" {
		return nil
	}
	return strings.Split(p, ".")
}

type scanner struct {
	b   []byte
	i   int
	doc *Doc
	dep int
}

func (s *scanner) ws() {
	for s.i < len(s.b) {
		switch s.b[s.i] {
		case ' ', '\t', '\n', '\r':
			s.i++
		default:
			return
		}
	}
}

func join(path, key string) string {
	if path == "" {
		return key
	}
	return path + "." + key
}

func (s *scanner) value(path, key string) error {
	s.ws()
	if s.i >= len(s.b) {
		return ErrInvalid
	}
	s.dep++
	defer func() { s.dep-- }()
	if s.dep > 256 {
		return ErrInvalid
	}
	switch c := s.b[s.i]; {
	case c == '{':
		root := s.dep == 1
		s.i++
		s.ws()
		if s.b[s.i] == '}' {
			if root {
				s.doc.RootClose, s.doc.RootEmpty = s.i, true
			}
			s.i++
			return nil
		}
		for {
			s.ws()
			start := s.i
			if err := s.str(); err != nil {
				return err
			}
			var k string
			if err := json.Unmarshal(s.b[start:s.i], &k); err != nil {
				return ErrInvalid
			}
			s.ws()
			s.i++ // ':'
			if err := s.value(join(path, k), k); err != nil {
				return err
			}
			s.ws()
			if s.b[s.i] == ',' {
				s.i++
				continue
			}
			if root {
				s.doc.RootClose = s.i
			}
			s.i++ // '}'
			return nil
		}
	case c == '[':
		s.i++
		s.ws()
		if s.b[s.i] == ']' {
			s.i++
			return nil
		}
		for {
			if err := s.value(path+"[]", ""); err != nil {
				return err
			}
			s.ws()
			if s.b[s.i] == ',' {
				s.i++
				continue
			}
			s.i++ // ']'
			return nil
		}
	case c == '"':
		start := s.i
		if err := s.str(); err != nil {
			return err
		}
		s.add(path, key, String, start)
	case c == 't':
		start := s.i
		s.i += 4
		s.add(path, key, Bool, start)
	case c == 'f':
		start := s.i
		s.i += 5
		s.add(path, key, Bool, start)
	case c == 'n':
		start := s.i
		s.i += 4
		s.add(path, key, Null, start)
	default:
		start := s.i
		for s.i < len(s.b) && strings.IndexByte("+-0123456789.eE", s.b[s.i]) >= 0 {
			s.i++
		}
		s.add(path, key, Number, start)
	}
	return nil
}

func (s *scanner) add(path, key string, k Kind, start int) {
	s.doc.Scalars = append(s.doc.Scalars, Scalar{Path: path, Key: key, Kind: k, Start: start, End: s.i})
}

func (s *scanner) str() error {
	if s.b[s.i] != '"' {
		return ErrInvalid
	}
	s.i++
	for s.i < len(s.b) {
		switch s.b[s.i] {
		case '\\':
			s.i += 2
		case '"':
			s.i++
			return nil
		default:
			s.i++
		}
	}
	return ErrInvalid
}

// Replace returns body with the bytes [start, end) replaced by repl.
func Replace(body []byte, start, end int, repl []byte) []byte {
	out := make([]byte, 0, len(body)-(end-start)+len(repl))
	out = append(out, body[:start]...)
	out = append(out, repl...)
	return append(out, body[end:]...)
}
