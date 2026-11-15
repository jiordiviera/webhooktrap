package render

import (
	"bytes"
	"encoding/json"
	"strings"

	"github.com/jiordiviera/webhooktrap/agent/judge"
	"github.com/jiordiviera/webhooktrap/agent/observe"
	"github.com/jiordiviera/webhooktrap/agent/plan"
	"github.com/jiordiviera/webhooktrap/agent/scrub"
)

// HostedDocument is the hosted-profile run document an agent posts to the
// server (contracts §5.7, ADR-010 §11): no prose fields (the server renders
// finding text from type + pack + params, TM-16), no raw probe values (only
// PV-2 display values), no observations, no local warnings.
type HostedDocument struct {
	Proto         int                `json:"proto"`
	SchemaVersion string             `json:"schema_version"`
	Profile       string             `json:"profile"`
	Run           DocRun             `json:"run"`
	Events        []plan.Event       `json:"events"`
	Scenarios     []HostedScenario   `json:"scenarios"`
	Deliveries    []HostedDelivery   `json:"deliveries"`
	Findings      []judge.Finding    `json:"findings"`
	Counts        judge.Counts       `json:"counts"`
	NotChecked    []judge.NotChecked `json:"not_checked"`
	Plan          *plan.Plan         `json:"plan"`
}

// HostedScenario is a scenario without reading text or raw values.
type HostedScenario struct {
	Name       string         `json:"name"`
	Result     string         `json:"result"`
	Reason     *string        `json:"reason"`
	Deliveries []int          `json:"deliveries"`
	Findings   []string       `json:"findings"`
	Reading    *HostedReading `json:"reading"`
}

// HostedReading is a scenario reading, display values only.
type HostedReading struct {
	Kind    string             `json:"kind"`
	Probe   string             `json:"probe"`
	Display map[string]*string `json:"display"`
}

// HostedDelivery is one delivery of the final document (contracts §5.7).
type HostedDelivery struct {
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
	State      *HostedState      `json:"state"`
	Result     string            `json:"result"`
	Severity   *string           `json:"severity"`
	Completed  bool              `json:"completed"`
}

// LiveDelivery is one delivery of an observation batch (contracts §5.5):
// a phase, no severity, and no result until judged.
type LiveDelivery struct {
	N          int               `json:"n"`
	Phase      string            `json:"phase"` // sent | responded | probed | judged
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
	State      *HostedState      `json:"state"`
	Result     *string           `json:"result"`
	Completed  bool              `json:"completed"`
}

// HostedState is a delivery's state: outcomes and display values only.
type HostedState struct {
	Readings  []HostedStateReading `json:"readings"`
	Failed    bool                 `json:"failed"`
	Unsettled bool                 `json:"unsettled"`
}

// HostedStateReading is one probe reading of a delivery.
type HostedStateReading struct {
	Name            string             `json:"name"`
	Changed         bool               `json:"changed"`
	MatchesExpected *bool              `json:"matches_expected"`
	Flagged         bool               `json:"flagged"`
	Display         map[string]*string `json:"display"`
}

// Hosted builds the hosted-profile document from the same input as Build.
func Hosted(in Input) *HostedDocument {
	local := Build(in)
	bits := local.Run.LabelBits
	disp := func(name string, raw *string) *string {
		if raw == nil {
			return nil
		}
		d := scrub.Display(name, *raw, in.Meta.Salt, bits)
		return &d
	}
	run := local.Run
	run.Budget.Source = codeForm(run.Budget.Source)
	run.PlanHash = HostedPlanHash(run.PlanHash)
	events := make([]plan.Event, len(local.Events))
	for i, e := range local.Events {
		e.EventType, e.ProviderEventID = HostedToken(e.EventType), HostedToken(e.ProviderEventID)
		events[i] = e
	}
	doc := &HostedDocument{
		Proto: 1, SchemaVersion: SchemaVersion, Profile: "hosted", Run: run, Events: events,
		Counts: local.Counts, NotChecked: in.Verdict.NotChecked, Plan: in.Plan,
		Scenarios: []HostedScenario{}, Deliveries: []HostedDelivery{}, Findings: []judge.Finding{},
	}
	if doc.NotChecked == nil {
		doc.NotChecked = []judge.NotChecked{}
	}
	for _, s := range local.Scenarios {
		hs := HostedScenario{Name: s.Name, Result: s.Result, Reason: s.Reason, Deliveries: s.Deliveries, Findings: s.Findings}
		if s.Reading != nil {
			hs.Reading = &HostedReading{Kind: s.Reading.Kind, Probe: s.Reading.Probe, Display: s.Reading.Display}
		}
		doc.Scenarios = append(doc.Scenarios, hs)
	}
	for _, d := range local.Deliveries {
		hd := HostedDelivery{N: d.N, Scenario: d.Scenario, EventRef: d.EventRef, Mode: d.Mode, OffsetMS: d.OffsetMS,
			Signing: d.Signing, Tamper: d.Tamper, Status: d.Status, Transport: d.Transport, LatencyMS: d.LatencyMS,
			OverBudget: d.OverBudget, Response: d.Response, Result: d.Result, Severity: d.Severity, Completed: d.Completed}
		if d.State != nil {
			hd.State = &HostedState{Failed: d.State.Failed, Unsettled: d.State.Unsettled, Readings: []HostedStateReading{}}
			for _, sr := range d.State.Readings {
				hd.State.Readings = append(hd.State.Readings, HostedStateReading{Name: sr.Name, Changed: sr.Changed,
					MatchesExpected: sr.MatchesExpected, Flagged: sr.Flagged, Display: sr.Display})
			}
		}
		doc.Deliveries = append(doc.Deliveries, hd)
	}
	for _, f := range in.Verdict.Findings {
		f.EventType, f.Params.EventType = HostedToken(f.EventType), HostedToken(f.Params.EventType)
		f.Params.Before, f.Params.After, f.Params.Expected = disp(f.Params.Probe, f.Params.Before), disp(f.Params.Probe, f.Params.After), disp(f.Params.Probe, f.Params.Expected)
		ms := make([]judge.Measured, len(f.Measured))
		for i, m := range f.Measured {
			m.Before, m.After, m.Expected = disp(m.Probe, m.Before), disp(m.Probe, m.After), disp(m.Probe, m.Expected)
			ms[i] = m
		}
		f.Measured = ms
		doc.Findings = append(doc.Findings, f)
	}
	return doc
}

