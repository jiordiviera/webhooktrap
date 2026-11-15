package judge

import (
	"crypto/sha256"
	"encoding/hex"
	"slices"
	"sort"
	"strconv"
	"strings"

	"github.com/jiordiviera/webhooktrap/agent/observe"
	"github.com/jiordiviera/webhooktrap/agent/pack"
	"github.com/jiordiviera/webhooktrap/agent/plan"
)

// BurstSpreadMaxMS is BURST-2's bound.
const BurstSpreadMaxMS = 20

type hit struct {
	typ       string
	eventType string
	scenario  string
	evidence  []Evidence
	level     int
	params    Params
	measured  []Measured
}

type judger struct {
	pl   *plan.Plan
	obs  *observe.Run
	pk   *pack.Pack
	opt  Options
	hits []hit
	// inconclusive reasons per scenario and per delivery, first wins
	scnReason map[string]string
	dReason   map[int]string
	expected  map[int][]string // expected reading per delivery (§8.5)
	flagged   map[int]bool
	probeOn   bool
	resetOn   bool
}

// Judge turns a plan and its observations into a verdict. Pure.
func Judge(pl *plan.Plan, obs *observe.Run, pk *pack.Pack, opt Options) *Verdict {
	if opt.FailOn == "" {
		opt.FailOn = High
	}
	j := &judger{
		pl: pl, obs: obs, pk: pk, opt: opt,
		scnReason: map[string]string{}, dReason: map[int]string{},
		expected: map[int][]string{}, flagged: map[int]bool{},
		probeOn: len(obs.ProbeNames) > 0, resetOn: obs.ResetConfigured,
	}
	j.generic()
	j.scenarios()
	j.infos()
	findings := j.merge()
	j.likelyCauses(findings)
	v := &Verdict{FailOn: opt.FailOn, FailOnInconclusive: opt.FailOnInconclusive, Levels: []int{1}}
	if j.probeOn {
		v.Levels = []int{1, 2}
	}
	v.OrderDependent = j.probeOn && !j.resetOn
	v.Framework = j.framework()
	fw := ""
	if v.Framework != nil {
		fw = v.Framework.Name
	}
	for i := range findings {
		findings[i].Framework = fw
		findings[i].Params.Framework = fw
	}
	number(findings)
	v.Findings = findings
	v.Deliveries = j.deliveryResults(findings)
	v.Scenarios = j.scenarioResults(findings, v.Deliveries)
	v.Counts = counts(findings)
	j.status(v)
	v.NotChecked = j.notChecked()
	return v
}

// ---- helpers over observations ----

func (j *judger) d(n int) *observe.Delivery { return j.obs.Delivery(n) }

func (j *judger) pd(n int) plan.Delivery { return j.pl.Deliveries[n-1] }

func isValid(mode string) bool {
	return mode == plan.ModeNormal || mode == plan.ModeDuplicate || mode == plan.ModeRetry
}

func ok2xx(o *observe.Delivery) bool {
	return o != nil && o.Responded && o.Status >= 200 && o.Status < 300
}

func unreachable(o *observe.Delivery) bool { return o != nil && observe.Unreachable(o.Transport) }

// failed: a transport failure (timeout, reset) after the request was sent.
func transportFailed(o *observe.Delivery) bool {
	return o != nil && !o.Responded && o.Transport != "" && !observe.Unreachable(o.Transport)
}

func done(o *observe.Delivery) bool { return o != nil && o.Completed }

// state returns before/after when the reading is usable.
func (j *judger) state(n int) (before, after []string, reason string) {
	o := j.d(n)
	if !j.probeOn {
		return nil, nil, "needs_probe"
	}
	if o == nil || o.State == nil || o.State.Failed || o.State.Before == nil || o.State.After == nil {
		return nil, nil, "probe_failed"
	}
	if o.State.Unsettled {
		return nil, nil, "unsettled"
	}
	return o.State.Before, o.State.After, ""
}

func (j *judger) scenarioDeliveries(s plan.Scenario) []int {
	for _, sp := range j.pl.Scenarios {
		if sp.Name == s && sp.NA == "" && j.obs.Scenario(string(s)).NA == "" {
			return sp.Deliveries
		}
	}
	return nil
}

func (j *judger) eventType(n int) string { return j.pl.Events[j.pd(n).Event].EventType }

func (j *judger) inconclusive(s plan.Scenario, reason string, ds ...int) {
	if _, ok := j.scnReason[string(s)]; !ok {
		j.scnReason[string(s)] = reason
	}
	for _, n := range ds {
		if _, ok := j.dReason[n]; !ok {
			j.dReason[n] = reason
		}
	}
}

func (j *judger) add(h hit) { j.hits = append(j.hits, h) }

func i64(v int64) *int64 { return &v }

func str(v string) *string { return &v }

