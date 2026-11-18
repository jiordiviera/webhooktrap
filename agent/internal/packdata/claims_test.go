package packdata

import (
	"encoding/json"
	"io/fs"
	"os"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jiordiviera/webhooktrap/agent/internal/schematest"
)

// TestPacksMatchSchema validates the manifest and every pack file against
// packs/_schema (CI: schema validation for shipped and contributed packs).
func TestPacksMatchSchema(t *testing.T) {
	dir := filepath.Join("..", "..", "..", "packs", "_schema")
	if _, err := os.Stat(dir); err != nil {
		t.Skip("shared packs/ not present (agent-only checkout)")
	}
	v := schematest.New(dir)
	_ = fs.WalkDir(FS(), ".", func(p string, d fs.DirEntry, err error) error {
		if err != nil || d.IsDir() || strings.HasPrefix(p, "_schema/") {
			return nil
		}
		schema := "pack.schema.json"
		if p == "manifest.json" {
			schema = "manifest.schema.json"
		}
		raw, _ := fs.ReadFile(FS(), p)
		errs, verr := v.Validate(schema, raw)
		if verr != nil {
			t.Fatalf("%s: %v", p, verr)
		}
		for _, e := range errs {
			t.Errorf("%s: %s", p, e)
		}
		return nil
	})
}

type claims struct {
	ID      string `json:"id"`
	Version string `json:"version"`
	Budget  struct {
		Source     string `json:"source"`
		Documented bool   `json:"documented"`
	} `json:"budget"`
	Signing struct {
		Scheme string `json:"scheme"`
	} `json:"signing"`
	Duplicate rule                                     `json:"duplicate"`
	Retry     rule                                     `json:"retry"`
	Citations map[string][]struct{ Label, URL string } `json:"citations"`
}

type rule struct {
	Supported bool   `json:"supported"`
	Verified  bool   `json:"verified"`
	Citation  string `json:"citation"`
}

// TestPackClaimsAreCited: no pack claims a provider guarantee without an
// official source (issue #19). A verified duplicate or retry rule, and a
// "doesn't retry" claim, need an https citation; a documented budget names
// the provider's docs; a Webhook Trap default says "default".
func TestPackClaimsAreCited(t *testing.T) {
	ids, err := Source().Providers()
	if err != nil {
		t.Fatal(err)
	}
	for _, id := range ids {
		var c claims
		raw, err := fs.ReadFile(FS(), id+"/"+mustVersion(t, id)+".json")
		if err != nil {
			t.Fatalf("%s: %v", id, err)
		}
		if err := json.Unmarshal(raw, &c); err != nil {
			t.Fatalf("%s: %v", id, err)
		}
		for name, r := range map[string]rule{"duplicate": c.Duplicate, "retry": c.Retry} {
			if r.Verified && !strings.HasPrefix(r.Citation, "https://") {
				t.Errorf("%s %s: verified without an https citation", id, name)
			}
		}
		switch {
		case c.Budget.Documented && (c.Budget.Source == "default" || !strings.HasSuffix(c.Budget.Source, " docs")):
			t.Errorf("%s: documented budget must name \"<provider> docs\", got %q", id, c.Budget.Source)
		case !c.Budget.Documented && c.Budget.Source != "default":
			t.Errorf("%s: an undocumented budget is a Webhook Trap default, got %q", id, c.Budget.Source)
		}
		for finding, list := range c.Citations {
			for _, l := range list {
				if l.Label == "" || !strings.HasPrefix(l.URL, "https://") {
					t.Errorf("%s: citation for %s needs a label and an https url", id, finding)
				}
			}
		}
		if c.Signing.Scheme != "none" && !citeLater[id] && len(c.Citations["accepts_tampered"]) == 0 {
			t.Errorf("%s: signed pack without a signature citation (citations.accepts_tampered)", id)
		}
	}
}

// citeLater: packs shipped before issue #19 without finding citations. Their
// next version adds them; this list only shrinks.
var citeLater = map[string]bool{"github": true, "shopify": true, "standard_webhooks": true}

func mustVersion(t *testing.T, id string) string {
	t.Helper()
	raw, err := fs.ReadFile(FS(), "manifest.json")
	if err != nil {
		t.Fatal(err)
	}
	var m struct {
		Packs []struct{ ID, Version string } `json:"packs"`
	}
	if err := json.Unmarshal(raw, &m); err != nil {
		t.Fatal(err)
	}
	for _, p := range m.Packs {
		if p.ID == id {
			return p.Version
		}
	}
	t.Fatalf("%s not in manifest", id)
	return ""
}
