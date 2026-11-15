package plan

import (
	"bytes"
	"errors"
	"strings"

	"github.com/jiordiviera/webhooktrap/agent/internal/jsonpath"
	"github.com/jiordiviera/webhooktrap/agent/pack"
)

// ErrNoTamper means no faithful tampered body could be built.
var ErrNoTamper = errors.New("couldn't build a tampered body")

// TamperBody edits the body in place in the raw bytes (TAMP-1), never
// re-serialising. The first rule that applies wins:
//  1. the first pack tamper path for the event type that exists with a scalar;
//  2. the first numeric field whose name contains amount, total, price or quantity;
//  3. insert "_webhooktrap_tampered":true as the last top-level member.
//
// Fields named id, type, object, created or ending in _id are never changed.
func TamperBody(p *pack.Pack, eventType string, body []byte) ([]byte, Tamper, error) {
	doc, err := jsonpath.Scan(body)
	if err != nil {
		return nil, Tamper{}, err
	}
	// Rule 1.
	for _, t := range p.TamperPaths(eventType) {
		s, ok := doc.First(t.Path)
		if !ok || protected(s.Key) {
			continue
		}
		from := s.Text(body)
		var to []byte
		switch s.Kind {
		case jsonpath.Number:
			to = flipNumber(s.Raw(body))
		case jsonpath.String, jsonpath.Bool:
			if len(t.Value) == 0 {
				continue
			}
			to = bytes.TrimSpace(t.Value)
		default:
			continue
		}
		out := jsonpath.Replace(body, s.Start, s.End, to)
		return out, Tamper{Path: s.Path, From: from, To: display(to)}, nil
	}
	// Rule 2.
	for _, s := range doc.Scalars {
		if s.Kind != jsonpath.Number || protected(s.Key) || !moneyish(s.Key) {
			continue
		}
		to := flipNumber(s.Raw(body))
		out := jsonpath.Replace(body, s.Start, s.End, to)
		return out, Tamper{Path: s.Path, From: string(s.Raw(body)), To: string(to)}, nil
	}
	// Rule 3.
	if !doc.RootObject {
		return nil, Tamper{}, ErrNoTamper
	}
	member := `"` + tamperedMarkerKey + `":true`
	at := doc.RootClose
	if !doc.RootEmpty {
		for at > 0 && strings.IndexByte(" \t\r\n", body[at-1]) >= 0 {
			at--
		}
		member = "," + member
	}
	out := jsonpath.Replace(body, at, at, []byte(member))
	return out, Tamper{Path: tamperedMarkerKey, From: "(absent)", To: "true"}, nil
}

func protected(key string) bool {
	switch key {
	case "id", "type", "object", "created":
		return true
	}
	return strings.HasSuffix(key, "_id")
}

func moneyish(key string) bool {
	k := strings.ToLower(key)
	for _, w := range []string{"amount", "total", "price", "quantity"} {
		if strings.Contains(k, w) {
			return true
		}
	}
	return false
}

// flipNumber: non-zero → 0, zero → 1 (TAMP-1).
func flipNumber(raw []byte) []byte {
	for _, c := range raw {
		if c >= '1' && c <= '9' {
			return []byte("0")
		}
		if c == 'e' || c == 'E' {
			break
		}
	}
	return []byte("1")
}

func display(raw []byte) string {
	s := string(raw)
	if len(s) >= 2 && s[0] == '"' {
		return strings.Trim(s, `"`)
	}
	return s
}