// HostedJSON renders the hosted document as compact JSON.
func HostedJSON(in Input) ([]byte, error) {
	var b bytes.Buffer
	enc := json.NewEncoder(&b)
	enc.SetEscapeHTML(false)
	if err := enc.Encode(Hosted(in)); err != nil {
		return nil, err
	}
	return bytes.TrimRight(b.Bytes(), "\n"), nil
}

// Live is the observation of one delivery as it completes, before
// judging: outcomes known at that point (changed), display values keyed at
// 24 bits (PV-2; widening is applied in the final document), no result.
func Live(pd plan.Delivery, o observe.Delivery, probeNames []string, salt []byte, budgetMS int) LiveDelivery {
	hd := LiveDelivery{N: pd.N, Phase: "sent", Scenario: string(pd.Scenario), EventRef: pd.EventRef, Mode: pd.Mode,
		Signing: pd.Signing, Tamper: pd.Tamper, Response: o.Response, Completed: o.Completed}
	if o.Sent {
		hd.OffsetMS = i64p(o.OffsetMS)
	}
	if o.Responded {
		hd.Phase = "responded"
		hd.Status, hd.LatencyMS = intp(o.Status), i64p(o.LatencyMS)
		hd.OverBudget = budgetMS > 0 && o.LatencyMS > int64(budgetMS)
	}
	if o.Transport != "" {
		hd.Transport = strp(o.Transport)
	}
	if o.State != nil {
		hd.Phase = "probed"
		st := &HostedState{Failed: o.State.Failed, Unsettled: o.State.Unsettled, Readings: []HostedStateReading{}}
		for i, name := range probeNames {
			var before, after *string
			if i < len(o.State.Before) {
				v := scrub.Display(name, o.State.Before[i], salt, 24)
				before = &v
			}
			if i < len(o.State.After) {
				v := scrub.Display(name, o.State.After[i], salt, 24)
				after = &v
			}
			changed := before != nil && after != nil && i < len(o.State.Before) && i < len(o.State.After) && o.State.Before[i] != o.State.After[i]
			st.Readings = append(st.Readings, HostedStateReading{Name: name, Changed: changed,
				Display: map[string]*string{"before": before, "after": after, "expected": nil}})
		}
		hd.State = st
	}
	return hd
}

// HostedToken returns s when the server's schema accepts it as an event type
// or provider event id (^[A-Za-z0-9_.:/-]{1,128}$), else "" ("unknown",
// which the schema allows in both places). Both come from captured bodies,
// which anyone with a capture URL can write; normalising them here keeps a
// hostile capture from making the final upload fail with 422 (AC-9).
func HostedToken(s string) string {
	if len(s) > 128 {
		return ""
	}
	for i := 0; i < len(s); i++ {
		c := s[i]
		if !(c >= 'A' && c <= 'Z' || c >= 'a' && c <= 'z' || c >= '0' && c <= '9' || strings.IndexByte("_.:/-", c) >= 0) {
			return ""
		}
	}
	return s
}

// codeForm turns a label such as "webhooktrap.yml" into a machine code
// (^[a-z0-9_]{1,64}$): the hosted profile carries codes, never text.
func codeForm(s string) string {
	b := []byte(strings.ToLower(s))
	for i, c := range b {
		if !(c >= 'a' && c <= 'z' || c >= '0' && c <= '9' || c == '_') {
			b[i] = '_'
		}
	}
	if len(b) == 0 {
		return "default"
	}
	if len(b) > 64 {
		b = b[:64]
	}
	return string(b)
}

// HostedPlanHash is the plan hash as the hosted schemas carry it: bare hex,
// without the local "sha256:" prefix.
func HostedPlanHash(h string) string { return strings.TrimPrefix(h, "sha256:") }
