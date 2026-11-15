package judge

import "github.com/jiordiviera/webhooktrap/agent/plan"

// Severities (SEV-1).
const (
	Critical = "critical"
	High     = "high"
	Medium   = "medium"
	Info     = "info"
)

// Rank orders severities, Critical first.
func Rank(sev string) int {
	switch sev {
	case Critical:
		return 0
	case High:
		return 1
	case Medium:
		return 2
	case Info:
		return 3
	}
	return 4
}

// Finding types (SJ §5.2, FND-1). No other type exists.
const (
	AcceptsTampered        = "accepts_tampered"
	AcceptsUnsigned        = "accepts_unsigned"
	AcceptsStale           = "accepts_stale"
	DuplicateChangedState  = "duplicate_changed_state"
	OrderChangedState      = "order_changed_state"
	ConcurrentChangedState = "concurrent_changed_state"
	PartialSideEffect      = "partial_side_effect"
	RetryDiverged          = "retry_diverged"
	ServerError            = "server_error"
	Non2xxOnValid          = "non_2xx_on_valid"
	ErrorOnInvalid         = "error_on_invalid"
	OverBudget             = "over_budget"
	NeedsProbe             = "needs_probe"
	OrderDependent         = "order_dependent"
	ProbeGap               = "probe_gap"
	ColdStart              = "cold_start"
	AsyncWrite             = "async_write"
)

// SeverityOf is the §5.2 table.
var SeverityOf = map[string]string{
	AcceptsTampered: Critical, AcceptsUnsigned: Critical,
	AcceptsStale: High, DuplicateChangedState: High, OrderChangedState: High,
	ConcurrentChangedState: High, PartialSideEffect: High, RetryDiverged: High, ServerError: High,
	Non2xxOnValid: Medium, ErrorOnInvalid: Medium, OverBudget: Medium,
	NeedsProbe: Info, OrderDependent: Info, ProbeGap: Info, ColdStart: Info, AsyncWrite: Info,
}

// runLevel types merge on type only (FND-4).
var runLevel = map[string]bool{NeedsProbe: true, OrderDependent: true, ProbeGap: true, ColdStart: true, AsyncWrite: true}

// Run statuses (RUN-1).
const (
	StatusCouldntStart = "couldnt_start"
	StatusAborted      = "aborted"
	StatusFindings     = "findings"
	StatusInconclusive = "inconclusive"
	StatusPassed       = "passed"
)

// Scenario and delivery results (§7.1, SCN-1).
const (
	ResultPass         = "pass"
	ResultFinding      = "finding"
	ResultInconclusive = "inconclusive"
	ResultUnreachable  = "unreachable"
	ResultNA           = "n/a"
	ResultNotRun       = "not_run"
	ResultSentNoResult = "sent_no_result"
)

// Options are the run definition values the judge needs.
type Options struct {
	FailOn             string // critical | high | medium | never (default high)
	FailOnInconclusive bool
	Framework          string // webhooktrap.yml framework: override (FND-9)
	TargetPath         string // for fingerprints (FND-2)
	// Covers maps a probe entry name to the object types it declares (PRB-12).
	Covers map[string][]string
}

// Evidence is one delivery of a finding (FND-5).
type Evidence struct {
	D    int    `json:"d"`
	Role string `json:"role"` // offending | reference
}

// Measured holds the typed measurements of one offending delivery.
type Measured struct {
	D         int     `json:"d"`
	Status    int     `json:"status,omitempty"`
	Transport string  `json:"transport,omitempty"`
	LatencyMS *int64  `json:"latency_ms,omitempty"`
	OverByMS  *int64  `json:"over_by_ms,omitempty"`
	Probe     string  `json:"probe,omitempty"`
	Before    *string `json:"before,omitempty"`
	After     *string `json:"after,omitempty"`
	Expected  *string `json:"expected,omitempty"`
}

// Params are FND-11's closed set: the only variable parts of finding text.
type Params struct {
	EventType        string       `json:"event_type,omitempty"`
	Status           int          `json:"status,omitempty"`
	Statuses         []int        `json:"statuses,omitempty"`
	Transport        string       `json:"transport,omitempty"`
	LatencyMS        *int64       `json:"latency_ms,omitempty"`
	MaxOtherMS       *int64       `json:"max_other_ms,omitempty"`
	OverByMS         *int64       `json:"over_by_ms,omitempty"`
	BudgetMS         int          `json:"budget_ms,omitempty"`
	BudgetLabel      string       `json:"budget_label,omitempty"`
	SlowCount        int          `json:"slow_count,omitempty"`
	ValidCount       int          `json:"valid_count,omitempty"`
	D                int          `json:"d,omitempty"`
	DRef             int          `json:"d_ref,omitempty"`
	TimestampOffsetS int          `json:"timestamp_offset_s,omitempty"`
	Tamper           *plan.Tamper `json:"tamper,omitempty"`
	Probe            string       `json:"probe,omitempty"`
	Before           *string      `json:"before,omitempty"`
	After            *string      `json:"after,omitempty"`
	Expected         *string      `json:"expected,omitempty"`
	ObjectType       string       `json:"object_type,omitempty"`
	Framework        string       `json:"framework,omitempty"`
}

