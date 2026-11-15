package render

import (
	"strings"
	"unicode/utf8"

	"github.com/jiordiviera/webhooktrap/agent/judge"
	"github.com/jiordiviera/webhooktrap/agent/observe"
	"github.com/jiordiviera/webhooktrap/agent/plan"
	"github.com/jiordiviera/webhooktrap/agent/scrub"
)

// Profile is a terminal profile (golden-files §2.1). Colour and live mode
// are not built yet; text is always the u-plain / a-plain form.
type Profile struct {
	ASCII bool
	Width int // 100 by default
}

// Unicode is the default profile (u-plain-100).
var Unicode = Profile{Width: 100}

// ASCIIProfile is a-plain-100.
var ASCIIProfile = Profile{ASCII: true, Width: 100}

type glyphs struct{ pass, crit, high, med, info, stop, arrow, dot, times, dash, minus, prime, none string }

func (p Profile) g() glyphs {
	if p.ASCII {
		return glyphs{"[-]", "[C]", "[H]", "[M]", "[I]", "//", "->", "|", "x", "-", "-", "'", "-"}
	}
	return glyphs{"▬", "■", "▲", "△", "○", "⫽", "→", "·", "×", "–", "−", "′", "—"}
}

func (p Profile) width() int {
	if p.Width < 80 {
		return 100
	}
	return p.Width
}

func (p Profile) sevGlyph(sev string) string {
	g := p.g()
	switch sev {
	case judge.Critical:
		return g.crit
	case judge.High:
		return g.high
	case judge.Medium:
		return g.med
	case judge.Info:
		return g.info
	}
	return g.pass
}

// SeverityWord is the word shown next to the glyph.
func SeverityWord(sev string) string {
	switch sev {
	case judge.Critical:
		return "Critical"
	case judge.High:
		return "High"
	case judge.Medium:
		return "Medium"
	case judge.Info:
		return "Info"
	}
	return sev
}

// Text renders the whole run for a terminal: header, delivery rows, the
// scenario summary (PASS/FAIL lines), the findings list, each finding's
// details, and the status and exit lines. Pure over (Input, Profile).
func Text(in Input, p Profile) string {
	var b strings.Builder
	header(&b, in, p)
	rows(&b, in, p)
	summary(&b, in, p)
	findings(&b, in, p)
	footer(&b, in, p)
	if p.ASCII {
		// Layout glyphs are already ASCII; this maps characters that come
		// from pack prose and values (§3.2: bytes 0x0A and 0x20–0x7E only).
		return asciify(b.String())
	}
	return b.String()
}

var asciiMap = map[rune]string{
	'→': "->", '·': "|", '×': "x", '–': "-", '−': "-", '′': "'", '›': ">", '…': "...",
	'≤': "<=", '≥': ">=", '—': "-", '▬': "[-]", '■': "[C]", '▲': "[H]", '△': "[M]", '○': "[I]", '⫽': "//",
}

func asciify(s string) string {
	var b strings.Builder
	for _, r := range s {
		switch {
		case r == '\n' || (r >= 0x20 && r <= 0x7e):
			b.WriteRune(r)
		case asciiMap[r] != "":
			b.WriteString(asciiMap[r])
		default:
			b.WriteByte('?')
		}
	}
	return b.String()
}

