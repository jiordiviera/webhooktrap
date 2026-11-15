package judge_test

import (
	"encoding/json"
	"testing"

	"github.com/jiordiviera/webhooktrap/agent/internal/packdata"
	"github.com/jiordiviera/webhooktrap/agent/judge"
	"github.com/jiordiviera/webhooktrap/agent/observe"
	"github.com/jiordiviera/webhooktrap/agent/pack"
	"github.com/jiordiviera/webhooktrap/agent/plan"
)

// Synthetic observations only: boundaries are tested here, never with a
// real handler (D-5).

const body = `{"id":"evt_1","object":"event","type":"invoice.paid","data":{"object":{"id":"in_1","object":"invoice","amount_paid":2400}}}`

func setup(t *testing.T, sc ...plan.Scenario) (*plan.Plan, *pack.Pack) {
	t.Helper()
	pk, err := packdata.Source().Load("stripe", "")
	if err != nil {
		t.Fatal(err)
	}
	pl, err := plan.Build(plan.Input{
		Captures:  []plan.Capture{{Ref: "a", Provider: "stripe", CaptureOrder: 1, Body: []byte(body)}},
		Scenarios: sc, Pack: pk, Seed: 1, HasSecret: true, Budget: plan.Budget{MS: 10000},
	})
	if err != nil {
		t.Fatal(err)
	}
	return pl, pk
}

type d struct {
	status        int
	lat           int64
	transport     string
	before, after string
	noState       bool
}

func obs(pl *plan.Plan, probe, reset bool, ds map[int]d) *observe.Run {
	r := &observe.Run{ResetConfigured: reset}
	if probe {
		r.ProbeNames = []string{"orders.count"}
	}
	for _, pd := range pl.Deliveries {
		x, ok := ds[pd.N]
		if !ok {
			x = d{status: 200, lat: 100, before: "0", after: "1"}
		}
		o := observe.Delivery{N: pd.N, Sent: true, Completed: true, Status: x.status, Transport: x.transport, LatencyMS: x.lat, Responded: x.status > 0,
			Response: &observe.Response{BodySHA256: "h"}}
		if probe && !x.noState && !pd.Concurrent {
			o.State = &observe.State{Before: []string{x.before}, After: []string{x.after}}
		}
		r.Deliveries = append(r.Deliveries, o)
	}
	for _, s := range pl.Scenarios {
		so := observe.Scenario{Name: string(s.Name)}
		if s.Name == plan.Burst && probe {
			so.Burst = &observe.State{Before: []string{"0"}, After: []string{"1"}}
			z := int64(2)
			so.SpreadMS = &z
		}
		r.Scenarios = append(r.Scenarios, so)
	}
	return r
}

func types(v *judge.Verdict) map[string]string {
	out := map[string]string{}
	for _, f := range v.Findings {
		out[f.Type] = f.Severity
	}
	return out
}

func TestINV1Matrix(t *testing.T) {
	pl, pk := setup(t, plan.Tampered)
	n := pl.Scenarios[1].Deliveries[0]
	cases := []struct {
		name  string
		x     d
		probe bool
		want  string // finding type or scenario result
	}{
		{"2xx", d{status: 200, lat: 50, before: "0", after: "0"}, true, judge.AcceptsTampered},
		{"4xx unchanged", d{status: 400, lat: 5, before: "0", after: "0"}, true, "pass"},
		{"4xx changed", d{status: 400, lat: 5, before: "0", after: "1"}, true, judge.AcceptsTampered},
		{"3xx level 1", d{status: 302, lat: 5}, false, "inconclusive"},
		{"5xx unchanged", d{status: 500, lat: 5, before: "0", after: "0"}, true, judge.ErrorOnInvalid},
		{"timeout changed", d{transport: "timeout", before: "0", after: "1"}, true, judge.AcceptsTampered},
		{"unreachable", d{transport: "refused", noState: true}, true, "unreachable"},
	}
	for _, c := range cases {
		v := judge.Judge(pl, obs(pl, c.probe, true, map[int]d{n: c.x}), pk, judge.Options{})
		got := types(v)
		var sc string
		for _, s := range v.Scenarios {
			if s.Name == "tampered" {
				sc = s.Result
			}
		}
		if _, ok := got[c.want]; !ok && sc != c.want {
			t.Errorf("%s: findings %v, scenario %s, want %s", c.name, got, sc, c.want)
		}
	}
}

