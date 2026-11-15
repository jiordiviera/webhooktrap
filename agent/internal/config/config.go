// Package config reads webhooktrap.yml (NAME-2) with gopkg.in/yaml.v3
// (decision 64). The file is decoded to a node tree and walked strictly: one
// document, a top-level mapping of known keys, scalars, lists of scalars and
// lists of flat probe maps. Anchors, aliases, custom tags, repeated keys and
// unknown keys are definition errors (exit 3).
package config

import (
	"crypto/sha256"
	"encoding/hex"
	"errors"
	"fmt"
	"io"
	"regexp"
	"strconv"
	"strings"
	"time"

	"gopkg.in/yaml.v3"
)

// Probe is one probe entry (PRB-1, PRB-2). Text may hold {{T.id}}
// templates. In a cmd: probe they never expand to the value: they become a
// reference to the environment variable WT_<T>_ID, which carries a value
// checked to be a plain id (AC-1; see the agent README, "Probe templates").
type Probe struct {
	As     string
	Kind   string // cmd | url | sql
	Text   string
	Covers []string
	Show   string
}

// Config is webhooktrap.yml.
type Config struct {
	Target       string
	Provider     string
	Secret       string // environment variable name (from "env:NAME")
	Fixtures     []string
	Scenarios    []string
	Budget       int // 0: pack budget
	Framework    string
	Targets      []string
	Probe        []Probe
	Settle       time.Duration
	ProbeTimeout time.Duration
	Reset        string
	Database     string
	Hash         string // sha256 of the file, for PRB-11 confirmation
}

// Error is a definition error.
type Error struct{ Msg string }

func (e *Error) Error() string { return "webhooktrap.yml: " + e.Msg }

func fail(format string, a ...any) error { return &Error{fmt.Sprintf(format, a...)} }

var (
	nameRe = regexp.MustCompile(`^[a-z0-9_.]{1,48}$`) // PV-9, contracts §5.11
	envRe  = regexp.MustCompile(`^[A-Z_][A-Z0-9_]{0,127}$`)
)

// Parse reads the file content.
func Parse(raw []byte) (*Config, error) {
	sum := sha256.Sum256(raw)
	doc, err := parseYAML(string(raw))
	if err != nil {
		return nil, err
	}
	c := &Config{Hash: hex.EncodeToString(sum[:])}
	for key, v := range doc {
		switch key {
		case "target":
			c.Target, err = scalar(key, v)
		case "provider":
			c.Provider, err = scalar(key, v)
		case "secret":
			var s string
			if s, err = scalar(key, v); err == nil {
				name, ok := strings.CutPrefix(s, "env:")
				if !ok || !envRe.MatchString(name) {
					err = fail("secret must be env:NAME (an environment variable name, never a value)")
				}
				c.Secret = name
			}
		case "database":
			c.Database, err = scalar(key, v)
		case "fixtures":
			c.Fixtures, err = list(key, v)
		case "scenarios":
			c.Scenarios, err = list(key, v)
		case "targets":
			c.Targets, err = list(key, v)
		case "framework":
			c.Framework, err = scalar(key, v)
			if err == nil && c.Framework != "express" && c.Framework != "nextjs" && c.Framework != "laravel" {
				err = fail("framework must be express, nextjs or laravel")
			}
		case "budget":
			var s string
			if s, err = scalar(key, v); err == nil {
				c.Budget, err = strconv.Atoi(strings.TrimSuffix(s, "ms"))
				if err != nil || c.Budget < 100 || c.Budget > 30000 {
					err = fail("budget must be 100 to 30000 ms (BUD-2)")
				}
			}
		case "settle":
			c.Settle, err = duration(key, v, 30*time.Second)
		case "probe_timeout":
			c.ProbeTimeout, err = duration(key, v, 30*time.Second)
		case "reset":
			c.Reset, err = scalar(key, v)
		case "probe":
			c.Probe, err = probes(v)
		default:
			err = fail("unknown key %q", key)
		}
		if err != nil {
			return nil, err
		}
	}
	return c, nil
}

func scalar(key string, v any) (string, error) {
	s, ok := v.(string)
	if !ok {
		return "", fail("%s must be a single value", key)
	}
	return s, nil
}

func list(key string, v any) ([]string, error) {
	switch x := v.(type) {
	case []string:
		return x, nil
	case string:
		if x == "" {
			return nil, nil
		}
		return []string{x}, nil
	}
	return nil, fail("%s must be a list", key)
}

func duration(key string, v any, maxD time.Duration) (time.Duration, error) {
	s, err := scalar(key, v)
	if err != nil {
		return 0, err
	}
	d, err := time.ParseDuration(s)
	if err != nil || d < 0 || d > maxD {
		return 0, fail("%s must be a duration such as 2s, at most %s", key, maxD)
	}
	return d, nil
}