func first(vals []string) *string {
	if len(vals) == 0 {
		return nil
	}
	return str(vals[0])
}

// entry picks the probe entry to show: the first whose values differ, else the first.
func entry(names []string, a, b []string) (string, int) {
	for i := range names {
		if i < len(a) && i < len(b) && a[i] != b[i] {
			return names[i], i
		}
	}
	if len(names) == 0 {
		return "", 0
	}
	return names[0], 0
}

func at(vals []string, i int) *string {
	if i < len(vals) {
		return str(vals[i])
	}
	return nil
}

// ---- Level 1 generic rules (GEN-1 to GEN-3, BUD-4 to BUD-6) ----

func (j *judger) generic() {
	budget := int64(j.pl.Budget.MS)
	baseline := j.obs.Stop != nil && j.obs.Stop.Reason == "baseline_rejected"
	// BUD-6: D1 over budget and every other valid delivery within → cold start.
	cold := false
	if o := j.d(1); o != nil && done(o) && o.Responded && o.LatencyMS > budget && isValid(j.pd(1).Mode) {
		cold = true
		var maxOther int64
		others := 0
		for _, pd := range j.pl.Deliveries[1:] {
			oo := j.d(pd.N)
			if !isValid(pd.Mode) || !done(oo) || !oo.Responded {
				continue
			}
			others++
			maxOther = max(maxOther, oo.LatencyMS)
			if oo.LatencyMS > budget {
				cold = false
			}
		}
		if others == 0 {
			cold = false
		}
		if cold {
			j.add(hit{typ: ColdStart, eventType: j.eventType(1), scenario: string(j.pd(1).Scenario), level: 1,
				evidence: []Evidence{{1, "offending"}},
				params:   Params{EventType: j.eventType(1), LatencyMS: i64(o.LatencyMS), MaxOtherMS: i64(maxOther), D: 1}})
		}
	}
	for _, pd := range j.pl.Deliveries {
		o := j.d(pd.N)
		if !isValid(pd.Mode) || !done(o) || unreachable(o) {
			continue
		}
		et := j.eventType(pd.N)
		sc := string(pd.Scenario)
		switch {
		case transportFailed(o) || (o.Responded && o.Status >= 500):
			p := Params{EventType: et, D: pd.N, Status: o.Status, Transport: o.Transport}
			m := Measured{D: pd.N, Status: o.Status, Transport: o.Transport}
			if o.Responded {
				m.LatencyMS = i64(o.LatencyMS)
			}
			j.add(hit{typ: ServerError, eventType: et, scenario: sc, level: 1, evidence: []Evidence{{pd.N, "offending"}}, params: p, measured: []Measured{m}})
		case o.Responded && o.Status >= 300 && o.Status < 500 && !(pd.N == 1 && baseline):
			j.add(hit{typ: Non2xxOnValid, eventType: et, scenario: sc, level: 1, evidence: []Evidence{{pd.N, "offending"}},
				params:   Params{EventType: et, D: pd.N, Status: o.Status},
				measured: []Measured{{D: pd.N, Status: o.Status, LatencyMS: i64(o.LatencyMS)}}})
		}
		if o.Responded && o.LatencyMS > budget && !(pd.N == 1 && cold) {
			over := o.LatencyMS - budget
			j.add(hit{typ: OverBudget, eventType: et, scenario: sc, level: 1, evidence: []Evidence{{pd.N, "offending"}},
				params:   Params{EventType: et, D: pd.N, LatencyMS: i64(o.LatencyMS), OverByMS: i64(over), BudgetMS: j.pl.Budget.MS, BudgetLabel: j.pl.Budget.Label},
				measured: []Measured{{D: pd.N, Status: o.Status, LatencyMS: i64(o.LatencyMS), OverByMS: i64(over)}}})
		}
	}
}

// overBudget reports GEN-3 on a delivery (after BUD-6).
func (j *judger) overBudget(n int) bool {
	for _, h := range j.hits {
		if h.typ == OverBudget && h.evidence[0].D == n {
			return true
		}
	}
	return false
}

// l1failed reports whether a valid delivery broke a Level 1 rule (GEN-1, GEN-2).
func l1failed(o *observe.Delivery) bool {
	return transportFailed(o) || (o != nil && o.Responded && (o.Status < 200 || o.Status >= 300))
}

// ---- scenario rules ----

func (j *judger) scenarios() {
	j.twice()
	j.shuffled()
	for _, s := range []plan.Scenario{plan.Late, plan.Tampered, plan.Unsigned} {
		j.invalid(s)
	}
	j.retry()
	j.burst()
	for _, sp := range j.pl.Scenarios {
		if j.obs.Scenario(string(sp.Name)).AsyncChanged {
			j.inconclusive(sp.Name, "unsettled")
		}
	}
}

