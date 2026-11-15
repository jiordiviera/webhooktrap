package plan

import (
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"fmt"
	"sort"
	"strconv"

	"github.com/jiordiviera/webhooktrap/agent/internal/jsonpath"
	"github.com/jiordiviera/webhooktrap/agent/pack"
)

// Scenario names (SJ §2.1). Values are the machine names of the run document.
type Scenario string

const (
	Once       Scenario = "once"
	Twice      Scenario = "twice"
	Shuffled   Scenario = "shuffled"
	Late       Scenario = "late"
	Retry      Scenario = "retry"
	Tampered   Scenario = "tampered"
	Unsigned   Scenario = "unsigned"
	Burst      Scenario = "burst"
	SlowBudget Scenario = "slow_budget"
)

// Order is PLAN-1's fixed order. Unsigned is in the MVP (A-7 option B, decision 35).
var Order = []Scenario{Once, Twice, Shuffled, Late, Retry, Tampered, Unsigned, Burst, SlowBudget}

// Label is the UI name of a scenario.
func (s Scenario) Label() string {
	switch s {
	case Once:
		return "Once"
	case Twice:
		return "Twice"
	case Shuffled:
		return "Shuffled"
	case Late:
		return "Late"
	case Retry:
		return "Retry after error"
	case Tampered:
		return "Tampered"
	case Unsigned:
		return "Unsigned"
	case Burst:
		return "Burst"
	case SlowBudget:
		return "Slow budget"
	}
	return string(s)
}

// ParseScenario accepts a machine name.
func ParseScenario(s string) (Scenario, bool) {
	for _, o := range Order {
		if string(o) == s {
			return o, true
		}
	}
	return "", false
}

// Delivery modes (SJ §13).
const (
	ModeNormal    = "normal"
	ModeDuplicate = "duplicate"
	ModeRetry     = "retry"
	ModeLate      = "late"
	ModeTampered  = "tampered"
	ModeUnsigned  = "unsigned"
)

// Signing kinds (SJ §13, schema.md signing_kind).
const (
	SignResigned        = "resigned"
	SignOriginal        = "original"
	SignOriginalBodyNow = "original_body_fresh_ts"
	SignNone            = "none"
)

// Expected-state rules (SJ §8.5), recorded per delivery (PLAN-4).
const (
	ExpectNone            = "none"
	ExpectBaseline        = "baseline"         // Once, only event: records B_primary and B_final
	ExpectBaselinePrimary = "baseline_primary" // Once D1: records B_primary
	ExpectBaselineFinal   = "baseline_final"   // last Once delivery: records B_final
	ExpectSameAsPrevious  = "same_as_previous" // Twice D_b
	ExpectBFinal          = "b_final"          // last Shuffled delivery
	ExpectUnchanged       = "unchanged"        // Late, Tampered, Unsigned
	ExpectRetryA1         = "retry_a1"
	ExpectRetryA2         = "retry_a2"
	ExpectBurstFinal      = "burst_final"
)

// Gaps (PLAN-3), in milliseconds.
const (
	GapSequential     = 150
	GapTwice          = 500
	GapRetry          = 2000
	GapScenario       = 150
	MaxDeliveries     = 50 // CAP-1
	MaxEvents         = 5  // DEF-1
	LateExtraSeconds  = 60 // LATE-1
	tamperedMarkerKey = "_webhooktrap_tampered"
)

// Capture is one selected source event (a capture or a fixture).
type Capture struct {
	Ref          string      // capture id or fixture path
	Provider     string      // pack id
	CaptureOrder int         // from the fixture or received_at order
	Headers      [][2]string // as captured, in order
	Body         []byte
}

// Budget is the resolved budget for the run (BUD-1, BUD-2).
type Budget struct {
	MS          int    `json:"ms"`
	Source      string `json:"source"`
	Label       string `json:"label"`
	SourceLabel string `json:"-"`
}

// Input is everything plan needs. No clock, no network.
type Input struct {
	Captures  []Capture
	Scenarios []Scenario
	Pack      *pack.Pack
	Seed      uint64
	HasSecret bool
	Budget    Budget
}

