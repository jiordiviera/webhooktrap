package sandbox

import (
	"bytes"
	"encoding/base64"
	"encoding/json"
	"errors"
	"regexp"
	"strconv"

	"github.com/jiordiviera/webhooktrap/agent/internal/agentapi"
	"github.com/jiordiviera/webhooktrap/agent/internal/version"
	"github.com/jiordiviera/webhooktrap/agent/pack"
	"github.com/jiordiviera/webhooktrap/agent/plan"
	"github.com/jiordiviera/webhooktrap/agent/render"
	"github.com/jiordiviera/webhooktrap/agent/signing"
)

// PlanInput is the `plan` op's input: the run draft's selection, scenarios
// and pack, as the app holds them (contracts §3.2 Runs/Draft preview).
type PlanInput struct {
	Captures  []CaptureIn `json:"captures"`
	Scenarios []string    `json:"scenarios"`
	Pack      PackRef     `json:"pack"`
	Seed      string      `json:"seed,omitempty"`       // decimal uint64; default 0
	HasSecret bool        `json:"has_secret,omitempty"` // a secret will be available to the executor
	BudgetMS  int         `json:"budget_ms,omitempty"`  // project budget over the pack's (BUD-1, BUD-2)
}

// CaptureIn is one selected capture: headers in received order and the raw
// body, base64.
type CaptureIn struct {
	Ref        string      `json:"ref"`
	Order      int         `json:"order"`
	Headers    [][2]string `json:"headers"`
	BodyBase64 string      `json:"body_base64"`
}

// PackRef names a pack; an empty version is the highest available.
type PackRef struct {
	ID      string `json:"id"`
	Version string `json:"version,omitempty"`
}

// Preview is the `plan` op's answer (PlanPreview): delivery count, n/a
// codes and refusals, codes only.
type Preview struct {
	DeliveryCount int               `json:"delivery_count"`
	PlanHash      string            `json:"plan_hash"`
	NeedsSecret   bool              `json:"needs_secret"`
	Pack          plan.PackRef      `json:"pack"`
	Budget        PreviewBudget     `json:"budget"`
	Scenarios     []PreviewScenario `json:"scenarios"`
	Events        []PreviewEvent    `json:"events"`
}

// PreviewBudget is the resolved budget, as a code.
type PreviewBudget struct {
	MS     int    `json:"ms"`
	Source string `json:"source"`
}

// PreviewScenario is one scenario of the preview.
type PreviewScenario struct {
	Name       string  `json:"name"`
	NA         *string `json:"na"`
	Deliveries int     `json:"deliveries"`
}

// PreviewEvent is one planned source event. Event types come from captured
// bodies, so anything outside the hosted charset is "" (render.HostedToken).
type PreviewEvent struct {
	Ref       string `json:"ref"`
	EventType string `json:"event_type"`
	Primary   bool   `json:"primary"`
}

// VerifyInput is the `verify` op's input (SIG-1 on one stored capture).
type VerifyInput struct {
	Pack       PackRef     `json:"pack"`
	Headers    [][2]string `json:"headers"`
	BodyBase64 string      `json:"body_base64"`
	ReceivedAt int64       `json:"received_at"` // unix seconds, check 7
}

var refRe = regexp.MustCompile(`^[A-Za-z0-9_.:/-]{1,128}$`)

// Handle answers one request frame. It reads nothing but the frame and the
// embedded packs: no file, no environment, no network, no clock.
func Handle(frame []byte, src pack.Source) Reply {
	var req Request
	dec := json.NewDecoder(bytes.NewReader(frame))
	dec.DisallowUnknownFields()
	if err := dec.Decode(&req); err != nil {
		return errorReply("", CodeBadFrame)
	}
	switch req.Op {
	case OpPing:
		if req.Secret != nil {
			return errorReply(req.Op, CodeBadInput)
		}
		return Reply{Proto: Proto, Op: OpPing, OK: true, Version: version.Current().Version}
	case OpPlan:
		if req.Secret != nil { // plan never needs one: refuse rather than hold it
			return errorReply(req.Op, CodeBadInput)
		}
		return handlePlan(req.Input, src)
	case OpVerify:
		return handleVerify(req.Input, req.Secret, src)
	}
	return errorReply("", CodeUnknownOp)
}

func strict(raw json.RawMessage, v any) error {
	if len(raw) == 0 {
		return errors.New("no input")
	}
	dec := json.NewDecoder(bytes.NewReader(raw))
	dec.DisallowUnknownFields()
	return dec.Decode(v)
}

func headersOK(h [][2]string) bool {
	if len(h) > agentapi.MaxCaptureHeaders {
		return false
	}
	n := 0
	for _, kv := range h {
		n += len(kv[0]) + len(kv[1])
		if kv[0] == "" {
			return false
		}
	}
	return n <= 64*agentapi.MaxHeaderBytes
}

func body(b64 string) ([]byte, bool) {
	b, err := base64.StdEncoding.DecodeString(b64)
	return b, err == nil && len(b) <= agentapi.MaxCaptureBody
}