func header(b *strings.Builder, in Input, p Profile) {
	g := p.g()
	pl, v := in.Plan, in.Verdict
	title := "Webhook Trap " + g.dot + " webhooktrap test " + g.dot + " " + clean(in.Pack.Name) + " " + clean(pl.Events[0].EventType)
	line(b, title)
	line(b, "  target    "+in.Meta.Target.String())
	if v.Framework != nil {
		from := "detected from X-Powered-By"
		if v.Framework.DetectedFrom == "webhooktrap_yml" {
			from = "set in webhooktrap.yml"
		}
		line(b, "  handler   "+frameworkName(v.Framework.Name)+" ("+from+")")
	}
	var evs []string
	for i, e := range pl.Events {
		evs = append(evs, "#"+itoa(i+1)+" "+clean(e.EventType))
	}
	line(b, "  events    "+strings.Join(evs, " "+g.dot+" "))
	checks := "Level 1 transport"
	if len(in.Obs.ProbeNames) > 0 {
		checks += " " + g.dot + " Level 2 probe " + strings.Join(in.Obs.ProbeNames, ", ")
		if in.Obs.ResetConfigured {
			checks += " " + g.dot + " reset between scenarios"
		} else {
			checks += " " + g.dot + " no reset"
		}
	}
	line(b, "  checks    "+checks)
	line(b, "  budget    "+num(int64(pl.Budget.MS))+" ms ("+budgetSource(in)+")")
	for _, w := range in.Meta.Warnings {
		for i, l := range wrap(clean(w), p.width()-4-12) {
			prefix := strings.Repeat(" ", 12)
			if i == 0 {
				prefix = "  warning   "
			}
			line(b, prefix+l)
		}
	}
	line(b, "")
}

func budgetSource(in Input) string {
	if s := in.Plan.Budget.SourceLabel; s != "" {
		return s
	}
	if in.Plan.Budget.Source == "webhooktrap.yml" {
		return "webhooktrap.yml"
	}
	return in.Pack.Budget.SourceLabel
}

// row cells for one delivery.
type cells struct{ d, off, event, http, lat, state, result string }

func rows(b *strings.Builder, in Input, p Profile) {
	g := p.g()
	pl, v, obs := in.Plan, in.Verdict, in.Obs
	probe := len(obs.ProbeNames) > 0
	all := make([]cells, len(pl.Deliveries))
	ew, sw := len("event"), len("state")
	for i, pd := range pl.Deliveries {
		c := deliveryCells(in, pd, &obs.Deliveries[i], &v.Deliveries[i], p)
		all[i] = c
		ew = max(ew, width(c.event))
		sw = max(sw, width(c.state))
	}
	hdr := "    " + pad("D", 3) + " " + pad(" +ms", 8) + "  " + pad("event", ew) + "  " + "http" + " " + padLeft("latency", 9)
	if probe {
		hdr += "  " + pad("state", sw)
	}
	line(b, hdr+"  result")
	announced := map[string]bool{}
	for _, sp := range pl.Scenarios {
		if len(sp.Deliveries) == 0 {
			continue
		}
		line(b, "  "+sp.Name.Label())
		for _, n := range sp.Deliveries {
			c := all[n-1]
			r := "    " + pad(c.d, 3) + " " + padLeft(c.off, 8) + "  " + pad(c.event, ew) + "  " + pad(c.http, 3) + "  " + padLeft(c.lat, 9)
			if probe {
				r += "  " + pad(c.state, sw)
			}
			line(b, strings.TrimRight(r+"  "+c.result, " "))
			for _, f := range v.Findings {
				if !announced[f.ID] && announceAt(in, &f, sp) == n {
					announced[f.ID] = true
					line(b, "          new finding  "+p.sevGlyph(f.Severity)+" "+SeverityWord(f.Severity)+" "+g.dot+" "+Prose(in, &f).Title)
				}
			}
		}
		if sp.Name == plan.Burst {
			if s := burstLine(in, p); s != "" {
				line(b, s)
			}
		}
	}
	line(b, "")
}

// announceAt returns the D-number after which a finding's "new finding"
// line is printed: its first offending delivery, or the scenario's last
// delivery for run-level Info findings. No F-number appears here (FND-6a).
func announceAt(in Input, f *judge.Finding, sp plan.ScenarioPlan) int {
	if len(f.Scenarios) > 0 && f.Severity == judge.Info {
		if f.Scenarios[0] == string(sp.Name) {
			return sp.Deliveries[len(sp.Deliveries)-1]
		}
		return 0
	}
	if off := f.Offending(); len(off) > 0 {
		for _, n := range sp.Deliveries {
			if n == off[0] {
				return n
			}
		}
	}
	return 0
}