func probes(v any) ([]Probe, error) {
	maps, ok := v.([]map[string]any)
	if !ok {
		return nil, fail("probe must be a list of entries (- as: name)")
	}
	seen := map[string]bool{}
	var out []Probe
	for _, m := range maps {
		var p Probe
		for k, val := range m {
			switch k {
			case "as", "cmd", "url", "sql", "show":
				s, err := scalar("probe."+k, val)
				if err != nil {
					return nil, err
				}
				switch k {
				case "as":
					p.As = s
				case "show":
					p.Show = s
				default:
					if p.Kind != "" {
						return nil, fail("probe entry %q has more than one of sql, cmd, url", p.As)
					}
					p.Kind, p.Text = k, s
				}
			case "covers":
				l, err := list("probe.covers", val)
				if err != nil {
					return nil, err
				}
				p.Covers = l
			default:
				return nil, fail("unknown probe key %q", k)
			}
		}
		if !nameRe.MatchString(p.As) {
			return nil, fail("probe name %q must match [a-z0-9_.], at most 48 characters", p.As)
		}
		if seen[p.As] {
			return nil, fail("probe name %q is used twice", p.As)
		}
		seen[p.As] = true
		if p.Kind == "" || p.Text == "" {
			return nil, fail("probe entry %q needs one of sql, cmd, url", p.As)
		}
		out = append(out, p)
	}
	return out, nil
}

// ErrNotFound is returned when there is no config file.
var ErrNotFound = errors.New("webhooktrap.yml not found")

// ---- YAML (yaml.v3 node tree) ----

// parseYAML decodes one document into plain values: string for scalars
// (null is ""), []string for lists of scalars, []map[string]any for lists of
// flat maps. Anything else is refused.
func parseYAML(src string) (map[string]any, error) {
	dec := yaml.NewDecoder(strings.NewReader(src))
	var root yaml.Node
	if err := dec.Decode(&root); err != nil {
		if errors.Is(err, io.EOF) {
			return map[string]any{}, nil
		}
		return nil, fail("%s", strings.TrimPrefix(err.Error(), "yaml: "))
	}
	var extra yaml.Node
	if err := dec.Decode(&extra); !errors.Is(err, io.EOF) {
		return nil, fail("only one YAML document is allowed")
	}
	if root.Kind != yaml.DocumentNode || len(root.Content) != 1 {
		return nil, fail("expected key: value lines")
	}
	top := root.Content[0]
	if err := plainNode(top); err != nil {
		return nil, err
	}
	if top.Kind == yaml.ScalarNode && top.Tag == "!!null" {
		return map[string]any{}, nil
	}
	if top.Kind != yaml.MappingNode {
		return nil, fail("line %d: expected key: value lines", top.Line)
	}
	return mapping(top, 0)
}

// mapping reads a mapping node; depth 0 is the file, depth 1 a probe entry.
func mapping(n *yaml.Node, depth int) (map[string]any, error) {
	out := map[string]any{}
	for i := 0; i+1 < len(n.Content); i += 2 {
		k, v := n.Content[i], n.Content[i+1]
		if err := plainNode(k); err != nil {
			return nil, err
		}
		if k.Kind != yaml.ScalarNode || !keyRe.MatchString(k.Value) {
			return nil, fail("line %d: expected key: value", k.Line)
		}
		if _, dup := out[k.Value]; dup {
			return nil, fail("line %d: key %q repeated", k.Line, k.Value)
		}
		val, err := nodeValue(v, depth)
		if err != nil {
			return nil, err
		}
		out[k.Value] = val
	}
	return out, nil
}

func nodeValue(n *yaml.Node, depth int) (any, error) {
	if err := plainNode(n); err != nil {
		return nil, err
	}
	switch n.Kind {
	case yaml.ScalarNode:
		if n.Tag == "!!null" {
			return "", nil
		}
		return n.Value, nil
	case yaml.SequenceNode:
		if depth > 0 {
			// Inside a probe entry only lists of scalars (covers).
			return scalarList(n)
		}
		if len(n.Content) > 0 && n.Content[0].Kind == yaml.MappingNode {
			var maps []map[string]any
			for _, item := range n.Content {
				if err := plainNode(item); err != nil {
					return nil, err
				}
				if item.Kind != yaml.MappingNode {
					return nil, fail("line %d: a list mixes values and entries", item.Line)
				}
				m, err := mapping(item, depth+1)
				if err != nil {
					return nil, err
				}
				maps = append(maps, m)
			}
			return maps, nil
		}
		return scalarList(n)
	}
	return nil, fail("line %d: this YAML form is not supported; use a value or a list", n.Line)
}

func scalarList(n *yaml.Node) ([]string, error) {
	out := []string{}
	for _, item := range n.Content {
		if err := plainNode(item); err != nil {
			return nil, err
		}
		if item.Kind != yaml.ScalarNode {
			if item.Kind == yaml.MappingNode {
				return nil, fail("line %d: a list mixes values and entries", item.Line)
			}
			return nil, fail("line %d: nested lists are not supported", item.Line)
		}
		out = append(out, item.Value)
	}
	return out, nil
}

// plainNode refuses anchors, aliases, merge keys and custom tags: the file is
// data a person reads before confirming its commands (PRB-11), so nothing in
// it may expand into something else.
func plainNode(n *yaml.Node) error {
	switch {
	case n.Kind == yaml.AliasNode || n.Anchor != "":
		return fail("line %d: anchors and aliases are not supported", n.Line)
	case n.Tag == "!!merge":
		return fail("line %d: merge keys are not supported", n.Line)
	case n.Tag != "" && !strings.HasPrefix(n.Tag, "!!"):
		return fail("line %d: custom tags are not supported", n.Line)
	case n.Tag == "!!binary":
		return fail("line %d: binary values are not supported", n.Line)
	}
	return nil
}

var keyRe = regexp.MustCompile(`^[A-Za-z_][A-Za-z0-9_]*$`)
