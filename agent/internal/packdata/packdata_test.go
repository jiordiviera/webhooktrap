package packdata

import (
	"bytes"
	"io/fs"
	"os"
	"path/filepath"
	"strings"
	"testing"
)

// TestInSync is the CI check that the embedded copy equals the shared packs/.
func TestInSync(t *testing.T) {
	src := filepath.Join("..", "..", "..", "packs")
	if _, err := os.Stat(src); err != nil {
		t.Skip("shared packs/ not present (agent-only checkout)")
	}
	want := map[string][]byte{}
	_ = filepath.WalkDir(src, func(p string, d fs.DirEntry, err error) error {
		if err == nil && !d.IsDir() && strings.HasSuffix(p, ".json") {
			rel, _ := filepath.Rel(src, p)
			want[filepath.ToSlash(rel)], _ = os.ReadFile(p)
		}
		return nil
	})
	got := map[string][]byte{}
	_ = fs.WalkDir(FS(), ".", func(p string, d fs.DirEntry, err error) error {
		if err == nil && !d.IsDir() {
			got[p], _ = fs.ReadFile(FS(), p)
		}
		return nil
	})
	for k, w := range want {
		if !bytes.Equal(got[k], w) {
			t.Errorf("%s differs from packs/%s: run `go generate ./internal/packdata`", k, k)
		}
	}
	for k := range got {
		if _, ok := want[k]; !ok {
			t.Errorf("%s is embedded but no longer in packs/: run `go generate ./internal/packdata`", k)
		}
	}
}

func TestEveryPackLoads(t *testing.T) {
	src := Source()
	ids, err := src.Providers()
	if err != nil || len(ids) < 4 {
		t.Fatalf("providers %v, %v", ids, err)
	}
	for _, id := range ids {
		if _, err := src.Load(id, ""); err != nil {
			t.Errorf("%s: %v", id, err)
		}
	}
}