func TestRetry5(t *testing.T) {
	pl, pk := setup(t, plan.Retry)
	a1, a2 := pl.Scenarios[1].Deliveries[0], pl.Scenarios[1].Deliveries[1]
	once := pl.Scenarios[0].Deliveries[0]
	cases := []struct {
		name  string
		ds    map[int]d
		reset bool
		want  string
	}{
		{"2xx then duplicate", map[int]d{a1: {status: 200, before: "0", after: "1"}, a2: {status: 200, before: "1", after: "2"}}, true, judge.DuplicateChangedState},
		{"500 kept its write", map[int]d{a1: {status: 500, before: "0", after: "1"}, a2: {status: 200, before: "1", after: "1"}}, true, judge.PartialSideEffect},
		{"500 then retry skipped", map[int]d{once: {status: 200, before: "0", after: "1"}, a1: {status: 500, before: "0", after: "0"}, a2: {status: 200, before: "0", after: "0"}}, true, judge.RetryDiverged},
		{"500, no reset", map[int]d{a1: {status: 500, before: "0", after: "0"}, a2: {status: 200, before: "0", after: "1"}}, false, "needs_reset"},
	}
	for _, c := range cases {
		v := judge.Judge(pl, obs(pl, true, c.reset, c.ds), pk, judge.Options{})
		got := types(v)
		reason := v.Deliveries[a2-1].Reason // SCN-1: the scenario itself is "finding" (GEN-1 on A1)
		if _, ok := got[c.want]; !ok && reason != c.want {
			t.Errorf("%s: %v / %s, want %s", c.name, got, reason, c.want)
		}
	}
}

func TestBaselineRejectedAndExit(t *testing.T) {
	pl, pk := setup(t, plan.Twice)
	o := obs(pl, false, false, map[int]d{1: {status: 401, lat: 3}})
	o.Stop = &observe.Stop{Status: observe.CouldntStart, Reason: "baseline_rejected", HTTPStatus: 401}
	for i := 1; i < len(o.Deliveries); i++ {
		o.Deliveries[i] = observe.Delivery{N: i + 1}
	}
	v := judge.Judge(pl, o, pk, judge.Options{})
	if v.Status != judge.StatusCouldntStart || v.ExitCode != 2 || len(v.Findings) != 0 {
		t.Fatalf("ONCE-4: %s exit %d findings %v", v.Status, v.ExitCode, types(v))
	}
}

func TestNeedsProbeAndFailOn(t *testing.T) {
	pl, pk := setup(t, plan.Twice)
	v := judge.Judge(pl, obs(pl, false, false, nil), pk, judge.Options{})
	if v.Status != judge.StatusInconclusive || v.Reason != "needs_probe" || v.ExitCode != 0 || types(v)[judge.NeedsProbe] != judge.Info {
		t.Fatalf("TWICE-3: %s %s exit %d %v", v.Status, v.Reason, v.ExitCode, types(v))
	}
	v = judge.Judge(pl, obs(pl, false, false, nil), pk, judge.Options{FailOnInconclusive: true})
	if v.ExitCode != 1 {
		t.Fatalf("--fail-on-inconclusive: exit %d", v.ExitCode)
	}
	slow := map[int]d{2: {status: 200, lat: 12000}, 3: {status: 200, lat: 50}}
	for failOn, exit := range map[string]int{"high": 0, "medium": 1, "never": 0} {
		v = judge.Judge(pl, obs(pl, false, false, slow), pk, judge.Options{FailOn: failOn})
		if v.ExitCode != exit || v.Status != judge.StatusFindings {
			t.Errorf("fail-on %s: exit %d status %s", failOn, v.ExitCode, v.Status)
		}
	}
}

func TestColdStart(t *testing.T) {
	pl, pk := setup(t, plan.Twice, plan.SlowBudget)
	v := judge.Judge(pl, obs(pl, false, false, map[int]d{1: {status: 200, lat: 15000}}), pk, judge.Options{})
	if ty := types(v); ty[judge.ColdStart] != judge.Info || ty[judge.OverBudget] != "" {
		t.Fatalf("BUD-6: %v", ty)
	}
	v = judge.Judge(pl, obs(pl, false, false, map[int]d{1: {status: 200, lat: 15000}, 4: {status: 200, lat: 10001}}), pk, judge.Options{})
	if ty := types(v); ty[judge.OverBudget] == "" || ty[judge.ColdStart] != "" {
		t.Fatalf("D1 and D4 slow: %v", ty)
	}
	v = judge.Judge(pl, obs(pl, false, false, map[int]d{4: {status: 200, lat: 10000}}), pk, judge.Options{})
	if ty := types(v); ty[judge.OverBudget] != "" {
		t.Fatalf("equal to the budget is within (BUD-4): %v", ty)
	}
}

func TestDeterministic(t *testing.T) {
	pl, pk := setup(t, plan.Order...)
	o := obs(pl, true, true, map[int]d{3: {status: 200, before: "1", after: "2"}})
	a, _ := json.Marshal(judge.Judge(pl, o, pk, judge.Options{TargetPath: "/w"}))
	b, _ := json.Marshal(judge.Judge(pl, o, pk, judge.Options{TargetPath: "/w"}))
	if string(a) != string(b) {
		t.Fatal("same input, different verdict")
	}
}