// Event is a planned source event.
type Event struct {
	Ref             string `json:"ref"`
	ProviderEventID string `json:"provider_event_id"`
	EventType       string `json:"event_type"`
	CaptureOrder    int    `json:"capture_order"`
	BodySHA256      string `json:"body_sha256"`
	Primary         bool   `json:"primary"`
	JSON            bool   `json:"-"`
}

// PackRef names the pack version the plan used.
type PackRef struct {
	ID      string `json:"id"`
	Version string `json:"version"`
}

// ScenarioPlan is one scenario of the plan.
type ScenarioPlan struct {
	Name       Scenario `json:"name"`
	NA         string   `json:"na,omitempty"` // n/a reason code
	NAText     string   `json:"na_text,omitempty"`
	Deliveries []int    `json:"deliveries"`
}

// Signing is the signing instruction of a delivery.
type Signing struct {
	Kind             string `json:"kind"`
	TimestampOffsetS int    `json:"timestamp_offset_s,omitempty"`
}

// Tamper is the edit of a Tampered delivery (TAMP-1).
type Tamper struct {
	Path string `json:"path"`
	From string `json:"from"`
	To   string `json:"to"`
}

// Delivery is one planned request.
type Delivery struct {
	N          int      `json:"n"`
	Scenario   Scenario `json:"scenario"`
	Event      int      `json:"event"` // index into Plan.Events
	EventRef   string   `json:"event_ref"`
	Mode       string   `json:"mode"`
	Signing    Signing  `json:"signing"`
	Tamper     *Tamper  `json:"tamper"`
	GapMS      int      `json:"gap_ms"`
	Concurrent bool     `json:"concurrent,omitempty"`
	Expect     string   `json:"expect"`
}

// Plan is the exact list of deliveries a run sends (PLAN-4).
type Plan struct {
	Seed        string         `json:"seed"`
	Provider    string         `json:"provider"`
	Pack        PackRef        `json:"pack"`
	Budget      Budget         `json:"budget"`
	Events      []Event        `json:"events"`
	Scenarios   []ScenarioPlan `json:"scenarios"`
	Deliveries  []Delivery     `json:"deliveries"`
	NeedsSecret bool           `json:"needs_secret"`
	Hash        string         `json:"-"`
}

// Error is a definition error (EXIT-1 exit 3): nothing is sent.
type Error struct {
	Code string // def_1, cap_1, no_events, unknown_scenario, no_pack
	Msg  string
}

func (e *Error) Error() string { return e.Msg }