// baselines returns B_primary and B_final when a reset ran before Once (ONCE-2).
func (j *judger) baselines() (bp, bf []string, reason string) {
	if !j.probeOn {
		return nil, nil, "needs_probe"
	}
	if !j.resetOn {
		return nil, nil, "needs_reset"
	}
	ds := j.scenarioDeliveries(plan.Once)
	if len(ds) == 0 {
		return nil, nil, "probe_failed"
	}
	_, a1, r1 := j.state(ds[0])
	_, an, rn := j.state(ds[len(ds)-1])
	if r1 != "" {
		return nil, nil, r1
	}
	if rn != "" {
		return nil, nil, rn
	}
	if j.obs.Scenario(string(plan.Once)).AsyncChanged {
		return nil, nil, "unsettled"
	}
	return a1, an, ""
}

func (j *judger) twice() {
	ds := j.scenarioDeliveries(plan.Twice)
	if len(ds) != 2 || !done(j.d(ds[0])) || !done(j.d(ds[1])) {
		return
	}
	a, b := ds[0], ds[1]
	oa, ob := j.d(a), j.d(b)
	if !ok2xx(oa) { // TWICE-4: no expectation on D_b
		return
	}
	_, afterA, ra := j.state(a)
	beforeB, afterB, rb := j.state(b)
	if ra == "" && rb == "" {
		j.expected[b] = afterA
		if !slices.Equal(afterA, afterB) && !j.obs.Scenario(string(plan.Twice)).AsyncChanged {
			j.flagged[b] = true
			j.stateHit(DuplicateChangedState, plan.Twice, a, b, beforeB, afterB, afterA)
		}
		return
	}
	if !ok2xx(ob) || l1failed(ob) {
		return
	}
	reason := ra
	if reason == "" {
		reason = rb
	}
	j.inconclusive(plan.Twice, reason, b)
}

// stateHit adds a Level 2 finding comparing an offending delivery to a reference.
func (j *judger) stateHit(typ string, s plan.Scenario, ref, off int, before, after, expected []string) {
	name, i := entry(j.obs.ProbeNames, after, expected)
	ev := []Evidence{{off, "offending"}}
	if ref > 0 {
		ev = []Evidence{{ref, "reference"}, {off, "offending"}}
	}
	o := j.d(off)
	m := Measured{D: off, Probe: name, Before: at(before, i), After: at(after, i), Expected: at(expected, i)}
	if o.Responded {
		m.Status, m.LatencyMS = o.Status, i64(o.LatencyMS)
	}
	j.add(hit{typ: typ, eventType: j.eventType(off), scenario: string(s), level: 2, evidence: ev,
		params:   Params{EventType: j.eventType(off), D: off, DRef: ref, Status: o.Status, Probe: name, Before: m.Before, After: m.After, Expected: m.Expected},
		measured: []Measured{m}})
}

func (j *judger) shuffled() {
	ds := j.scenarioDeliveries(plan.Shuffled)
	if len(ds) == 0 || !j.allDone(ds) {
		return
	}
	anyL1 := false
	for _, n := range ds {
		if l1failed(j.d(n)) {
			anyL1 = true
		}
	}
	last := ds[len(ds)-1]
	_, bf, reason := j.baselines()
	if reason == "" {
		var lb, la []string
		lb, la, reason = j.state(last)
		if reason == "" {
			j.expected[last] = bf
			if !slices.Equal(la, bf) {
				j.flagged[last] = true
				once := j.scenarioDeliveries(plan.Once)
				j.stateHit(OrderChangedState, plan.Shuffled, once[len(once)-1], last, lb, la, bf)
				return
			}
			return
		}
	}
	if reason == "needs_probe" && anyL1 {
		return
	}
	j.inconclusive(plan.Shuffled, reason, last)
}

func (j *judger) allDone(ds []int) bool {
	for _, n := range ds {
		if !done(j.d(n)) {
			return false
		}
	}
	return true
}

