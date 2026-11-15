// Package pack holds provider packs: versioned data files the engine reads for
// every provider value (budget, signing scheme, tolerance, duplicate and retry
// rules, tamper paths, objects, status events, scrub rules, citations and
// finding text templates). The judge reads packs only, never provider names
// (ADR-010 D-8, SJ DEF-2). Pure: the loader reads through io/fs.
//
// The file format here is the engine's draft (packs/<provider>/<version>.json,
// domain-model §8). It is to be reconciled with the shared pack files A06 is
// building; the engine only depends on the Source interface.
package pack

import (
	"encoding/json"
	"errors"
	"fmt"
	"io/fs"
	"path"
	"regexp"
	"sort"
	"strconv"
	"strings"
)

// Source finds packs. The engine never hard-codes where packs live.
type Source interface {
	// Load returns the pack for a provider id. An empty version means the
	// highest version available.
	Load(provider, version string) (*Pack, error)
	// Providers lists the provider ids the source holds, sorted.
	Providers() ([]string, error)
}

// Pack is one provider pack version.
type Pack struct {
	Schema  string `json:"$schema,omitempty"`
	Format  int    `json:"format"`
	ID      string `json:"id"`
	Version string `json:"version"`
	Name    string `json:"name"` // display name, "Stripe"

	Budget    Budget    `json:"budget"`
	Signing   Signing   `json:"signing"`
	Event     Event     `json:"event"`
	Duplicate Rule      `json:"duplicate"`
	Retry     Rule      `json:"retry"`
	Resend    []string  `json:"resend_headers"` // TM-51 provider event headers re-sent as captured
	Tamper    []Tamper  `json:"tamper_paths"`
	Objects   []Object  `json:"objects"`
	Status    []Status  `json:"status_events"`
	Scrub     Scrub     `json:"scrub"`
	IDPattern string    `json:"id_pattern"`
	Citations Citations `json:"citations"`
	Text      Text      `json:"text"`

	// Server-side capture derivation keys (A06, format 1). The engine keeps
	// them opaque so one file and one schema serve both sides.
	Detect   json.RawMessage `json:"detect,omitempty"`
	Sender   json.RawMessage `json:"sender,omitempty"`
	Grouping json.RawMessage `json:"grouping,omitempty"`

	idRe *regexp.Regexp
}

// Budget is the response-time budget (BUD-1).
type Budget struct {
	MS     int    `json:"ms"`
	Source string `json:"source"` // "default" or "<provider> docs"
	// Label is the UI label, "10 s budget · Webhook Trap default".
	Label string `json:"label"`
	// SourceLabel is the parenthesised source in text output.
	SourceLabel string `json:"source_label"`
	// Documented is true when the provider documents the limit (BUD-2:
	// a project budget above it is refused).
	Documented bool `json:"documented"`
}

// Signing describes the provider's signature scheme.
type Signing struct {
	// Scheme names the algorithm in package signing: stripe_v1,
	// github_sha256, shopify_hmac_sha256, standard_webhooks_v1 or none.
	Scheme          string   `json:"scheme"`
	Headers         []string `json:"headers"` // every signature header (UNS-1)
	SignedTimestamp bool     `json:"signed_timestamp"`
	ToleranceS      int      `json:"tolerance_s"`
	// SecretEnv lists default environment variable names for the secret.
	SecretEnv []string `json:"secret_env"`
}

// Event says where event type and event id live.
type Event struct {
	TypePath   string `json:"type_path"`
	TypeHeader string `json:"type_header"`
	IDPath     string `json:"id_path"`
	IDHeader   string `json:"id_header"`
	// API version location (A06); not used by the engine yet.
	APIVersionPath   string `json:"api_version_path,omitempty"`
	APIVersionHeader string `json:"api_version_header,omitempty"`
}

// Rule is a duplicate or retry rule (DUP-1, DUP-2).
type Rule struct {
	// Supported is false when the provider never does it (no automatic retries).
	Supported bool `json:"supported"`
	// Verified is false while the value is "to verify": the scenario is n/a.
	Verified bool `json:"verified"`
	// KeepHeaders are the captured headers kept on a duplicate or retry.
	KeepHeaders []string `json:"keep_headers"`
	Citation    string   `json:"citation"`
}