func deliveryCells(in Input, pd plan.Delivery, o *observe.Delivery, dr *judge.DeliveryResult, p Profile) cells {
	g := p.g()
	c := cells{d: "D" + itoa(pd.N)}
	if o.Sent {
		c.off = "+" + num(o.OffsetMS)
	}
	ev := clean(in.Plan.Events[pd.Event].EventType)
	switch pd.Mode {
	case plan.ModeDuplicate:
		ev += " = D" + itoa(refOf(in.Plan, pd)) + g.prime
	case plan.ModeRetry:
		ev += " = D" + itoa(refOf(in.Plan, pd)) + g.prime + " (retry)"
	case plan.ModeLate:
		ev += "  t " + g.minus + " " + itoa(-pd.Signing.TimestampOffsetS) + " s"
	case plan.ModeTampered:
		ev += "  body changed"
	case plan.ModeUnsigned:
		ev += "  no signature"
	}
	c.event = ev
	switch {
	case o.Responded:
		c.http = itoa(o.Status)
		c.lat = num(o.LatencyMS) + " ms"
	case o.Transport != "":
		c.http = "-"
		c.lat = o.Transport
	}
	if pd.Concurrent {
		c.state = g.none
	} else if o.State != nil {
		switch {
		case o.State.Failed:
			c.state = "failed"
		case len(o.State.Before) > 0 && len(o.State.After) > 0:
			c.state = short(o.State.Before[0]) + " " + g.arrow + " " + short(o.State.After[0])
		}
	}
	c.result = resultCell(in, pd, o, dr, p)
	return c
}

func refOf(pl *plan.Plan, pd plan.Delivery) int {
	if pd.N > 1 {
		return pl.Deliveries[pd.N-2].N
	}
	return pd.N
}

func resultCell(in Input, pd plan.Delivery, o *observe.Delivery, dr *judge.DeliveryResult, p Profile) string {
	g := p.g()
	switch dr.Result {
	case judge.ResultNotRun:
		return g.stop + " not run"
	case judge.ResultSentNoResult:
		return g.stop + " sent, no result"
	case judge.ResultUnreachable:
		return g.stop + " unreachable"
	case judge.ResultInconclusive:
		return g.none + " inconclusive (" + dr.Reason + ")"
	case judge.ResultFinding:
		s := p.sevGlyph(dr.Severity) + " " + SeverityWord(dr.Severity)
		for _, r := range dr.Readings {
			if r.Flagged && r.Expected != nil {
				return s + ", expected " + short(*r.Expected)
			}
		}
		if dr.OverBudget {
			return s + ", +" + num(o.LatencyMS-int64(in.Plan.Budget.MS)) + " over"
		}
		if pd.Mode == plan.ModeLate || pd.Mode == plan.ModeTampered || pd.Mode == plan.ModeUnsigned {
			return s + ", expected 4xx"
		}
		return s
	}
	if (pd.Mode == plan.ModeLate || pd.Mode == plan.ModeTampered || pd.Mode == plan.ModeUnsigned) && o.Responded && o.Status >= 400 {
		return g.pass + " pass (rejected)"
	}
	return g.pass + " pass"
}

func burstLine(in Input, p Profile) string {
	g := p.g()
	var sr *judge.ScenarioResult
	for i := range in.Verdict.Scenarios {
		if in.Verdict.Scenarios[i].Name == string(plan.Burst) {
			sr = &in.Verdict.Scenarios[i]
		}
	}
	if sr == nil {
		return ""
	}
	var parts []string
	if r := sr.Reading; r != nil && r.Before != nil && r.After != nil {
		s := short(*r.Before) + " " + g.arrow + " " + short(*r.After) + " after the burst"
		if r.Expected != nil {
			s += ", expected " + short(*r.Expected)
		}
		parts = append(parts, s)
	}
	if sr.SpreadMS != nil {
		parts = append(parts, "released within "+num(*sr.SpreadMS)+" ms")
	}
	if len(parts) == 0 {
		return ""
	}
	res := g.pass + " pass"
	switch sr.Result {
	case judge.ResultFinding:
		res = g.high + " High"
		for _, f := range in.Verdict.Findings {
			if f.Type == judge.ConcurrentChangedState {
				res = p.sevGlyph(f.Severity) + " " + SeverityWord(f.Severity)
			}
		}
	case judge.ResultInconclusive:
		res = g.none + " inconclusive (" + sr.Reason + ")"
	}
	return "          final state  " + strings.Join(parts, " "+g.dot+" ") + "  " + res
}