// invalid applies INV-1 to Late, Tampered and Unsigned.
func (j *judger) invalid(s plan.Scenario) {
	ds := j.scenarioDeliveries(s)
	if len(ds) != 1 || !done(j.d(ds[0])) {
		return
	}
	n := ds[0]
	o := j.d(n)
	if unreachable(o) {
		return
	}
	typ := map[plan.Scenario]string{plan.Late: AcceptsStale, plan.Tampered: AcceptsTampered, plan.Unsigned: AcceptsUnsigned}[s]
	before, after, reason := j.state(n)
	changed := reason == "" && !slices.Equal(before, after)
	if reason == "" {
		j.expected[n] = before
		j.flagged[n] = changed
	}
	pd := j.pd(n)
	et := j.eventType(n)
	name, i := entry(j.obs.ProbeNames, before, after)
	params := Params{EventType: et, D: n, Status: o.Status, Tamper: pd.Tamper}
	if s == plan.Late {
		params.TimestampOffsetS = pd.Signing.TimestampOffsetS
	}
	m := Measured{D: n, Status: o.Status, Transport: o.Transport}
	if o.Responded {
		m.LatencyMS = i64(o.LatencyMS)
		params.LatencyMS = m.LatencyMS
	}
	level := 1
	if reason == "" {
		m.Probe, m.Before, m.After, m.Expected = name, at(before, i), at(after, i), at(before, i)
		params.Probe, params.Before, params.After, params.Expected = m.Probe, m.Before, m.After, m.Expected
	}
	accept := func(lv int) {
		j.add(hit{typ: typ, eventType: et, scenario: string(s), level: lv, evidence: []Evidence{{n, "offending"}}, params: params, measured: []Measured{m}})
	}
	switch {
	case ok2xx(o):
		accept(level)
	case changed: // rejected or errored, but the write happened first
		accept(2)
	case o.Responded && o.Status >= 300 && o.Status < 400:
		if reason != "" {
			if j.probeOn {
				j.inconclusive(s, reason, n)
			} else {
				j.inconclusive(s, "redirected", n)
			}
		}
	case o.Responded && o.Status >= 400 && o.Status < 500:
		if reason != "" && j.probeOn {
			j.inconclusive(s, reason, n)
		}
	default: // 5xx or transport failure
		params.Transport = o.Transport
		j.add(hit{typ: ErrorOnInvalid, eventType: et, scenario: string(s), level: level, evidence: []Evidence{{n, "offending"}}, params: params, measured: []Measured{m}})
	}
}

// retry applies RETRY-4 and RETRY-5.
func (j *judger) retry() {
	ds := j.scenarioDeliveries(plan.Retry)
	if len(ds) != 2 || !j.allDone(ds) {
		return
	}
	a1, a2 := ds[0], ds[1]
	o1, o2 := j.d(a1), j.d(a2)
	if unreachable(o1) || unreachable(o2) {
		return
	}
	b1, af1, r1 := j.state(a1)
	b2, af2, r2 := j.state(a2)
	if ok2xx(o1) {
		if r1 == "" && r2 == "" {
			j.expected[a2] = af1
			if !slices.Equal(af1, af2) {
				j.flagged[a2] = true
				j.stateHit(DuplicateChangedState, plan.Retry, a1, a2, b2, af2, af1)
			}
			return
		}
		if l1failed(o2) {
			return
		}
		reason := r1
		if reason == "" {
			reason = r2
		}
		j.inconclusive(plan.Retry, reason, a2)
		return
	}
	// A1 failed in the provider's eyes (non-2xx or transport failure).
	if r1 != "" {
		if r1 != "needs_probe" {
			j.inconclusive(plan.Retry, r1, a1)
		}
		return
	}
	if !slices.Equal(b1, af1) {
		j.flagged[a1] = true
		j.expected[a1] = b1
		j.stateHit(PartialSideEffect, plan.Retry, 0, a1, b1, af1, b1)
		return
	}
	bp, _, rb := j.baselines()
	if rb == "" && r2 == "" {
		j.expected[a2] = bp
		if !slices.Equal(af2, bp) {
			j.flagged[a2] = true
			j.stateHit(RetryDiverged, plan.Retry, a1, a2, b2, af2, bp)
		}
		return
	}
	reason := rb
	if reason == "" {
		reason = r2
	}
	j.inconclusive(plan.Retry, reason, a2)
}

// burst applies BURST-2 to BURST-4.
func (j *judger) burst() {
	ds := j.scenarioDeliveries(plan.Burst)
	if len(ds) == 0 || !j.allDone(ds) {
		return
	}
	so := j.obs.Scenario(string(plan.Burst))
	if so.SpreadMS != nil && *so.SpreadMS > BurstSpreadMaxMS {
		j.inconclusive(plan.Burst, "not_simultaneous", ds...)
		return
	}
	anyL1 := false
	for _, n := range ds {
		if l1failed(j.d(n)) {
			anyL1 = true
		}
	}
	bp, bf, reason := j.baselines()
	if reason == "" {
		st := so.Burst
		switch {
		case st == nil || st.Failed || st.Before == nil || st.After == nil:
			reason = "probe_failed"
		case st.Unsettled:
			reason = "unsettled"
		}
		if reason == "" {
			want := bf
			if len(j.pl.Events) == 1 {
				want = bp
			}
			if !slices.Equal(st.After, want) && !so.AsyncChanged {
				name, i := entry(j.obs.ProbeNames, st.After, want)
				var ev []Evidence
				var ms []Measured
				for _, n := range ds {
					ev = append(ev, Evidence{n, "offending"})
				}
				ms = append(ms, Measured{D: ds[len(ds)-1], Probe: name, Before: at(st.Before, i), After: at(st.After, i), Expected: at(want, i)})
				j.add(hit{typ: ConcurrentChangedState, eventType: j.eventType(ds[0]), scenario: string(plan.Burst), level: 2, evidence: ev,
					params:   Params{EventType: j.eventType(ds[0]), D: ds[0], Probe: name, Before: at(st.Before, i), After: at(st.After, i), Expected: at(want, i)},
					measured: ms})
			}
			return
		}
	}
	if reason == "needs_probe" && anyL1 {
		return
	}
	j.inconclusive(plan.Burst, reason)
}

