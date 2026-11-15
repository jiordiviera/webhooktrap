//go:build !windows

package execute

import (
	"context"
	"os/exec"
)

// EnvRef is what a {{T.id}} template at offset in a cmd: probe's command
// text becomes: a reference to the variable name, written for the quoting
// context the template sits in, so the shell expands it after parsing as one
// word, without splitting or globbing. The value itself is never part of the
// command text (AC-1).
//
//	unquoted        {{T.id}}         → "${NAME}"
//	"double"        "…{{T.id}}…"     → "…${NAME}…"
//	'single'        '…{{T.id}}…'     → '…'"${NAME}"'…'
func EnvRef(command string, offset int, name string) string {
	switch quoteAt(command, offset) {
	case '"':
		return "${" + name + "}"
	case '\'':
		return `'"${` + name + `}"'`
	}
	return `"${` + name + `}"`
}

// quoteAt is the POSIX quoting state at offset: 0 (none), a double or a single quote. It
// follows backslash escapes outside single quotes. A context it can't see
// (inside $( ) or backticks) only costs expansion, never safety: the value
// is never in the text, and plan.PlainID keeps it free of IFS and glob
// characters.
func quoteAt(s string, offset int) byte {
	var q byte
	for i := 0; i < offset && i < len(s); i++ {
		c := s[i]
		switch {
		case c == '\\' && q != '\'':
			i++
		case q == 0 && (c == '"' || c == '\''):
			q = c
		case q != 0 && c == q:
			q = 0
		}
	}
	return q
}

// shellCommand runs command with /bin/sh -c.
func shellCommand(ctx context.Context, command string) *exec.Cmd {
	return exec.CommandContext(ctx, "/bin/sh", "-c", command)
}
