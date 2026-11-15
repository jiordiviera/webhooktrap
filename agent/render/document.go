package render

import (
	"bytes"
	"encoding/json"

	"github.com/jiordiviera/webhooktrap/agent/judge"
	"github.com/jiordiviera/webhooktrap/agent/observe"
	"github.com/jiordiviera/webhooktrap/agent/pack"
	"github.com/jiordiviera/webhooktrap/agent/plan"
	"github.com/jiordiviera/webhooktrap/agent/scrub"
)

// SchemaVersion of the run document. Draft until contracts/run/v1/run.schema.json exists.
const SchemaVersion = "1.0.0-draft.1"

// ProbeEntry describes one probe entry in the document (no definition, PV-8).
type ProbeEntry struct {
	Name        string   `json:"name"`
	DisplayMode string   `json:"display_mode"` // count | code (opted_* not built yet)
	Covers      []string `json:"covers,omitempty"`
}

// Target is the display form of the target.
type Target struct {
	Scheme string `json:"scheme"`
	Host   string `json:"host"`
	Port   int    `json:"port"`
	Path   string `json:"path"`
}

// String is the URL as shown.
func (t Target) String() string {
	def := (t.Scheme == "http" && t.Port == 80) || (t.Scheme == "https" && t.Port == 443)
	h := t.Host
	if bytes.IndexByte([]byte(h), ':') >= 0 {
		h = "[" + h + "]"
	}
	if !def {
		h += ":" + itoa(t.Port)
	}
	return t.Scheme + "://" + h + t.Path
}

// Meta is what the CLI knows about the run beyond plan, observations and verdict.
type Meta struct {
	RunID        string
	StartedAt    string // RFC 3339 UTC with ms
	FinishedAt   string
	Target       Target
	Executor     string // agent | ci
	Source       string // cli | ci | web
	AgentName    string
	AgentVersion string
	ProbeEntries []ProbeEntry
	SideEffects  bool
	Warnings     []string
	// Salt keys PV-2 codes. Never serialised (D-3a, JSON-2).
	Salt []byte
}

// Input bundles everything render reads. Render is pure over it (D-11).
type Input struct {
	Plan    *plan.Plan
	Obs     *observe.Run
	Verdict *judge.Verdict
	Pack    *pack.Pack
	Meta    Meta
}

// Document is the local-profile run document (SJ §13, ADR-010 §11).
type Document struct {
	SchemaVersion string        `json:"schema_version"`
	Profile       string        `json:"profile"`
	Run           DocRun        `json:"run"`
	Events        []plan.Event  `json:"events"`
	Scenarios     []DocScenario `json:"scenarios"`
	Deliveries    []DocDelivery `json:"deliveries"`
	Findings      []DocFinding  `json:"findings"`
	Counts        judge.Counts  `json:"counts"`
	NotChecked    []string      `json:"not_checked"`
	Warnings      []string      `json:"warnings"`
	// Plan and Observations make the file rejudgeable (D-1, JSON-4).
	Plan         *plan.Plan   `json:"plan"`
	Observations *observe.Run `json:"observations"`
}

// DocRun is the run object.
type DocRun struct {
	ID                 string            `json:"id"`
	Status             string            `json:"status"`
	StatusReason       *string           `json:"status_reason"`
	ExitCode           int               `json:"exit_code"`
	FailOn             string            `json:"fail_on"`
	FailOnInconclusive bool              `json:"fail_on_inconclusive"`
	StartedAt          string            `json:"started_at"`
	FinishedAt         string            `json:"finished_at"`
	DurationMS         int64             `json:"duration_ms"`
	Seed               string            `json:"seed"`
	PlanHash           string            `json:"plan_hash"`
	Provider           string            `json:"provider"`
	Pack               plan.PackRef      `json:"pack"`
	Budget             plan.Budget       `json:"budget"`
	Target             Target            `json:"target"`
	Provenance         map[string]string `json:"provenance"`
	Agent              map[string]string `json:"agent"`
	Framework          *judge.Framework  `json:"framework"`
	Levels             []int             `json:"levels"`
	ProbeEntries       []ProbeEntry      `json:"probe_entries"`
	ProbeDisplayPolicy string            `json:"probe_display_policy"`
	LabelBits          int               `json:"label_bits"`
	ResetConfigured    bool              `json:"reset_configured"`
	OrderDependent     bool              `json:"order_dependent"`
	SideEffectsAck     bool              `json:"side_effects_acknowledged"`
	Burst              map[string]*int64 `json:"burst,omitempty"`
}

