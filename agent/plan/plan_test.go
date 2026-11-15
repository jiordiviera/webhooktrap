package plan_test

import (
	"encoding/json"
	"errors"
	"os"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jiordiviera/webhooktrap/agent/internal/packdata"
	"github.com/jiordiviera/webhooktrap/agent/pack"
	"github.com/jiordiviera/webhooktrap/agent/plan"
)

func stripe(t *testing.T) *pack.Pack {
	t.Helper()
	p, err := packdata.Source().Load("stripe", "")
	if err != nil {
		t.Fatal(err)
	}
	return p
}

func captures(t *testing.T) []plan.Capture {
	t.Helper()
	var out []plan.Capture
	for i, name := range []string{"invoice.paid", "payment_intent.succeeded", "customer.subscription.updated"} {
		raw, err := os.ReadFile(filepath.Join("..", "testdata", "fixtures", "stripe", name+".json"))
		if err != nil {
			t.Fatal(err)
		}
		var f struct{ Body string }
		if err := json.Unmarshal(raw, &f); err != nil {
			t.Fatal(err)
		}
		out = append(out, plan.Capture{Ref: name, Provider: "stripe", CaptureOrder: i + 1, Body: []byte(f.Body)})
	}
	return out
}

func build(t *testing.T, caps []plan.Capture, sc []plan.Scenario, secret bool) *plan.Plan {
	t.Helper()
	p, err := plan.Build(plan.Input{Captures: caps, Scenarios: sc, Pack: stripe(t), Seed: 7, HasSecret: secret, Budget: plan.Budget{MS: 10000}})
	if err != nil {
		t.Fatal(err)
	}
	return p
}

func TestRun3Plan(t *testing.T) {
	all := []plan.Scenario{plan.Twice, plan.Shuffled, plan.Late, plan.Retry, plan.Tampered, plan.Burst, plan.SlowBudget}
	p := build(t, captures(t), all, true)
	if len(p.Deliveries) != 16 {
		t.Fatalf("got %d deliveries, want 16 (PLAN-1)", len(p.Deliveries))
	}
	// SJ §16 change 2: Shuffled D6 = #3, D7 = #2, D8 = #1; Burst D13..D15 in capture order.
	ev := func(n int) string { return p.Events[p.Deliveries[n-1].Event].EventType }
	if ev(6) != "customer.subscription.updated" || ev(8) != "invoice.paid" || ev(15) != "customer.subscription.updated" {
		t.Fatalf("order wrong: D6 %s D8 %s D15 %s", ev(6), ev(8), ev(15))
	}
	if d := p.Deliveries[11]; d.Mode != plan.ModeTampered || d.Tamper.Path != "data.object.amount_paid" || d.Tamper.From != "2400" || d.Tamper.To != "0" {
		t.Fatalf("tamper %+v", d.Tamper)
	}
	if p.Deliveries[8].Signing.TimestampOffsetS != -360 {
		t.Fatalf("late offset %d", p.Deliveries[8].Signing.TimestampOffsetS)
	}
	with := build(t, captures(t), append(all, plan.Unsigned), true)
	if len(with.Deliveries) != 17 || with.Deliveries[12].Mode != plan.ModeUnsigned {
		t.Fatalf("with Unsigned: %d deliveries", len(with.Deliveries))
	}
}

func TestDeterministicHash(t *testing.T) {
	a := build(t, captures(t), plan.Order, true)
	caps := captures(t)
	caps[0], caps[2] = caps[2], caps[0] // input order doesn't matter: capture order does
	b := build(t, caps, plan.Order, true)
	if a.Hash != b.Hash || !strings.HasPrefix(a.Hash, "sha256:") {
		t.Fatalf("hash %s vs %s", a.Hash, b.Hash)
	}
}

func TestNeedsSecretAndNA(t *testing.T) {
	p := build(t, captures(t)[:1], plan.Order, false)
	if !p.NeedsSecret {
		t.Fatal("Stripe without a secret must need one (SIGN-2)")
	}
	for _, s := range p.Scenarios {
		if s.Name == plan.Shuffled && s.NA != "needs_related_events" {
			t.Fatalf("shuffled with one event: %+v", s)
		}
		if s.Name == plan.Burst && len(s.Deliveries) != 2 {
			t.Fatalf("burst with one event sends two copies (BURST-1)")
		}
	}
}

func TestDefinitionErrors(t *testing.T) {
	caps := captures(t)
	caps[1].Provider = "github"
	_, err := plan.Build(plan.Input{Captures: caps, Pack: stripe(t)})
	var pe *plan.Error
	if !errors.As(err, &pe) || pe.Code != "def_1" {
		t.Fatalf("two providers: %v", err)
	}
	six := append(captures(t), captures(t)...)
	if _, err := plan.Build(plan.Input{Captures: six, Pack: stripe(t)}); !errors.As(err, &pe) || pe.Code != "def_1" {
		t.Fatalf("six events: %v", err)
	}
}

func TestTamperRules(t *testing.T) {
	p := stripe(t)
	body := []byte("{\n  \"id\": \"evt_1\",\n  \"type\": \"customer.updated\",\n  \"data\": {\"object\": {\"id\": \"cus_1\", \"quantity\": 0}}\n}")
	out, tm, err := plan.TamperBody(p, "customer.updated", body)
	if err != nil || tm.Path != "data.object.quantity" || tm.To != "1" || !json.Valid(out) {
		t.Fatalf("rule 2: %+v %s %v", tm, out, err)
	}
	none := []byte("{\n  \"id\": \"evt_1\",\n  \"type\": \"x\"\n}")
	out, tm, err = plan.TamperBody(p, "x", none)
	if err != nil || tm.Path != "_webhooktrap_tampered" || !json.Valid(out) || !strings.HasSuffix(string(out), "\"x\",\"_webhooktrap_tampered\":true\n}") {
		t.Fatalf("rule 3: %+v %q %v", tm, out, err)
	}
	if _, _, err := plan.TamperBody(p, "x", []byte("a=b")); err == nil {
		t.Fatal("non-JSON must fail (TAMP-3)")
	}
}