// ---- Info findings ----

func (j *judger) infos() {
	// needs_probe (TWICE-3, SHUF-2, RETRY-4, BURST-3).
	var np []Evidence
	for _, sp := range j.pl.Scenarios {
		if j.scnReason[string(sp.Name)] == "needs_probe" {
			for _, n := range sp.Deliveries {
				np = append(np, Evidence{n, "reference"})
			}
		}
	}
	if len(np) > 0 {
		j.add(hit{typ: NeedsProbe, eventType: j.pl.Events[0].EventType, level: 1, evidence: np,
			params: Params{EventType: j.pl.Events[0].EventType}})
	}
	// order_dependent (PRB-9, RUN-4).
	if j.probeOn && !j.resetOn {
		j.add(hit{typ: OrderDependent, eventType: j.pl.Events[0].EventType, level: 2, params: Params{EventType: j.pl.Events[0].EventType}})
	}
	// async_write (PRB-7).
	for _, sp := range j.pl.Scenarios {
		if j.obs.Scenario(string(sp.Name)).AsyncChanged && len(j.obs.ProbeNames) > 0 {
			ev := []Evidence{}
			if len(sp.Deliveries) > 0 {
				ev = append(ev, Evidence{sp.Deliveries[len(sp.Deliveries)-1], "offending"})
			}
			j.add(hit{typ: AsyncWrite, eventType: j.pl.Events[0].EventType, scenario: string(sp.Name), level: 2, evidence: ev,
				params: Params{EventType: j.pl.Events[0].EventType, Probe: j.obs.ProbeNames[0]}})
		}
	}
	// probe_gap (PRB-12).
	if !j.probeOn {
		return
	}
	for _, typ := range j.uncovered() {
		var ev []Evidence
		var scs []string
		for _, s := range []plan.Scenario{plan.Shuffled, plan.Burst} {
			for _, n := range j.scenarioDeliveries(s) {
				if j.pk.StatusObject(j.eventType(n)) == typ && done(j.d(n)) {
					ev = append(ev, Evidence{n, "offending"})
					if !slices.Contains(scs, string(s)) {
						scs = append(scs, string(s))
					}
				}
			}
		}
		if len(ev) == 0 {
			continue
		}
		et := j.eventType(ev[0].D)
		for _, s := range scs {
			j.add(hit{typ: ProbeGap, eventType: et, scenario: s, level: 2, evidence: ev,
				params: Params{EventType: et, ObjectType: typ, Probe: j.obs.ProbeNames[0]}})
		}
	}
}

// uncovered returns the status-event object types of the selection that no
// probe entry covers (PRB-12), in capture order.
func (j *judger) uncovered() []string {
	covered := map[string]bool{}
	for _, name := range j.obs.ProbeNames {
		seg, _, _ := strings.Cut(name, ".")
		covered[singular(seg)] = true
		for _, c := range j.opt.Covers[name] {
			covered[c] = true
		}
	}
	var out []string
	for _, e := range j.pl.Events {
		if t := j.pk.StatusObject(e.EventType); t != "" && !covered[t] && !slices.Contains(out, t) {
			out = append(out, t)
		}
	}
	return out
}

func singular(s string) string {
	switch {
	case strings.HasSuffix(s, "ies"):
		return strings.TrimSuffix(s, "ies") + "y"
	case strings.HasSuffix(s, "ses"):
		return strings.TrimSuffix(s, "es")
	case strings.HasSuffix(s, "s"):
		return strings.TrimSuffix(s, "s")
	}
	return s
}

// ---- merging, likely cause, numbering ----