// Finding is one merged finding.
type Finding struct {
	ID              string     `json:"id"`
	Type            string     `json:"type"`
	Fingerprint     string     `json:"fingerprint"`
	Severity        string     `json:"severity"`
	Level           int        `json:"level"`
	EventType       string     `json:"event_type"`
	Scenarios       []string   `json:"scenarios"`
	Evidence        []Evidence `json:"evidence"`
	LikelyCauseRule string     `json:"likely_cause_rule"`
	Framework       string     `json:"framework"`
	Params          Params     `json:"params"`
	Measured        []Measured `json:"measured"`
}

// Offending returns the offending D-numbers in order.
func (f *Finding) Offending() []int {
	var out []int
	for _, e := range f.Evidence {
		if e.Role == "offending" {
			out = append(out, e.D)
		}
	}
	return out
}

// Reading is the judged state of one probe entry on a delivery.
type Reading struct {
	Name            string  `json:"name"`
	Changed         bool    `json:"changed"`
	MatchesExpected *bool   `json:"matches_expected"`
	Flagged         bool    `json:"flagged"`
	Before          *string `json:"before"`
	After           *string `json:"after"`
	Expected        *string `json:"expected"`
}

// DeliveryResult is the verdict on one delivery.
type DeliveryResult struct {
	N          int       `json:"n"`
	Result     string    `json:"result"`
	Severity   string    `json:"severity,omitempty"`
	Reason     string    `json:"reason,omitempty"`
	OverBudget bool      `json:"over_budget"`
	Readings   []Reading `json:"readings,omitempty"`
}

// ScenarioReading is the scenario's line-3 reading (PV-6), real values locally.
type ScenarioReading struct {
	Kind     string  `json:"kind"` // change | burst_final
	Probe    string  `json:"probe"`
	Before   *string `json:"before"`
	After    *string `json:"after"`
	Expected *string `json:"expected"`
	Flagged  bool    `json:"flagged"`
}

// ScenarioResult is the verdict on one scenario.
type ScenarioResult struct {
	Name       string           `json:"name"`
	Result     string           `json:"result"`
	Reason     string           `json:"reason"`
	ReasonText string           `json:"-"`
	Deliveries []int            `json:"deliveries"`
	Findings   []string         `json:"findings"`
	Reading    *ScenarioReading `json:"reading"`
	SpreadMS   *int64           `json:"spread_ms,omitempty"`
}

// Counts (RUN-3): Findings = Critical + High + Medium.
type Counts struct {
	Critical int `json:"critical"`
	High     int `json:"high"`
	Medium   int `json:"medium"`
	Info     int `json:"info"`
	Findings int `json:"findings"`
}

// Framework is FND-9's result.
type Framework struct {
	Name         string `json:"name"`
	DetectedFrom string `json:"detected_from"` // x_powered_by | cookie | webhooktrap_yml
}

// NotChecked is one RUN-7 item.
type NotChecked struct {
	Code        string   `json:"code"` // no_probe | uncovered_objects | no_reset | side_effects
	ObjectTypes []string `json:"object_types,omitempty"`
}

// Verdict is the judge's whole output. Same plan and observations, same
// verdict, byte for byte (JSON-4).
type Verdict struct {
	Status             string           `json:"status"`
	Reason             string           `json:"status_reason"`
	HTTPStatus         int              `json:"-"`
	ExitCode           int              `json:"exit_code"`
	FailOn             string           `json:"fail_on"`
	FailOnInconclusive bool             `json:"fail_on_inconclusive"`
	Levels             []int            `json:"levels"`
	OrderDependent     bool             `json:"order_dependent"`
	Framework          *Framework       `json:"framework"`
	Scenarios          []ScenarioResult `json:"scenarios"`
	Deliveries         []DeliveryResult `json:"deliveries"`
	Findings           []Finding        `json:"findings"`
	Counts             Counts           `json:"counts"`
	NotChecked         []NotChecked     `json:"not_checked"`
}

// Finding returns the finding with an id, or nil.
func (v *Verdict) Finding(id string) *Finding {
	for i := range v.Findings {
		if v.Findings[i].ID == id {
			return &v.Findings[i]
		}
	}
	return nil
}