// summary prints the landing's PASS/FAIL lines, one per scenario.
func summary(b *strings.Builder, in Input, p Profile) {
	tags := map[string]string{
		judge.ResultPass: "PASS", judge.ResultFinding: "FAIL", judge.ResultInconclusive: "INCONCLUSIVE",
		judge.ResultUnreachable: "UNREACHABLE", judge.ResultNA: "N/A", judge.ResultNotRun: "NOT RUN",
	}
	tw, nw, dw := 4, 0, 0
	for _, s := range in.Verdict.Scenarios {
		tw = max(tw, len(tags[s.Result]))
		nw = max(nw, len(plan.Scenario(s.Name).Label()))
		dw = max(dw, len(dlist(s.Deliveries)))
	}
	for _, s := range in.Verdict.Scenarios {
		l := " " + pad(tags[s.Result], tw) + " " + pad(plan.Scenario(s.Name).Label(), nw+2) + " " + pad(dlist(s.Deliveries), dw+2) + " " + scenarioDetail(in, s, p)
		line(b, strings.TrimRight(l, " "))
	}
	line(b, "")
}

func dlist(ds []int) string {
	var xs []string
	for _, n := range ds {
		xs = append(xs, "D"+itoa(n))
	}
	return strings.Join(xs, " ")
}

// scenarioDetail is the one-line reading of a scenario (strip line 3).
func scenarioDetail(in Input, s judge.ScenarioResult, p Profile) string {
	g := p.g()
	if s.Result == judge.ResultNA {
		text := s.ReasonText
		if text == "" {
			text = s.Reason
		}
		return "n/a (" + text + ")"
	}
	if s.Result == judge.ResultNotRun {
		return "not run"
	}
	obs := func(n int) *observe.Delivery { return &in.Obs.Deliveries[n-1] }
	var statuses []int
	var lats []int64
	for _, n := range s.Deliveries {
		if o := obs(n); o.Responded {
			statuses = append(statuses, o.Status)
			lats = append(lats, o.LatencyMS)
		}
	}
	reading := ""
	if r := s.Reading; r != nil && r.Before != nil && r.After != nil {
		reading = r.Probe + " " + short(*r.Before) + " " + g.arrow + " " + short(*r.After)
	}
	var parts []string
	add := func(x string) {
		if x != "" {
			parts = append(parts, x)
		}
	}
	name := plan.Scenario(s.Name)
	switch name {
	case plan.Late, plan.Tampered, plan.Unsigned:
		o := obs(s.Deliveries[0])
		switch {
		case o.Responded && o.Status >= 400 && o.Status < 500 && s.Result != judge.ResultFinding:
			add(itoa(o.Status) + " in " + num(o.LatencyMS) + " ms")
			add("rejected as expected")
		case o.Responded:
			add("expected 4xx " + g.dot + " got " + itoa(o.Status) + " in " + num(o.LatencyMS) + " ms")
			if s.Reading != nil && s.Reading.Before != nil && *s.Reading.Before != *s.Reading.After {
				add(reading)
			}
		default:
			add(o.Transport)
		}
	case plan.Retry:
		o1 := obs(s.Deliveries[0])
		first := o1.Transport
		if o1.Responded {
			first = num(o1.LatencyMS) + " ms"
			if o1.Status < 200 || o1.Status >= 300 {
				first = itoa(o1.Status) + " in " + first
			}
		}
		if reading != "" {
			add(first + " " + g.arrow + " retry " + g.arrow + " " + flaggedReading(s, reading))
		} else {
			add(first + " " + g.arrow + " retry " + g.arrow + " " + statusList(statuses[1:], g))
		}
	case plan.Burst:
		add(statusList(statuses, g))
		if len(lats) > 1 {
			lo, hi := lats[0], lats[0]
			for _, l := range lats {
				lo, hi = min(lo, l), max(hi, l)
			}
			if lo == hi {
				add(num(lo) + " ms")
			} else {
				add(num(lo) + g.dash + num(hi) + " ms")
			}
		}
		if reading != "" {
			add(reading + " (final)")
		}
	case plan.SlowBudget:
		o := obs(s.Deliveries[0])
		if o.Responded {
			over := o.LatencyMS - int64(in.Plan.Budget.MS)
			if over > 0 {
				add(num(o.LatencyMS) + " ms")
				add(num(over) + " ms over budget")
			} else {
				add(itoa(o.Status) + " in " + num(o.LatencyMS) + " ms")
				add("within budget")
			}
		} else {
			add(o.Transport)
		}
	case plan.Shuffled:
		add("reversed")
		add(flaggedReading(s, reading))
	default:
		if s.Reading != nil && s.Reading.Flagged {
			add(flaggedReading(s, reading))
		} else {
			add(statusList(statuses, g))
			add(reading)
		}
	}
	if s.Result == judge.ResultInconclusive {
		add("inconclusive (" + s.Reason + ")")
	}
	return strings.Join(parts, " "+g.dot+" ")
}