func (j *judger) merge() []Finding {
	type key struct{ typ, et string }
	idx := map[key]int{}
	var out []Finding
	order := map[string]int{}
	for i, sp := range j.pl.Scenarios {
		order[string(sp.Name)] = i
	}
	for _, h := range j.hits {
		k := key{h.typ, h.eventType}
		if runLevel[h.typ] {
			k.et = ""
		}
		i, ok := idx[k]
		if !ok {
			idx[k] = len(out)
			out = append(out, Finding{Type: h.typ, Severity: SeverityOf[h.typ], Level: h.level, EventType: h.eventType,
				Scenarios: []string{}, Evidence: []Evidence{}, Params: h.params, Measured: []Measured{}})
			i = len(out) - 1
		}
		f := &out[i]
		f.Level = max(f.Level, h.level)
		if h.scenario != "" && !slices.Contains(f.Scenarios, h.scenario) {
			f.Scenarios = append(f.Scenarios, h.scenario)
		}
		for _, e := range h.evidence {
			found := false
			for k := range f.Evidence {
				if f.Evidence[k].D == e.D {
					found = true
					if e.Role == "offending" {
						f.Evidence[k].Role = "offending"
					}
				}
			}
			if !found {
				f.Evidence = append(f.Evidence, e)
			}
		}
		for _, m := range h.measured {
			if !slices.ContainsFunc(f.Measured, func(x Measured) bool { return x.D == m.D }) {
				f.Measured = append(f.Measured, m)
			}
		}
	}
	for i := range out {
		f := &out[i]
		sort.SliceStable(f.Scenarios, func(a, b int) bool { return order[f.Scenarios[a]] < order[f.Scenarios[b]] })
		sort.SliceStable(f.Evidence, func(a, b int) bool { return f.Evidence[a].D < f.Evidence[b].D })
		sort.SliceStable(f.Measured, func(a, b int) bool { return f.Measured[a].D < f.Measured[b].D })
		sum := sha256.Sum256([]byte(f.Type + "|" + j.pl.Provider + "|" + f.EventType + "|" + j.opt.TargetPath))
		f.Fingerprint = hex.EncodeToString(sum[:])[:12]
		if off := f.Offending(); len(off) > 0 {
			f.Params.D = off[0]
		}
		if f.Type == OverBudget {
			f.Params.SlowCount = len(f.Offending())
			f.Params.ValidCount = j.validCount(f.EventType)
		}
	}
	return out
}

// validCount counts valid deliveries of an event type that got a response.
func (j *judger) validCount(et string) int {
	c := 0
	for _, pd := range j.pl.Deliveries {
		if o := j.d(pd.N); isValid(pd.Mode) && done(o) && o.Responded && j.eventType(pd.N) == et {
			c++
		}
	}
	return c
}

// likelyCauses applies the FND-8 table: the first matching rule wins.
func (j *judger) likelyCauses(fs []Finding) {
	has := func(t string) bool { return slices.ContainsFunc(fs, func(f Finding) bool { return f.Type == t }) }
	rejected := func(s plan.Scenario) int {
		ds := j.scenarioDeliveries(s)
		if len(ds) != 1 {
			return 0
		}
		o := j.d(ds[0])
		if !done(o) || !o.Responded || o.Status < 400 || o.Status >= 500 {
			return 0
		}
		if b, a, r := j.state(ds[0]); r == "" && !slices.Equal(b, a) {
			return 0
		}
		return ds[0]
	}
	for i := range fs {
		f := &fs[i]
		switch f.Type {
		case AcceptsTampered:
			if d := rejected(plan.Late); d > 0 {
				f.LikelyCauseRule, f.Params.DRef = "LC-1", d
			} else if has(AcceptsStale) {
				f.LikelyCauseRule = "LC-2"
			}
		case AcceptsStale:
			if has(AcceptsTampered) {
				f.LikelyCauseRule = "LC-2"
			} else if rejected(plan.Tampered) > 0 {
				f.LikelyCauseRule = "LC-3"
			}
		case OverBudget:
			if 2*f.Params.SlowCount < f.Params.ValidCount {
				f.LikelyCauseRule = "LC-4"
			} else {
				f.LikelyCauseRule = "LC-5"
			}
		case Non2xxOnValid:
			for _, n := range f.Offending() {
				if j.pd(n).Scenario == plan.Shuffled && j.pd(n).Event > 0 {
					f.LikelyCauseRule = "LC-6"
				}
			}
		case ServerError:
			only := true
			for _, n := range f.Offending() {
				if s := j.pd(n).Scenario; s != plan.Shuffled && s != plan.Burst {
					only = false
				}
			}
			okOnce := false
			for _, n := range j.scenarioDeliveries(plan.Once) {
				if j.eventType(n) == f.EventType && ok2xx(j.d(n)) {
					okOnce = true
				}
			}
			if only && okOnce {
				f.LikelyCauseRule = "LC-7"
			}
		case DuplicateChangedState:
			ev := f.Evidence
			for k := 0; k+1 < len(ev); k++ {
				if ev[k].Role == "reference" && ev[k+1].Role == "offending" {
					a, b := j.d(ev[k].D), j.d(ev[k+1].D)
					if a.Responded && b.Responded && a.Status == b.Status && a.Response != nil && b.Response != nil && a.Response.BodySHA256 == b.Response.BodySHA256 {
						f.LikelyCauseRule = "LC-8"
					}
				}
			}
		case ConcurrentChangedState:
			if ds := j.scenarioDeliveries(plan.Shuffled); len(ds) > 0 && !has(OrderChangedState) {
				if _, ok := j.scnReason[string(plan.Shuffled)]; !ok && j.probeOn && j.resetOn {
					f.LikelyCauseRule = "LC-9"
				}
			}
		case PartialSideEffect:
			f.LikelyCauseRule = "LC-10"
		}
	}
}

