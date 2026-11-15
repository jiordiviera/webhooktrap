// Command sync copies the shared packs/ tree into the engine for embedding:
// manifest.json, _schema/*.json and <provider>/<version>.json. The
// destination is replaced, so a removed pack disappears from the copy too.
package main

import (
	"fmt"
	"io/fs"
	"os"
	"path/filepath"
	"strings"
)

func main() {
	if len(os.Args) != 3 {
		fmt.Fprintln(os.Stderr, "usage: sync <source packs dir> <destination>")
		os.Exit(2)
	}
	src, dst := os.Args[1], os.Args[2]
	if err := os.RemoveAll(dst); err != nil {
		fail(err)
	}
	err := filepath.WalkDir(src, func(path string, d fs.DirEntry, err error) error {
		if err != nil {
			return err
		}
		rel, _ := filepath.Rel(src, path)
		if d.IsDir() || !strings.HasSuffix(rel, ".json") || !d.Type().IsRegular() {
			return nil
		}
		raw, err := os.ReadFile(path)
		if err != nil {
			return err
		}
		out := filepath.Join(dst, rel)
		if err := os.MkdirAll(filepath.Dir(out), 0o755); err != nil {
			return err
		}
		return os.WriteFile(out, raw, 0o644)
	})
	if err != nil {
		fail(err)
	}
}

func fail(err error) {
	fmt.Fprintln(os.Stderr, "sync:", err)
	os.Exit(1)
}