func flaggedReading(s judge.ScenarioResult, reading string) string {
	r := s.Reading
	if r == nil || !r.Flagged || r.Expected == nil {
		return reading
	}
	on := ""
	for i := len(s.Deliveries) - 1; i >= 0; i-- {
		on = " on D" + itoa(s.Deliveries[i])
		break
	}
	return reading + on + ", expected " + short(*r.Expected)
}

func statusList(st []int, g glyphs) string {
	if len(st) == 0 {
		return ""
	}
	same := true
	for _, s := range st {
		if s != st[0] {
			same = false
		}
	}
	if same && len(st) > 2 {
		return itoa(st[0]) + " " + g.times + " " + itoa(len(st))
	}
	var xs []string
	for _, s := range st {
		xs = append(xs, itoa(s))
	}
	return strings.Join(xs, ", ")
}

func findings(b *strings.Builder, in Input, p Profile) {
	v := in.Verdict
	if len(v.Findings) == 0 {
		return
	}
	g := p.g()
	tw := 0
	for _, f := range v.Findings {
		tw = max(tw, width(Prose(in, &f).Title))
	}
	for _, f := range v.Findings {
		ds := dlist(f.Offending())
		line(b, strings.TrimRight(" "+p.sevGlyph(f.Severity)+" "+pad(f.ID, 3)+" "+pad(SeverityWord(f.Severity), 8)+" "+pad(Prose(in, &f).Title, tw)+"  "+ds, " "))
	}
	line(b, "")
	w := p.width() - 4
	for _, f := range v.Findings {
		t := Prose(in, &f)
		head := p.sevGlyph(f.Severity) + " " + f.ID + " " + SeverityWord(f.Severity) + " " + g.dot + " " + t.Title
		ev := evidenceText(&f, g)
		if width(head)+2+width(ev) <= w {
			line(b, head+strings.Repeat(" ", w-width(head)-width(ev))+ev)
		} else {
			line(b, head)
			line(b, strings.Repeat(" ", max(0, w-width(ev)))+ev)
		}
		what := t.What
		if t.LikelyCause != "" {
			what += " Likely cause: " + t.LikelyCause
		}
		beat(b, "What happened", what, w)
		why := t.Why
		beat(b, "Why it matters", why, w)
		if t.Citation.Label != "" {
			label := t.Citation.Label
			if p.ASCII {
				label = strings.ReplaceAll(strings.ReplaceAll(label, "›", ">"), "·", "|")
			}
			line(b, strings.Repeat(" ", 18)+label)
		}
		beat(b, "Fix", t.Fix, w)
		if t.Snippet != "" {
			line(b, strings.Repeat(" ", 18)+"Snippet ("+t.SnippetFor+"):")
			for _, l := range strings.Split(t.Snippet, "\n") {
				line(b, strings.TrimRight(strings.Repeat(" ", 20)+l, " "))
			}
		}
		line(b, "")
	}
}

