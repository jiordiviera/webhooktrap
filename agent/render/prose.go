package render

import (
	"strings"

	"github.com/jiordiviera/webhooktrap/agent/judge"
	"github.com/jiordiviera/webhooktrap/agent/pack"
	"github.com/jiordiviera/webhooktrap/agent/plan"
)

// Copy is a finding's rendered prose (FND-7, FND-7a): every sentence comes
// from a pack template filled only from type, evidence, pack data and params.
type Copy struct {
	Title       string
	What        string
	Why         string
	Fix         string
	LikelyCause string
	Citation    pack.Citation
	Snippet     string
	SnippetFor  string // framework or file the snippet is for
}

// Prose renders the text of a finding.
func Prose(in Input, f *judge.Finding) Copy {
	tpl := in.Pack.Text.Findings[f.Type]
	vars := vars(in, f)
	t := Copy{
		Title:    fill(tpl.Title, vars),
		What:     fill(tpl.What, vars),
		Why:      fill(tpl.Why, vars),
		Fix:      fill(tpl.Fix, vars),
		Citation: in.Pack.Citation(f.Type),
	}
	if t.Title == "" {
		t.Title = f.Type
	}
	if f.LikelyCauseRule != "" {
		t.LikelyCause = fill(in.Pack.Text.LikelyCause[f.LikelyCauseRule], vars)
	}
	if sn := in.Pack.Text.Snippets[f.Type]; sn != nil {
		if f.Framework != "" && sn[f.Framework] != "" {
			t.Snippet, t.SnippetFor = sn[f.Framework], frameworkName(f.Framework)
		} else if sn["webhooktrap.yml"] != "" {
			t.Snippet, t.SnippetFor = sn["webhooktrap.yml"], "webhooktrap.yml"
		}
	}
	return t
}

func frameworkName(f string) string {
	switch f {
	case "express":
		return "Express"
	case "nextjs":
		return "Next.js"
	case "laravel":
		return "Laravel"
	}
	return f
}

// vars builds the placeholder values for one finding.
func vars(in Input, f *judge.Finding) map[string]string {
	p := f.Params
	v := map[string]string{
		"event_type":   p.EventType,
		"provider":     in.Pack.Name,
		"probe":        p.Probe,
		"object_type":  p.ObjectType,
		"budget_label": p.BudgetLabel,
		"tolerance_s":  itoa(in.Pack.Signing.ToleranceS),
	}
	if p.D > 0 {
		v["d"] = "D" + itoa(p.D)
	}
	if p.DRef > 0 {
		v["d_ref"] = "D" + itoa(p.DRef)
	}
	if p.Status > 0 {
		v["status"] = itoa(p.Status)
	}
	v["status_text"] = statusText(p.Status, p.Transport)
	if p.LatencyMS != nil {
		v["latency_ms"] = num(*p.LatencyMS)
	}
	if p.MaxOtherMS != nil {
		v["max_other_ms"] = num(*p.MaxOtherMS)
	}
	if p.TimestampOffsetS != 0 {
		v["timestamp_age_s"] = itoa(-p.TimestampOffsetS)
	}
	if p.Tamper != nil {
		v["tamper.path"] = lastSegment(p.Tamper.Path)
		v["tamper.from"] = p.Tamper.From
		v["tamper.to"] = p.Tamper.To
	}
	for k, ptr := range map[string]*string{"before": p.Before, "after": p.After, "expected": p.Expected} {
		if ptr != nil {
			v[k] = *ptr
		}
	}
	if f.Type == judge.OverBudget {
		v["budget_s"] = budgetSeconds(in.Plan.Budget.MS)
		v["budget_source"] = in.Plan.Budget.SourceLabel
		if v["budget_source"] == "" {
			v["budget_source"] = in.Pack.Budget.SourceLabel
		}
		v["slow_count"] = itoa(p.SlowCount)
		v["valid_count"] = itoa(p.ValidCount)
		var parts []string
		for _, m := range f.Measured {
			if m.LatencyMS != nil {
				parts = append(parts, "D"+itoa(m.D)+" ("+num(*m.LatencyMS)+" ms)")
			}
		}
		v["latency_list"] = andList(parts)
	}
	var off []string
	for _, n := range f.Offending() {
		off = append(off, "D"+itoa(n))
	}
	v["offending"] = andList(off)
	var scs []string
	for _, s := range f.Scenarios {
		scs = append(scs, plan.Scenario(s).Label())
	}
	v["scenario_list"] = andList(scs)
	if len(f.Measured) > 0 && f.Measured[0].Probe != "" {
		var parts []string
		for _, m := range f.Measured {
			if m.Before != nil && m.After != nil {
				parts = append(parts, *m.Before+" → "+*m.After+" on D"+itoa(m.D))
			}
		}
		s := f.Measured[0].Probe + " went " + andList(parts)
		if e := f.Measured[0].Expected; e != nil {
			s += ", expected " + *e
		}
		v["state_list"] = s
	}
	return v
}

func statusText(status int, transport string) string {
	switch {
	case status > 0:
		return "HTTP " + itoa(status)
	case transport == "timeout":
		return "no response within 30 s"
	case transport == "reset":
		return "a closed connection before any response"
	}
	return ""
}

func lastSegment(p string) string {
	if i := strings.LastIndexByte(p, '.'); i >= 0 {
		return p[i+1:]
	}
	return p
}

func budgetSeconds(ms int) string {
	if ms%1000 == 0 {
		return itoa(ms / 1000)
	}
	return itoa(ms/1000) + "." + itoa(ms%1000/100)
}

func andList(xs []string) string {
	switch len(xs) {
	case 0:
		return ""
	case 1:
		return xs[0]
	}
	return strings.Join(xs[:len(xs)-1], ", ") + " and " + xs[len(xs)-1]
}

// fill substitutes {name} and keeps [[name|text]] only when name has a value.
func fill(tpl string, v map[string]string) string {
	var b strings.Builder
	for i := 0; i < len(tpl); {
		switch {
		case strings.HasPrefix(tpl[i:], "[["):
			end := strings.Index(tpl[i:], "]]")
			if end < 0 {
				b.WriteString(tpl[i:])
				return b.String()
			}
			body := tpl[i+2 : i+end]
			name, text, ok := strings.Cut(body, "|")
			if ok && v[name] != "" {
				b.WriteString(fill(text, v))
			}
			i += end + 2
		case tpl[i] == '{':
			end := strings.IndexByte(tpl[i:], '}')
			if end < 0 {
				b.WriteString(tpl[i:])
				return b.String()
			}
			b.WriteString(v[tpl[i+1:i+end]])
			i += end + 1
		default:
			b.WriteByte(tpl[i])
			i++
		}
	}
	return b.String()
}
