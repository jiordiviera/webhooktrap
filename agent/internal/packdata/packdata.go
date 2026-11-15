// Package packdata embeds the shared provider packs (repository root
// `packs/`, shared with the server). Go embed can't read outside agent/, so
// `go generate ./internal/packdata` copies them here; TestInSync fails when
// the copy and the source differ.
package packdata

import (
	"embed"
	"io/fs"

	"github.com/jiordiviera/webhooktrap/agent/pack"
)

//go:generate go run ./sync ../../../packs packs

//go:embed all:packs
var files embed.FS

// FS returns the embedded packs/ tree.
func FS() fs.FS {
	sub, err := fs.Sub(files, "packs")
	if err != nil {
		panic(err)
	}
	return sub
}

// Source returns the embedded packs as a pack.Source.
func Source() pack.Source { return pack.FSSource{FS: FS()} }