// Build makes the plan. Same input, same plan and hash (D-3).
func Build(in Input) (*Plan, error) {
	p := in.Pack
	if p == nil {
		return nil, &Error{"no_pack", "no provider pack"}
	}
	if len(in.Captures) == 0 {
		return nil, &Error{"no_events", "Select at least one event."}
	}
	if len(in.Captures) > MaxEvents {
		return nil, &Error{"def_1", fmt.Sprintf("Select at most %d events (got %d).", MaxEvents, len(in.Captures))}
	}
	for _, c := range in.Captures {
		if c.Provider != p.ID {
			return nil, &Error{"def_1", "Select events from one provider."}
		}
	}
	caps := append([]Capture(nil), in.Captures...)
	sort.SliceStable(caps, func(i, j int) bool {
		if caps[i].CaptureOrder != caps[j].CaptureOrder {
			return caps[i].CaptureOrder < caps[j].CaptureOrder
		}
		return caps[i].Ref < caps[j].Ref
	})

	pl := &Plan{
		Seed:     strconv.FormatUint(in.Seed, 10),
		Provider: p.ID,
		Pack:     PackRef{p.ID, p.Version},
		Budget:   in.Budget,
	}
	for i, c := range caps {
		et, id := EventMeta(p, c.Headers, c.Body)
		sum := sha256.Sum256(c.Body)
		pl.Events = append(pl.Events, Event{
			Ref: c.Ref, ProviderEventID: id, EventType: et, CaptureOrder: c.CaptureOrder,
			BodySHA256: hex.EncodeToString(sum[:]), Primary: i == 0, JSON: jsonValid(c.Body),
		})
	}

	selected := map[Scenario]bool{Once: true} // PLAN-2
	for _, s := range in.Scenarios {
		if _, ok := ParseScenario(string(s)); !ok {
			return nil, &Error{"unknown_scenario", fmt.Sprintf("Unknown scenario %q.", s)}
		}
		selected[s] = true
	}

	scheme := p.Signing.Scheme
	signed := scheme != "none"
	if signed && p.Signing.SignedTimestamp && !in.HasSecret {
		pl.NeedsSecret = true // SIGN-2: valid deliveries can't be built
	}
	valid := SignResigned
	if !in.HasSecret {
		valid = SignOriginal
	}

	n := len(pl.Events)
	next := 1
	add := func(sc *ScenarioPlan, d Delivery) {
		d.N = next
		d.Scenario = sc.Name
		d.EventRef = pl.Events[d.Event].Ref
		next++
		sc.Deliveries = append(sc.Deliveries, d.N)
		pl.Deliveries = append(pl.Deliveries, d)
	}
	for _, s := range Order {
		if !selected[s] {
			continue
		}
		sc := ScenarioPlan{Name: s, Deliveries: []int{}}
		na, naText := notApplicable(s, p, pl, in.HasSecret)
		if na != "" {
			sc.NA, sc.NAText = na, naText
			pl.Scenarios = append(pl.Scenarios, sc)
			continue
		}
		vs := Signing{Kind: valid}
		switch s {
		case Once:
			for i := 0; i < n; i++ {
				exp := ExpectNone
				switch {
				case n == 1:
					exp = ExpectBaseline
				case i == 0:
					exp = ExpectBaselinePrimary
				case i == n-1:
					exp = ExpectBaselineFinal
				}
				add(&sc, Delivery{Event: i, Mode: ModeNormal, Signing: vs, GapMS: gap(i, GapSequential), Expect: exp})
			}
		case Twice:
			add(&sc, Delivery{Event: 0, Mode: ModeNormal, Signing: vs, GapMS: GapScenario, Expect: ExpectNone})
			add(&sc, Delivery{Event: 0, Mode: ModeDuplicate, Signing: vs, GapMS: GapTwice, Expect: ExpectSameAsPrevious})
		case Shuffled:
			for k, i := 0, n-1; i >= 0; k, i = k+1, i-1 {
				exp := ExpectNone
				if i == 0 {
					exp = ExpectBFinal
				}
				add(&sc, Delivery{Event: i, Mode: ModeNormal, Signing: vs, GapMS: gap(k, GapSequential), Expect: exp})
			}
		case Late:
			add(&sc, Delivery{Event: 0, Mode: ModeLate, GapMS: GapScenario, Expect: ExpectUnchanged,
				Signing: Signing{Kind: SignResigned, TimestampOffsetS: -(p.Signing.ToleranceS + LateExtraSeconds)}})
		case Retry:
			add(&sc, Delivery{Event: 0, Mode: ModeNormal, Signing: vs, GapMS: GapScenario, Expect: ExpectRetryA1})
			add(&sc, Delivery{Event: 0, Mode: ModeRetry, Signing: vs, GapMS: GapRetry, Expect: ExpectRetryA2})
		case Tampered:
			_, t, _ := TamperBody(p, pl.Events[0].EventType, caps[0].Body)
			kind := SignOriginalBodyNow
			if !in.HasSecret {
				kind = SignOriginal
			}
			tt := t
			add(&sc, Delivery{Event: 0, Mode: ModeTampered, Signing: Signing{Kind: kind}, Tamper: &tt, GapMS: GapScenario, Expect: ExpectUnchanged})
		case Unsigned:
			add(&sc, Delivery{Event: 0, Mode: ModeUnsigned, Signing: Signing{Kind: SignNone}, GapMS: GapScenario, Expect: ExpectUnchanged})
		case Burst:
			if n == 1 { // BURST-1: two copies of the one event
				add(&sc, Delivery{Event: 0, Mode: ModeNormal, Signing: vs, GapMS: GapScenario, Concurrent: true, Expect: ExpectBurstFinal})
				add(&sc, Delivery{Event: 0, Mode: ModeDuplicate, Signing: vs, Concurrent: true, Expect: ExpectBurstFinal})
			} else {
				for i := 0; i < n; i++ { // BURST-1a: numbered in capture order
					g := 0
					if i == 0 {
						g = GapScenario
					}
					add(&sc, Delivery{Event: i, Mode: ModeNormal, Signing: vs, GapMS: g, Concurrent: true, Expect: ExpectBurstFinal})
				}
			}
		case SlowBudget:
			add(&sc, Delivery{Event: 0, Mode: ModeNormal, Signing: vs, GapMS: GapScenario, Expect: ExpectNone})
		}
		pl.Scenarios = append(pl.Scenarios, sc)
	}
	if len(pl.Deliveries) > MaxDeliveries {
		return nil, &Error{"cap_1", fmt.Sprintf("This run would send %d deliveries. The limit is %d.", len(pl.Deliveries), MaxDeliveries)}
	}
	raw, err := json.Marshal(pl)
	if err != nil {
		return nil, err
	}
	sum := sha256.Sum256(raw)
	pl.Hash = "sha256:" + hex.EncodeToString(sum[:])
	return pl, nil
}