// DocScenario is one scenarios[] element.
type DocScenario struct {
	Name        string      `json:"name"`
	Result      string      `json:"result"`
	Reason      *string     `json:"reason"`
	Deliveries  []int       `json:"deliveries"`
	Findings    []string    `json:"findings"`
	Reading     *DocReading `json:"reading"`
	ReadingText string      `json:"reading_text"`
}

// DocReading is the scenario reading with display and local values.
type DocReading struct {
	Kind    string             `json:"kind"`
	Probe   string             `json:"probe"`
	Display map[string]*string `json:"display"`
	Before  *string            `json:"before"`
	After   *string            `json:"after"`
}

// DocDelivery is one deliveries[] element.
type DocDelivery struct {
	N          int               `json:"n"`
	Scenario   string            `json:"scenario"`
	EventRef   string            `json:"event_ref"`
	Mode       string            `json:"mode"`
	OffsetMS   *int64            `json:"offset_ms"`
	Signing    plan.Signing      `json:"signing"`
	Tamper     *plan.Tamper      `json:"tamper"`
	Status     *int              `json:"status"`
	Transport  *string           `json:"transport"`
	LatencyMS  *int64            `json:"latency_ms"`
	OverBudget bool              `json:"over_budget"`
	Response   *observe.Response `json:"response"`
	State      *DocState         `json:"state"`
	Result     string            `json:"result"`
	Severity   *string           `json:"severity"`
	Completed  bool              `json:"completed"`
}

// DocState is a delivery's state.
type DocState struct {
	Readings  []DocStateReading `json:"readings"`
	Failed    bool              `json:"failed"`
	Unsettled bool              `json:"unsettled"`
}

// DocStateReading holds outcomes, display values and (local) raw values.
type DocStateReading struct {
	Name            string             `json:"name"`
	Changed         bool               `json:"changed"`
	MatchesExpected *bool              `json:"matches_expected"`
	Flagged         bool               `json:"flagged"`
	Display         map[string]*string `json:"display"`
	Before          *string            `json:"before"`
	After           *string            `json:"after"`
	Expected        *string            `json:"expected"`
}

// DocFinding is one findings[] element, with local prose.
type DocFinding struct {
	judge.Finding
	Title           string         `json:"title"`
	What            string         `json:"what"`
	Why             string         `json:"why"`
	Citation        *pack.Citation `json:"citation"`
	Fix             DocFix         `json:"fix"`
	LikelyCauseText *string        `json:"likely_cause_text"`
}

// DocFix is the fix text and optional snippet.
type DocFix struct {
	Text    string  `json:"text"`
	Snippet *string `json:"snippet"`
}

