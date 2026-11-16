// Package archtest holds the engine's architecture tests (ADR-010 §10).
package archtest

import (
	"go/parser"
	"go/token"
	"os"
	"path/filepath"
	"strconv"
	"strings"
	"testing"
)

const module = "github.com/jiordiviera/webhooktrap/agent/"

// D-1: plan and judge are pure stages; they import no I/O, network,
// process, clock or randomness packages (randomness comes from the seeded
// PRNG passed in, D-3).
var pureForbidden = []string{
	"net", "net/http", "os", "os/exec", "io/ioutil", "syscall", "time",
	"math/rand", "math/rand/v2", "crypto/rand", "database/sql", "log", "log/slog",
	module + "execute", module + "guard",
}

// Packages expected by ADR-010; each must exist.
var enginePackages = []string{"plan", "execute", "judge", "render", "guard", "signing", "scrub"}

func root(t *testing.T) string {
	t.Helper()
	dir, err := filepath.Abs("../..")
	if err != nil {
		t.Fatal(err)
	}
	return dir
}

func imports(t *testing.T, dir string) map[string]string {
	t.Helper()
	found := map[string]string{}
	entries, err := os.ReadDir(dir)
	if err != nil {
		t.Fatal(err)
	}
	fset := token.NewFileSet()
	for _, e := range entries {
		name := e.Name()
		if e.IsDir() || !strings.HasSuffix(name, ".go") || strings.HasSuffix(name, "_test.go") {
			continue
		}
		f, err := parser.ParseFile(fset, filepath.Join(dir, name), nil, parser.ImportsOnly)
		if err != nil {
			t.Fatal(err)
		}
		for _, imp := range f.Imports {
			path, _ := strconv.Unquote(imp.Path.Value)
			found[path] = name
		}
	}
	return found
}

func TestEnginePackagesExist(t *testing.T) {
	for _, pkg := range enginePackages {
		if _, err := os.Stat(filepath.Join(root(t), pkg, "doc.go")); err != nil {
			t.Errorf("package %s missing: %v", pkg, err)
		}
	}
}

func TestPureStagesImportNoIO(t *testing.T) {
	for _, pkg := range []string{"plan", "judge", "render"} {
		got := imports(t, filepath.Join(root(t), pkg))
		for _, bad := range pureForbidden {
			if file, ok := got[bad]; ok {
				t.Errorf("%s/%s imports %q (ADR-010 D-1)", pkg, file, bad)
			}
		}
	}
}

// ENG-2, ADR-010 §1 rule 2: serve-sandbox reaches plan, verify and ping
// only. No executor, guard, HTTP, config file or agent state is linked
// into the sandbox package.
func TestSandboxReachesNoExecutor(t *testing.T) {
	got := imports(t, filepath.Join(root(t), "internal", "sandbox"))
	for _, bad := range []string{
		"net/http", module + "execute", module + "guard", module + "observe",
		module + "internal/config", module + "internal/agentstate", module + "internal/fixture",
	} {
		if file, ok := got[bad]; ok {
			t.Errorf("internal/sandbox/%s imports %q (ENG-2)", file, bad)
		}
	}
}
