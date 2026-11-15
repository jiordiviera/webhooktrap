package agentstate

import (
	"os"
	"path/filepath"
	"runtime"
	"testing"
)

func TestKeyPerOriginAndPermissions(t *testing.T) {
	s := &Store{Dir: t.TempDir()}
	a, pa, err := s.KeyFor("https://a.test")
	if err != nil {
		t.Fatal(err)
	}
	b, _, _ := s.KeyFor("https://b.test")
	again, _, _ := s.KeyFor("https://a.test")
	if a.Equal(b) || !a.Equal(again) {
		t.Fatal("want one stable key per origin")
	}
	st, _ := os.Stat(pa)
	if st.Mode().Perm() != 0o600 {
		t.Fatalf("key mode %v", st.Mode())
	}
	if runtime.GOOS == "windows" {
		return
	}
	if err := os.Chmod(pa, 0o644); err != nil {
		t.Fatal(err)
	}
	if _, err := LoadKey(pa); err == nil {
		t.Fatal("a key readable by others was loaded")
	}
	_ = os.Chmod(pa, 0o600)
	_ = os.Chmod(filepath.Dir(pa), 0o755)
	if _, err := LoadKey(pa); err == nil {
		t.Fatal("a key in a directory readable by others was loaded")
	}
}

func TestSaveLoadApprovals(t *testing.T) {
	s := &Store{Dir: t.TempDir()}
	o := "https://webhooktrap.test"
	if err := s.Save(Agent{Origin: o, AgentID: "agt_1", Name: "m"}); err != nil {
		t.Fatal(err)
	}
	if got := s.Origins(); len(got) != 1 || got[0] != o {
		t.Fatalf("origins %v", got)
	}
	_ = s.Approve(o, Approval{AgentID: "agt_1", Scheme: "http", Host: "localhost", Port: 3000, SideEffect: true})
	if !s.Approved(o, "agt_1", "http", "LOCALHOST", 3000) || s.Approved(o, "agt_2", "http", "localhost", 3000) || s.Approved(o, "agt_1", "http", "localhost", 3001) {
		t.Fatal("approval key is (agent, scheme, host, port)")
	}
	_ = s.ClearApprovals(o)
	if s.Approved(o, "agt_1", "http", "localhost", 3000) {
		t.Fatal("approvals not cleared")
	}
	_ = s.Delete(o)
	if _, err := s.Load(o); err == nil {
		t.Fatal("pairing kept after delete")
	}
}

// AC-13: origins whose readable slugs collide still get separate
// directories, keys and pairings.
func TestOriginDirsNeverCollide(t *testing.T) {
	pairs := [][2]string{
		{"https://a.b:8443", "https://a.b_8443"},
		{"http://localhost:8000", "https://localhost:8000"},
		{"https://x.test", "https://X.test"},
		{"https://aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa.test", "https://aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaab.test"},
	}
	for _, p := range pairs {
		if Slug(p[0]) == Slug(p[1]) {
			t.Errorf("%s and %s share %s", p[0], p[1], Slug(p[0]))
		}
	}
	s := &Store{Dir: t.TempDir()}
	a, _, _ := s.KeyFor("https://a.b:8443")
	b, _, _ := s.KeyFor("https://a.b_8443")
	if a.Equal(b) {
		t.Fatal("two origins share a key")
	}
	if err := s.Save(Agent{Origin: "https://a.b:8443", AgentID: "agt_1"}); err != nil {
		t.Fatal(err)
	}
	if _, err := s.Load("https://a.b_8443"); err == nil {
		t.Fatal("pairing of one origin loaded for another")
	}
	if got := Slug("https://webhooktrap.dev"); len(got) > 48+1+32 || got[:len("https_webhooktrap.dev")] != "https_webhooktrap.dev" {
		t.Fatalf("slug %q", got)
	}
}
