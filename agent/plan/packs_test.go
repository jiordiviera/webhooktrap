package plan_test

import (
	"os"
	"path/filepath"
	"sort"
	"testing"

	"github.com/jiordiviera/webhooktrap/agent/internal/fixture"
	"github.com/jiordiviera/webhooktrap/agent/internal/packdata"
	"github.com/jiordiviera/webhooktrap/agent/plan"
)

// fixturesFor loads testdata/fixtures/<provider>/*.json through the fixture
// loader the CLI uses, in capture order.
func fixturesFor(t *testing.T, provider string) []plan.Capture {
	t.Helper()
	dir, _ := filepath.Abs(filepath.Join("..", "testdata", "fixtures", provider)) // the loader refuses ".."
	entries, err := os.ReadDir(dir)
	if err != nil {
		return nil
	}
	var out []plan.Capture
	for _, e := range entries {
		c, err := fixture.Load(filepath.Join(dir, e.Name()))
		if err != nil {
			t.Fatalf("%s/%s: %v", provider, e.Name(), err)
		}
		if c.Provider != provider {
			t.Fatalf("%s/%s: provider %q", provider, e.Name(), c.Provider)
		}
		out = append(out, c)
	}
	sort.Slice(out, func(i, j int) bool { return out[i].CaptureOrder < out[j].CaptureOrder })
	return out
}

// Every pack with fixtures plans the same way twice, and every scenario
// either sends something or says why it is n/a, in words.
func TestEveryPackPlansDeterministically(t *testing.T) {
	ids, err := packdata.Source().Providers()
	if err != nil {
		t.Fatal(err)
	}
	for _, id := range ids {
		caps := fixturesFor(t, id)
		if len(caps) == 0 {
			continue
		}
		p, err := packdata.Source().Load(id, "")
		if err != nil {
			t.Fatal(err)
		}
		in := plan.Input{Captures: caps, Scenarios: plan.Order, Pack: p, Seed: 7, HasSecret: true, Budget: plan.Budget{MS: p.Budget.MS}}
		a, err := plan.Build(in)
		if err != nil {
			t.Fatalf("%s: %v", id, err)
		}
		b, _ := plan.Build(in)
		if a.Hash != b.Hash {
			t.Errorf("%s: plan not deterministic", id)
		}
		for _, s := range a.Scenarios {
			if s.NA == "" && len(s.Deliveries) == 0 {
				t.Errorf("%s/%s: no deliveries and no n/a reason", id, s.Name)
			}
			if s.NA != "" && s.NAText == "" {
				t.Errorf("%s/%s: n/a %q without words", id, s.Name, s.NA)
			}
		}
	}
}

// The packs added with their fixtures (issue #19) must keep them.
func TestNewPacksShipFixtures(t *testing.T) {
	for _, id := range []string{"slack", "paddle"} {
		if len(fixturesFor(t, id)) < 2 {
			t.Errorf("%s: needs at least two fixtures (related events for Shuffled)", id)
		}
	}
}