func handlePlan(raw json.RawMessage, src pack.Source) Reply {
	var in PlanInput
	if strict(raw, &in) != nil || len(in.Captures) == 0 || len(in.Captures) > agentapi.MaxSelection || len(in.Scenarios) == 0 || len(in.Scenarios) > 9 {
		return errorReply(OpPlan, CodeBadInput)
	}
	pk, err := src.Load(in.Pack.ID, in.Pack.Version)
	if err != nil {
		return errorReply(OpPlan, CodeNoPack)
	}
	var caps []plan.Capture
	for _, c := range in.Captures {
		b, ok := body(c.BodyBase64)
		if !ok || !refRe.MatchString(c.Ref) || c.Order < 0 || !headersOK(c.Headers) {
			return errorReply(OpPlan, CodeBadInput)
		}
		caps = append(caps, plan.Capture{Ref: c.Ref, Provider: pk.ID, CaptureOrder: c.Order, Headers: c.Headers, Body: b})
	}
	var scs []plan.Scenario
	for _, s := range in.Scenarios {
		sc, ok := plan.ParseScenario(s)
		if !ok {
			return errorReply(OpPlan, "unknown_scenario")
		}
		scs = append(scs, sc)
	}
	var seed uint64
	if in.Seed != "" {
		if seed, err = strconv.ParseUint(in.Seed, 10, 64); err != nil {
			return errorReply(OpPlan, CodeBadInput)
		}
	}
	budget := plan.Budget{MS: pk.Budget.MS, Source: pk.Budget.Source, Label: pk.Budget.Label, SourceLabel: pk.Budget.SourceLabel}
	if in.BudgetMS < 0 {
		return errorReply(OpPlan, CodeBadInput)
	}
	if in.BudgetMS > 0 {
		if pk.Budget.Documented && in.BudgetMS > pk.Budget.MS {
			return errorReply(OpPlan, CodeBudget)
		}
		budget = plan.Budget{MS: in.BudgetMS, Source: "project"}
	}
	pl, err := plan.Build(plan.Input{Captures: caps, Scenarios: scs, Pack: pk, Seed: seed, HasSecret: in.HasSecret, Budget: budget})
	if err != nil {
		var pe *plan.Error
		if errors.As(err, &pe) && refRe.MatchString(pe.Code) {
			return errorReply(OpPlan, pe.Code)
		}
		return errorReply(OpPlan, CodeInternal)
	}
	pv := &Preview{
		DeliveryCount: len(pl.Deliveries), PlanHash: render.HostedPlanHash(pl.Hash), NeedsSecret: pl.NeedsSecret,
		Pack: pl.Pack, Budget: PreviewBudget{MS: pl.Budget.MS, Source: codeOf(pl.Budget.Source)},
		Scenarios: []PreviewScenario{}, Events: []PreviewEvent{},
	}
	for _, s := range pl.Scenarios {
		ps := PreviewScenario{Name: string(s.Name), Deliveries: len(s.Deliveries)}
		if s.NA != "" {
			na := s.NA
			ps.NA = &na
		}
		pv.Scenarios = append(pv.Scenarios, ps)
	}
	for _, e := range pl.Events {
		pv.Events = append(pv.Events, PreviewEvent{Ref: e.Ref, EventType: render.HostedToken(e.EventType), Primary: e.Primary})
	}
	return Reply{Proto: Proto, Op: OpPlan, OK: true, Preview: pv}
}

// codeOf keeps a budget source a code (^[a-z0-9_]{1,64}$).
func codeOf(s string) string {
	b := []byte(s)
	for i, c := range b {
		switch {
		case c >= 'A' && c <= 'Z':
			b[i] = c + 32
		case c >= 'a' && c <= 'z', c >= '0' && c <= '9', c == '_':
		default:
			b[i] = '_'
		}
	}
	if len(b) == 0 {
		return "default"
	}
	return string(b[:min(len(b), 64)])
}

func handleVerify(raw json.RawMessage, sec *SecretIn, src pack.Source) Reply {
	var in VerifyInput
	if strict(raw, &in) != nil || !headersOK(in.Headers) || in.ReceivedAt <= 0 {
		return errorReply(OpVerify, CodeBadInput)
	}
	b, ok := body(in.BodyBase64)
	if !ok {
		return errorReply(OpVerify, CodeBadInput)
	}
	var scheme signing.Scheme
	if in.Pack.ID != "" {
		pk, err := src.Load(in.Pack.ID, in.Pack.Version)
		if err != nil {
			return errorReply(OpVerify, CodeNoPack)
		}
		scheme = signing.Scheme{Name: pk.Signing.Scheme, Headers: pk.Signing.Headers, SignedTimestamp: pk.Signing.SignedTimestamp, ToleranceS: pk.Signing.ToleranceS}
	}
	var secrets []signing.Secret
	if sec != nil {
		v, err := base64.StdEncoding.DecodeString(sec.ValueBase64)
		if err != nil || len(v) == 0 || len(v) > 4096 || !nameRe.MatchString(sec.Name) {
			return errorReply(OpVerify, CodeBadInput)
		}
		secrets = append(secrets, signing.NewSecret(sec.Name, v))
		clear(v)
	}
	d := signing.Diagnose(scheme, secrets, in.Headers, b, in.ReceivedAt)
	return Reply{Proto: Proto, Op: OpVerify, OK: true, Verdict: &d}
}

var nameRe = regexp.MustCompile(`^[A-Z_][A-Z0-9_]{0,63}$`) // TM-39