// number assigns F-numbers (FND-6): severity, smallest offending D, type.
func number(fs []Finding) {
	minD := func(f *Finding) int {
		if off := f.Offending(); len(off) > 0 {
			return off[0]
		}
		return 1 << 30
	}
	sort.SliceStable(fs, func(a, b int) bool {
		ra, rb := Rank(fs[a].Severity), Rank(fs[b].Severity)
		if ra != rb {
			return ra < rb
		}
		if da, db := minD(&fs[a]), minD(&fs[b]); da != db {
			return da < db
		}
		return fs[a].Type < fs[b].Type
	})
	for i := range fs {
		fs[i].ID = "F" + strconv.Itoa(i+1)
	}
}

func counts(fs []Finding) Counts {
	var c Counts
	for _, f := range fs {
		switch f.Severity {
		case Critical:
			c.Critical++
		case High:
			c.High++
		case Medium:
			c.Medium++
		case Info:
			c.Info++
		}
	}
	c.Findings = c.Critical + c.High + c.Medium
	return c
}

// ---- results ----

func (j *judger) framework() *Framework {
	if j.opt.Framework != "" {
		return &Framework{Name: j.opt.Framework, DetectedFrom: "webhooktrap_yml"}
	}
	for _, o := range j.obs.Deliveries {
		if o.Framework != "" {
			from := "x_powered_by"
			return &Framework{Name: o.Framework, DetectedFrom: from}
		}
	}
	return nil
}

func (j *judger) deliveryResults(fs []Finding) []DeliveryResult {
	sev := map[int]string{}
	for _, f := range fs {
		if f.Severity == Info {
			continue
		}
		for _, n := range f.Offending() {
			if cur, ok := sev[n]; !ok || Rank(f.Severity) < Rank(cur) {
				sev[n] = f.Severity
			}
		}
	}
	var out []DeliveryResult
	for _, pd := range j.pl.Deliveries {
		o := j.d(pd.N)
		r := DeliveryResult{N: pd.N, OverBudget: j.overBudget(pd.N)}
		switch {
		case o == nil || !o.Sent:
			r.Result = ResultNotRun
		case !o.Completed:
			r.Result = ResultSentNoResult
		case unreachable(o):
			r.Result = ResultUnreachable
		case sev[pd.N] != "":
			r.Result, r.Severity = ResultFinding, sev[pd.N]
		case j.dReason[pd.N] != "":
			r.Result, r.Reason = ResultInconclusive, j.dReason[pd.N]
		default:
			r.Result = ResultPass
		}
		if o != nil && o.State != nil && j.probeOn {
			exp, hasExp := j.expected[pd.N]
			for i, name := range j.obs.ProbeNames {
				rd := Reading{Name: name, Before: at(o.State.Before, i), After: at(o.State.After, i)}
				if rd.Before != nil && rd.After != nil {
					rd.Changed = *rd.Before != *rd.After
				}
				if hasExp && rd.After != nil {
					rd.Expected = at(exp, i)
					if rd.Expected != nil {
						m := *rd.Expected == *rd.After
						rd.MatchesExpected = &m
						rd.Flagged = !m && j.flagged[pd.N]
					}
				}
				r.Readings = append(r.Readings, rd)
			}
		}
		out = append(out, r)
	}
	return out
}

func (j *judger) scenarioResults(fs []Finding, drs []DeliveryResult) []ScenarioResult {
	var out []ScenarioResult
	for _, sp := range j.pl.Scenarios {
		sr := ScenarioResult{Name: string(sp.Name), Deliveries: append([]int{}, sp.Deliveries...), Findings: []string{}}
		so := j.obs.Scenario(string(sp.Name))
		sr.SpreadMS = so.SpreadMS
		na, naText := sp.NA, sp.NAText
		if na == "" && so.NA != "" {
			na, naText = so.NA, so.NAText
		}
		if na != "" {
			sr.Result, sr.Reason, sr.ReasonText = ResultNA, na, naText
			out = append(out, sr)
			continue
		}
		for _, f := range fs {
			if slices.Contains(f.Scenarios, sr.Name) {
				sr.Findings = append(sr.Findings, f.ID)
			}
		}
		finding, unr, notRun := false, false, false
		for _, n := range sp.Deliveries {
			switch drs[n-1].Result {
			case ResultFinding:
				finding = true
			case ResultUnreachable:
				unr = true
			case ResultNotRun, ResultSentNoResult:
				notRun = true
			}
		}
		for _, f := range fs {
			if f.Severity != Info && slices.Contains(f.Scenarios, sr.Name) {
				finding = true
			}
		}
		switch {
		case finding:
			sr.Result = ResultFinding
		case unr:
			sr.Result, sr.Reason = ResultUnreachable, "unreachable"
		case notRun:
			sr.Result = ResultNotRun
		case j.scnReason[sr.Name] != "":
			sr.Result, sr.Reason = ResultInconclusive, j.scnReason[sr.Name]
		default:
			sr.Result = ResultPass
		}
		sr.Reading = j.reading(sp)
		out = append(out, sr)
	}
	return out
}