// evidenceText groups evidence by scenario: "D4 D5 · D10 D11".
func evidenceText(f *judge.Finding, g glyphs) string {
	var groups []string
	var cur []string
	prev := -10
	for _, e := range f.Evidence {
		if e.D != prev+1 && len(cur) > 0 {
			groups = append(groups, strings.Join(cur, " "))
			cur = nil
		}
		cur = append(cur, "D"+itoa(e.D))
		prev = e.D
	}
	if len(cur) > 0 {
		groups = append(groups, strings.Join(cur, " "))
	}
	return strings.Join(groups, " "+g.dot+" ")
}

func beat(b *strings.Builder, label, text string, w int) {
	if p := strings.TrimSpace(text); p == "" {
		return
	}
	lines := wrap(clean(text), w-18)
	for i, l := range lines {
		prefix := strings.Repeat(" ", 18)
		if i == 0 {
			prefix = "  " + pad(label, 16)
		}
		line(b, prefix+l)
	}
}

func footer(b *strings.Builder, in Input, p Profile) {
	g := p.g()
	v := in.Verdict
	c := v.Counts
	switch v.Status {
	case judge.StatusFindings:
		word := "findings"
		if c.Findings == 1 {
			word = "finding"
		}
		line(b, itoa(c.Findings)+" "+word+" "+g.dot+" "+countsLine(c, g))
	case judge.StatusPassed:
		s := "Passed"
		if c.Info > 0 {
			s += " " + g.dot + " " + itoa(c.Info) + " Info"
		}
		line(b, s)
		for _, l := range wrap(passedLine(in), p.width()-4) {
			line(b, l)
		}
	case judge.StatusInconclusive:
		line(b, "Inconclusive (not passed) "+g.dot+" "+v.Reason)
	case judge.StatusCouldntStart:
		line(b, "Couldn't start the run "+g.dot+" "+v.Reason)
		if s := couldntStart(in); s != "" {
			for _, l := range wrap(s, p.width()-4) {
				line(b, l)
			}
		}
	case judge.StatusAborted:
		line(b, "Aborted "+g.dot+" "+v.Reason)
	}
	sent := 0
	for _, o := range in.Obs.Deliveries {
		if o.Sent {
			sent++
		}
	}
	run := 0
	for _, s := range v.Scenarios {
		if s.Result != judge.ResultNA {
			run++
		}
	}
	dl := itoa(len(in.Plan.Deliveries)) + " deliveries"
	if sent != len(in.Plan.Deliveries) {
		dl = itoa(sent) + " of " + itoa(len(in.Plan.Deliveries)) + " deliveries"
	}
	line(b, itoa(run)+" scenarios "+g.dot+" "+dl+" "+g.dot+" "+seconds(in.Obs.DurationMS)+" "+g.dot+" "+in.Meta.RunID)
}

// ExitLine is the last line the CLI prints (landing: "exit 1 --fail-on high").
func ExitLine(v *judge.Verdict) string {
	s := "exit " + itoa(v.ExitCode) + " --fail-on " + v.FailOn
	if v.FailOnInconclusive {
		s += " --fail-on-inconclusive"
	}
	return s
}

func countsLine(c judge.Counts, g glyphs) string {
	var xs []string
	for _, kv := range []struct {
		n    int
		word string
	}{{c.Critical, "Critical"}, {c.High, "High"}, {c.Medium, "Medium"}, {c.Info, "Info"}} {
		if kv.n > 0 {
			xs = append(xs, itoa(kv.n)+" "+kv.word)
		}
	}
	return strings.Join(xs, " "+g.dot+" ")
}

// passedLine is RUN-7.
func passedLine(in Input) string {
	k := 0
	for _, s := range in.Verdict.Scenarios {
		if s.Result != judge.ResultNA {
			k++
		}
	}
	s := itoa(k) + " of " + itoa(k) + " scenarios passed at Level 1"
	if len(in.Obs.ProbeNames) > 0 {
		s += " + Level 2 (" + strings.Join(in.Obs.ProbeNames, ", ") + ")"
	}
	return s + ". Not checked: " + strings.Join(notCheckedText(in.Verdict, in.Obs), " · ") + "."
}