// Tamper is one tamper path for event types matching EventTypes (globs with *).
type Tamper struct {
	EventTypes []string        `json:"event_types"`
	Path       string          `json:"path"`
	Value      json.RawMessage `json:"value,omitempty"` // replacement for strings and booleans
}

// Object locates typed objects in a body (PRB-4, GRP-1).
type Object struct {
	Path      string `json:"path"`       // "data.object"
	TypeField string `json:"type_field"` // "object"
	IDField   string `json:"id_field"`   // "id"
}

// Status is a status event (PRB-12).
type Status struct {
	EventType  string `json:"event_type"`
	ObjectType string `json:"object_type"`
}

// Scrub holds the pack's scrub rules (SCR-1, SCR-2, SCR-3).
type Scrub struct {
	Paths       []string `json:"paths"`
	KeepHeaders []string `json:"keep_headers"`
}

// Citation is one documentation reference.
type Citation struct {
	Label string `json:"label"`
	URL   string `json:"url"`
}

// Citations are keyed by finding type, plus "scenario:<name>" for scenario copy.
type Citations map[string][]Citation

// Text holds the finding text templates (FND-7a): keyed by finding type,
// likely-cause rule and framework. Placeholders are {param} names (FND-11).
type Text struct {
	Findings    map[string]FindingText       `json:"findings"`
	LikelyCause map[string]string            `json:"likely_cause"`
	Snippets    map[string]map[string]string `json:"snippets"` // type -> framework -> code
}

// FindingText is the prose for one finding type.
type FindingText struct {
	Title string `json:"title"`
	What  string `json:"what"`
	Why   string `json:"why"`
	Fix   string `json:"fix"`
}

// Parse decodes and validates a pack file.
func Parse(raw []byte) (*Pack, error) {
	dec := json.NewDecoder(strings.NewReader(string(raw)))
	dec.DisallowUnknownFields()
	var p Pack
	if err := dec.Decode(&p); err != nil {
		return nil, fmt.Errorf("pack: %w", err)
	}
	if err := p.validate(); err != nil {
		return nil, err
	}
	return &p, nil
}

var idRe = regexp.MustCompile(`^[a-z0-9][a-z0-9_-]{0,47}$`)
var verRe = regexp.MustCompile(`^[0-9]+\.[0-9]+\.[0-9]+$`)

// Schemes known to package signing.
var Schemes = map[string]bool{
	"stripe_v1": true, "github_sha256": true, "shopify_hmac_sha256": true,
	"standard_webhooks_v1": true, "none": true,
}

func (p *Pack) validate() error {
	switch {
	case p.Format != 1:
		return fmt.Errorf("pack %s: unsupported format %d", p.ID, p.Format)
	case !idRe.MatchString(p.ID):
		return fmt.Errorf("pack: invalid id %q", p.ID)
	case !verRe.MatchString(p.Version):
		return fmt.Errorf("pack %s: invalid version %q", p.ID, p.Version)
	case p.Budget.MS < 100 || p.Budget.MS > 30000:
		return fmt.Errorf("pack %s: budget %d ms out of range", p.ID, p.Budget.MS)
	case !Schemes[p.Signing.Scheme]:
		return fmt.Errorf("pack %s: unknown signing scheme %q", p.ID, p.Signing.Scheme)
	case p.Signing.Scheme != "none" && len(p.Signing.Headers) == 0:
		return fmt.Errorf("pack %s: signing scheme without headers", p.ID)
	case p.Signing.SignedTimestamp && p.Signing.ToleranceS <= 0:
		return fmt.Errorf("pack %s: signed timestamp without tolerance", p.ID)
	case p.Event.TypePath == "" && p.Event.TypeHeader == "":
		return fmt.Errorf("pack %s: no event type location", p.ID)
	}
	if p.IDPattern != "" {
		re, err := regexp.Compile(p.IDPattern)
		if err != nil {
			return fmt.Errorf("pack %s: id_pattern: %w", p.ID, err)
		}
		p.idRe = re
	}
	return nil
}