// reading builds the scenario's state line (before → after).
func (j *judger) reading(sp plan.ScenarioPlan) *ScenarioReading {
	if !j.probeOn || len(sp.Deliveries) == 0 {
		return nil
	}
	if sp.Name == plan.Burst {
		st := j.obs.Scenario(string(plan.Burst)).Burst
		if st == nil || st.Failed {
			return nil
		}
		r := &ScenarioReading{Kind: "burst_final", Probe: j.obs.ProbeNames[0], Before: first(st.Before), After: first(st.After)}
		if _, bf, reason := j.baselines(); reason == "" {
			want := bf
			if len(j.pl.Events) == 1 {
				want, _, _ = j.baselines()
			}
			r.Expected = first(want)
			r.Flagged = !slices.Equal(st.After, want)
		}
		return r
	}
	firstD, lastD := j.d(sp.Deliveries[0]), j.d(sp.Deliveries[len(sp.Deliveries)-1])
	if firstD == nil || lastD == nil || firstD.State == nil || lastD.State == nil {
		return nil
	}
	before, after := firstD.State.Before, lastD.State.After
	// Show the flagged delivery's reading when one is flagged.
	for _, n := range sp.Deliveries {
		if j.flagged[n] {
			o := j.d(n)
			name, i := entry(j.obs.ProbeNames, o.State.After, j.expected[n])
			return &ScenarioReading{Kind: "change", Probe: name, Before: at(o.State.Before, i), After: at(o.State.After, i), Expected: at(j.expected[n], i), Flagged: true}
		}
	}
	if before == nil || after == nil {
		return nil
	}
	return &ScenarioReading{Kind: "change", Probe: j.obs.ProbeNames[0], Before: first(before), After: first(after)}
}

// status applies RUN-1 and EXIT-1.
func (j *judger) status(v *Verdict) {
	anyUnreachable := false
	for _, s := range v.Scenarios {
		if s.Result == ResultUnreachable {
			anyUnreachable = true
		}
	}
	switch {
	case j.obs.Stop != nil && j.obs.Stop.Status == observe.CouldntStart:
		v.Status, v.Reason, v.HTTPStatus = StatusCouldntStart, j.obs.Stop.Reason, j.obs.Stop.HTTPStatus
	case j.obs.Stop != nil:
		v.Status, v.Reason = StatusAborted, j.obs.Stop.Reason
	case v.Counts.Findings > 0:
		v.Status = StatusFindings
	default:
		reason := ""
		allNA := len(v.Scenarios) > 0
		for _, s := range v.Scenarios {
			if s.Result != ResultNA {
				allNA = false
			}
			if reason == "" && (s.Result == ResultInconclusive || s.Result == ResultUnreachable) {
				reason = s.Reason
			}
		}
		if allNA {
			reason = "nothing_judged"
		}
		if reason != "" {
			v.Status, v.Reason = StatusInconclusive, reason
		} else {
			v.Status = StatusPassed
		}
	}
	switch {
	case v.Status == StatusCouldntStart || v.Status == StatusAborted || anyUnreachable:
		v.ExitCode = 2
	case failsOn(v.Findings, v.FailOn):
		v.ExitCode = 1
	case v.Status == StatusInconclusive && v.FailOnInconclusive:
		v.ExitCode = 1
	default:
		v.ExitCode = 0
	}
}

func failsOn(fs []Finding, failOn string) bool {
	if failOn == "never" {
		return false
	}
	limit := Rank(failOn)
	for _, f := range fs {
		if f.Severity != Info && Rank(f.Severity) <= limit {
			return true
		}
	}
	return false
}

// notChecked builds RUN-7's list, in its order.
func (j *judger) notChecked() []NotChecked {
	var out []NotChecked
	if !j.probeOn {
		out = append(out, NotChecked{Code: "no_probe"})
	} else if u := j.uncovered(); len(u) > 0 {
		out = append(out, NotChecked{Code: "uncovered_objects", ObjectTypes: u})
	}
	if j.probeOn && !j.resetOn {
		out = append(out, NotChecked{Code: "no_reset"})
	}
	return append(out, NotChecked{Code: "side_effects"})
}