// Build assembles the local-profile document.
func Build(in Input) *Document {
	v, pl, m := in.Verdict, in.Plan, in.Meta
	bits := labelBits(in)
	disp := func(name string, raw *string) *string {
		if raw == nil {
			return nil
		}
		d := scrub.Display(name, *raw, m.Salt, bits)
		return &d
	}
	doc := &Document{
		SchemaVersion: SchemaVersion, Profile: "local",
		Events: pl.Events, Counts: v.Counts, NotChecked: notCheckedText(v, in.Obs),
		Warnings: append([]string{}, m.Warnings...), Plan: pl, Observations: in.Obs,
	}
	var reason *string
	if v.Reason != "" {
		reason = strp(v.Reason)
	}
	entries := m.ProbeEntries
	if entries == nil {
		entries = []ProbeEntry{}
	}
	doc.Run = DocRun{
		ID: m.RunID, Status: v.Status, StatusReason: reason, ExitCode: v.ExitCode, FailOn: v.FailOn,
		FailOnInconclusive: v.FailOnInconclusive, StartedAt: m.StartedAt, FinishedAt: m.FinishedAt,
		DurationMS: in.Obs.DurationMS, Seed: pl.Seed, PlanHash: pl.Hash, Provider: pl.Provider, Pack: pl.Pack,
		Budget: pl.Budget, Target: m.Target,
		Provenance: map[string]string{"executor": m.Executor, "source": m.Source},
		Agent:      map[string]string{"name": m.AgentName, "version": m.AgentVersion},
		Framework:  v.Framework, Levels: v.Levels, ProbeEntries: entries, ProbeDisplayPolicy: "strict",
		LabelBits: bits, ResetConfigured: in.Obs.ResetConfigured, OrderDependent: v.OrderDependent,
		SideEffectsAck: m.SideEffects,
	}
	if m.AgentName != "" {
		doc.Run.Provenance["agent_name"] = m.AgentName
	}
	for _, s := range v.Scenarios {
		ds := DocScenario{Name: s.Name, Result: s.Result, Deliveries: s.Deliveries, Findings: s.Findings, ReadingText: scenarioDetail(in, s, Unicode)}
		if s.Reason != "" {
			ds.Reason = strp(s.Reason)
		}
		if s.Reading != nil {
			ds.Reading = &DocReading{Kind: s.Reading.Kind, Probe: s.Reading.Probe, Before: s.Reading.Before, After: s.Reading.After,
				Display: map[string]*string{"before": disp(s.Reading.Probe, s.Reading.Before), "after": disp(s.Reading.Probe, s.Reading.After)}}
		}
		doc.Scenarios = append(doc.Scenarios, ds)
	}
	for i, pd := range pl.Deliveries {
		o := in.Obs.Deliveries[i]
		dr := v.Deliveries[i]
		dd := DocDelivery{N: pd.N, Scenario: string(pd.Scenario), EventRef: pd.EventRef, Mode: pd.Mode,
			Signing: pd.Signing, Tamper: pd.Tamper, OverBudget: dr.OverBudget, Response: o.Response,
			Result: dr.Result, Completed: o.Completed}
		if o.Sent {
			dd.OffsetMS = i64p(o.OffsetMS)
		}
		if o.Responded {
			dd.Status, dd.LatencyMS = intp(o.Status), i64p(o.LatencyMS)
		}
		if o.Transport != "" {
			dd.Transport = strp(o.Transport)
		}
		if dr.Severity != "" {
			dd.Severity = strp(dr.Severity)
		}
		if o.State != nil {
			st := &DocState{Failed: o.State.Failed, Unsettled: o.State.Unsettled, Readings: []DocStateReading{}}
			for _, r := range dr.Readings {
				st.Readings = append(st.Readings, DocStateReading{Name: r.Name, Changed: r.Changed, MatchesExpected: r.MatchesExpected,
					Flagged: r.Flagged, Before: r.Before, After: r.After, Expected: r.Expected,
					Display: map[string]*string{"before": disp(r.Name, r.Before), "after": disp(r.Name, r.After), "expected": disp(r.Name, r.Expected)}})
			}
			dd.State = st
		}
		doc.Deliveries = append(doc.Deliveries, dd)
	}
	for _, f := range v.Findings {
		t := Prose(in, &f)
		df := DocFinding{Finding: f, Title: t.Title, What: t.What, Why: t.Why, Fix: DocFix{Text: t.Fix}}
		if t.Citation.Label != "" {
			c := t.Citation
			df.Citation = &c
		}
		if t.Snippet != "" {
			df.Fix.Snippet = strp(t.Snippet)
		}
		if t.LikelyCause != "" {
			df.LikelyCauseText = strp(t.LikelyCause)
		}
		doc.Findings = append(doc.Findings, df)
	}
	if doc.Findings == nil {
		doc.Findings = []DocFinding{}
	}
	return doc
}

// JSON renders the document as indented JSON with a final newline.
func JSON(in Input) ([]byte, error) {
	var b bytes.Buffer
	enc := json.NewEncoder(&b)
	enc.SetEscapeHTML(false)
	enc.SetIndent("", "  ")
	if err := enc.Encode(Build(in)); err != nil {
		return nil, err
	}
	return b.Bytes(), nil
}

func labelBits(in Input) int {
	vals := map[string][]string{}
	for _, o := range in.Obs.Deliveries {
		if o.State == nil {
			continue
		}
		for i, name := range in.Obs.ProbeNames {
			for _, src := range [][]string{o.State.Before, o.State.After} {
				if i < len(src) {
					vals[name] = append(vals[name], src[i])
				}
			}
		}
	}
	return scrub.LabelBits(vals, in.Meta.Salt)
}

func strp(s string) *string { return &s }
func intp(i int) *int       { return &i }
func i64p(i int64) *int64   { return &i }