func TestResolveTemplate(t *testing.T) {
	got, err := plan.ResolveTemplate("count where id = '{{invoice.id}}' and s = {{subscription.id}}", captures(t), stripe(t))
	if err != nil || got["invoice"] != "in_1QKImRcZJ8HR9BZYostEV4ui" || got["subscription"] != "sub_1QH3UF1HXb5oPQT3wfdt4drI" {
		t.Fatalf("%v %v", got, err)
	}
	if _, err := plan.ResolveTemplate("{{refund.id}}", captures(t), stripe(t)); err == nil {
		t.Fatal("unresolvable template must fail (PRB-4)")
	}
}

// hostileValues is the AC-1 corpus: shell, cmd.exe and SQL metacharacters in
// every position a probe command could place them.
var hostileValues = []string{
	`in_1'; touch pwned; '`,
	`$(touch pwned)`,
	"`touch pwned`",
	`in_1;touch pwned`,
	"in_1\ntouch pwned",
	"in_1\rtouch pwned",
	`%PATH%`,
	`in_1&calc`,
	`in_1|calc`,
	`in_1^&calc`,
	`x" ; touch pwned ; echo "`,
	`in_1 --output=/tmp/x`,
	`in_1\`,
	`!PATH!`,
	"in_1\x00",
	"in_​1",
	strings.Repeat("a", plan.MaxTemplateValue+1),
	"",
}

func TestPlainID(t *testing.T) {
	for _, ok := range []string{"in_1QKImRcZJ8HR9BZYostEV4ui", "12345", "a-b_C", strings.Repeat("a", plan.MaxTemplateValue)} {
		if !plan.PlainID(ok) {
			t.Errorf("PlainID(%q) = false", ok)
		}
	}
	for _, bad := range hostileValues {
		if plan.PlainID(bad) {
			t.Errorf("PlainID(%q) = true", bad)
		}
	}
}

func jsonString(t *testing.T, v string) string {
	t.Helper()
	b, err := json.Marshal(v)
	if err != nil {
		t.Fatal(err)
	}
	return string(b)
}

// Lookup 1: the pack object's id field. A hostile id is an error, never
// substituted, even when the pack has no id_pattern.
func TestResolveTemplateRefusesHostileObjectID(t *testing.T) {
	noPattern := *stripe(t)
	noPattern.IDPattern = ""
	for _, p := range []*pack.Pack{stripe(t), &noPattern} {
		for _, v := range hostileValues {
			body := `{"id":"evt_1","type":"invoice.paid","data":{"object":{"object":"invoice","id":` + jsonString(t, v) + `}}}`
			caps := []plan.Capture{{Ref: "c1", Provider: "stripe", CaptureOrder: 1, Body: []byte(body)}}
			got, err := plan.ResolveTemplate(`psql -c "select 1 where id = '{{invoice.id}}'"`, caps, p)
			var pe *plan.Error
			if err == nil || !errors.As(err, &pe) || (pe.Code != "template_value" && pe.Code != "template") {
				t.Errorf("pattern %q, value %q: got %v, %v", p.IDPattern, v, got, err)
			}
		}
	}
}

// Lookup 2: any string field named after the template. A hostile value is
// never chosen; a later plain id still is.
func TestResolveTemplateRefusesHostileScalar(t *testing.T) {
	gh, err := packdata.Source().Load("github", "")
	if err != nil {
		t.Fatal(err)
	}
	if gh.IDPattern != "" {
		t.Fatal("this test needs a pack without id_pattern")
	}
	for _, p := range []*pack.Pack{stripe(t), gh} {
		for _, v := range hostileValues {
			body := `{"order":` + jsonString(t, v) + `}`
			caps := []plan.Capture{{Ref: "c1", Provider: p.ID, CaptureOrder: 1, Body: []byte(body)}}
			if got, err := plan.ResolveTemplate("{{order.id}}", caps, p); err == nil {
				t.Errorf("pack %s, value %q resolved to %v", p.ID, v, got)
			}
		}
		caps := []plan.Capture{
			{Ref: "c1", Provider: p.ID, CaptureOrder: 1, Body: []byte(`{"order":"$(touch pwned)"}`)},
			{Ref: "c2", Provider: p.ID, CaptureOrder: 2, Body: []byte(`{"order":"cus_42"}`)},
		}
		if got, err := plan.ResolveTemplate("{{order.id}}", caps, p); err != nil || got["order"] != "cus_42" {
			t.Errorf("pack %s: plain id after a hostile one: %v %v", p.ID, got, err)
		}
	}
}

// The pack's id_pattern applies to both lookups.
func TestResolveTemplateAppliesIDPattern(t *testing.T) {
	obj := []plan.Capture{{Ref: "c1", Provider: "stripe", CaptureOrder: 1,
		Body: []byte(`{"data":{"object":{"object":"invoice","id":"not-a-stripe-id"}}}`)}}
	if got, err := plan.ResolveTemplate("{{invoice.id}}", obj, stripe(t)); err == nil {
		t.Errorf("object id outside id_pattern resolved: %v", got)
	}
	scalar := []plan.Capture{{Ref: "c1", Provider: "stripe", CaptureOrder: 1, Body: []byte(`{"invoice":"not-a-stripe-id"}`)}}
	if got, err := plan.ResolveTemplate("{{invoice.id}}", scalar, stripe(t)); err == nil {
		t.Errorf("scalar outside id_pattern resolved: %v", got)
	}
}