func gap(i, g int) int {
	if i == 0 {
		return GapScenario
	}
	return g
}

func notApplicable(s Scenario, p *pack.Pack, pl *Plan, hasSecret bool) (string, string) {
	signed := p.Signing.Scheme != "none"
	switch s {
	case Twice:
		if !p.Duplicate.Verified {
			return "not_verified", "not verified for " + p.Name
		}
	case Shuffled:
		if len(pl.Events) < 2 {
			return "needs_related_events", "needs 2+ related events"
		}
	case Late:
		if !signed || !p.Signing.SignedTimestamp {
			return "no_signed_timestamp", "provider signs no timestamp"
		}
	case Retry:
		if !p.Retry.Supported {
			return "no_automatic_retries", p.Name + " doesn't retry automatically"
		}
		if !p.Retry.Verified {
			return "not_verified", "not verified for " + p.Name
		}
	case Tampered:
		if !pl.Events[0].JSON {
			return "body_not_json", "body isn't JSON"
		}
		if !signed {
			return "nothing_to_forge", "nothing to forge: the provider signs nothing"
		}
	case Unsigned:
		if !signed {
			return "nothing_to_forge", "nothing to forge: the provider signs nothing"
		}
	}
	_ = hasSecret
	return "", ""
}

func jsonValid(b []byte) bool {
	_, err := jsonpath.Scan(b)
	return err == nil
}

// Header returns the first header value with a case-insensitive name match.
func Header(h [][2]string, name string) string {
	for _, kv := range h {
		if equalFold(kv[0], name) {
			return kv[1]
		}
	}
	return ""
}

func equalFold(a, b string) bool {
	if len(a) != len(b) {
		return false
	}
	for i := 0; i < len(a); i++ {
		x, y := a[i], b[i]
		if 'A' <= x && x <= 'Z' {
			x += 'a' - 'A'
		}
		if 'A' <= y && y <= 'Z' {
			y += 'a' - 'A'
		}
		if x != y {
			return false
		}
	}
	return true
}

// EventMeta reads the event type and provider event id as the pack says.
func EventMeta(p *pack.Pack, headers [][2]string, body []byte) (eventType, id string) {
	if p.Event.TypeHeader != "" {
		eventType = Header(headers, p.Event.TypeHeader)
	}
	if p.Event.IDHeader != "" {
		id = Header(headers, p.Event.IDHeader)
	}
	if (eventType == "" && p.Event.TypePath != "") || (id == "" && p.Event.IDPath != "") {
		if doc, err := jsonpath.Scan(body); err == nil {
			if eventType == "" && p.Event.TypePath != "" {
				if s, ok := doc.First(p.Event.TypePath); ok {
					eventType = s.Text(body)
				}
			}
			if id == "" && p.Event.IDPath != "" {
				if s, ok := doc.First(p.Event.IDPath); ok {
					id = s.Text(body)
				}
			}
		}
	}
	if eventType == "" {
		eventType = "event"
	}
	return eventType, id
}