func notCheckedText(v *judge.Verdict, obs *observe.Run) []string {
	out := []string{}
	for _, n := range v.NotChecked {
		switch n.Code {
		case "no_probe":
			out = append(out, "state, because there is no probe")
		case "uncovered_objects":
			out = append(out, andList(n.ObjectTypes)+" state, which no probe reads")
		case "no_reset":
			out = append(out, "state between scenarios, because no reset ran")
		case "side_effects":
			out = append(out, "side effects outside your database, such as emails")
		}
	}
	return out
}

func couldntStart(in Input) string {
	switch in.Verdict.Reason {
	case "baseline_rejected":
		return "Your handler rejected a correctly signed delivery (HTTP " + itoa(in.Verdict.HTTPStatus) + "). Check the secret and the target path. Nothing else was sent."
	case "target_unreachable":
		return "Nothing answered at " + in.Meta.Target.String() + ". Start your handler and run again."
	case "needs_secret":
		return in.Pack.Name + " signs a timestamp, so captured signatures go stale. Set " + strings.Join(in.Pack.Signing.SecretEnv, " or ") + " to re-sign deliveries."
	case "probe_unstable":
		return "Your probe isn't stable: its value changed with no delivery. Remove time-based values such as updated_at or now()."
	case "reset_failed":
		return "The reset command failed before the first delivery."
	}
	return ""
}

// ---- formatting helpers ----

// line writes one output line. Every line is cleaned as it is written, so
// no capture- or pack-derived string reaches the terminal raw, whichever
// field it came through (AC-2).
func line(b *strings.Builder, s string) { b.WriteString(clean(s) + "\n") }

// num formats an integer with a plain space as the thousands separator.
func num(n int64) string {
	neg := n < 0
	if neg {
		n = -n
	}
	s := itoa64(n)
	var out []byte
	for i := range s {
		if i > 0 && (len(s)-i)%3 == 0 {
			out = append(out, ' ')
		}
		out = append(out, s[i])
	}
	if neg {
		return "-" + string(out)
	}
	return string(out)
}

func seconds(ms int64) string {
	tenths := (ms + 50) / 100
	return itoa64(tenths/10) + "." + itoa64(tenths%10) + " s"
}

func itoa(n int) string { return itoa64(int64(n)) }

func itoa64(n int64) string {
	if n == 0 {
		return "0"
	}
	neg := n < 0
	if neg {
		n = -n
	}
	var b [24]byte
	i := len(b)
	for n > 0 {
		i--
		b[i] = byte('0' + n%10)
		n /= 10
	}
	if neg {
		i--
		b[i] = '-'
	}
	return string(b[i:])
}

func width(s string) int { return utf8.RuneCountInString(s) }

func pad(s string, w int) string {
	if d := w - width(s); d > 0 {
		return s + strings.Repeat(" ", d)
	}
	return s
}

func padLeft(s string, w int) string {
	if d := w - width(s); d > 0 {
		return strings.Repeat(" ", d) + s
	}
	return s
}

// short sanitises and caps a probe value for a table cell (local values are
// shown sanitised, at most 200 characters; cells are shorter).
func short(s string) string {
	s = clean(s)
	if width(s) > 24 {
		r := []rune(s)
		return string(r[:23]) + "…"
	}
	return s
}

// clean is the shared terminal cleaner (scrub.Terminal, AC-2).
func clean(s string) string { return scrub.Terminal(s) }

// wrap breaks prose on spaces at width w, never inside a token.
func wrap(s string, w int) []string {
	words := strings.Fields(s)
	var out []string
	cur := ""
	for _, word := range words {
		switch {
		case cur == "":
			cur = word
		case width(cur)+1+width(word) <= w:
			cur += " " + word
		default:
			out = append(out, cur)
			cur = word
		}
	}
	if cur != "" {
		out = append(out, cur)
	}
	return out
}