// IsID reports whether v matches the pack's id pattern (SCR-2).
func (p *Pack) IsID(v string) bool { return p.idRe != nil && p.idRe.MatchString(v) }

// TamperPaths returns the tamper paths that apply to an event type, in order.
func (p *Pack) TamperPaths(eventType string) []Tamper {
	var out []Tamper
	for _, t := range p.Tamper {
		for _, g := range t.EventTypes {
			if Glob(g, eventType) {
				out = append(out, t)
				break
			}
		}
	}
	return out
}

// StatusObject returns the object type of a status event, or "".
func (p *Pack) StatusObject(eventType string) string {
	for _, s := range p.Status {
		if s.EventType == eventType {
			return s.ObjectType
		}
	}
	return ""
}

// Citation returns the first citation for a key, or a zero value.
func (p *Pack) Citation(key string) Citation {
	if c := p.Citations[key]; len(c) > 0 {
		return c[0]
	}
	return Citation{}
}

// Glob matches "invoice.*" style patterns (only * is special).
func Glob(pattern, s string) bool {
	if !strings.Contains(pattern, "*") {
		return pattern == s
	}
	parts := strings.Split(pattern, "*")
	if !strings.HasPrefix(s, parts[0]) {
		return false
	}
	s = s[len(parts[0]):]
	for i := 1; i < len(parts); i++ {
		if i == len(parts)-1 {
			return strings.HasSuffix(s, parts[i])
		}
		j := strings.Index(s, parts[i])
		if j < 0 {
			return false
		}
		s = s[j+len(parts[i]):]
	}
	return true
}

// FSSource loads packs from <provider>/<version>.json under an fs.FS.
type FSSource struct{ FS fs.FS }

// ErrNotFound is returned when no pack exists for a provider.
var ErrNotFound = errors.New("pack not found")

// Load implements Source.
func (s FSSource) Load(provider, version string) (*Pack, error) {
	if !idRe.MatchString(provider) {
		return nil, fmt.Errorf("%w: invalid provider id", ErrNotFound)
	}
	if version == "" {
		vs, err := s.versions(provider)
		if err != nil || len(vs) == 0 {
			return nil, fmt.Errorf("%w: %s", ErrNotFound, provider)
		}
		version = vs[len(vs)-1]
	}
	if !verRe.MatchString(version) {
		return nil, fmt.Errorf("%w: invalid version", ErrNotFound)
	}
	raw, err := fs.ReadFile(s.FS, path.Join(provider, version+".json"))
	if err != nil {
		return nil, fmt.Errorf("%w: %s %s", ErrNotFound, provider, version)
	}
	p, err := Parse(raw)
	if err != nil {
		return nil, err
	}
	if p.ID != provider || p.Version != version {
		return nil, fmt.Errorf("pack %s/%s.json declares %s %s", provider, version, p.ID, p.Version)
	}
	return p, nil
}

// Providers implements Source.
func (s FSSource) Providers() ([]string, error) {
	entries, err := fs.ReadDir(s.FS, ".")
	if err != nil {
		return nil, err
	}
	var out []string
	for _, e := range entries {
		if e.IsDir() && idRe.MatchString(e.Name()) {
			out = append(out, e.Name())
		}
	}
	sort.Strings(out)
	return out, nil
}

func (s FSSource) versions(provider string) ([]string, error) {
	entries, err := fs.ReadDir(s.FS, provider)
	if err != nil {
		return nil, err
	}
	var vs []string
	for _, e := range entries {
		v := strings.TrimSuffix(e.Name(), ".json")
		if !e.IsDir() && v != e.Name() && verRe.MatchString(v) {
			vs = append(vs, v)
		}
	}
	sort.Slice(vs, func(i, j int) bool { return semverLess(vs[i], vs[j]) })
	return vs, nil
}

func semverLess(a, b string) bool {
	pa, pb := strings.Split(a, "."), strings.Split(b, ".")
	for i := 0; i < 3; i++ {
		x, _ := strconv.Atoi(pa[i])
		y, _ := strconv.Atoi(pb[i])
		if x != y {
			return x < y
		}
	}
	return false
}
