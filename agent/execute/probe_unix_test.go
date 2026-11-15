//go:build !windows

package execute_test

import (
	"context"
	"os"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jiordiviera/webhooktrap/agent/execute"
	"github.com/jiordiviera/webhooktrap/agent/plan"
)

// probeHostile is the AC-1 corpus as it would reach the shell if a value
// ever bypassed plan.PlainID: quotes, $(), backticks, ;, newlines, %VAR%.
var probeHostile = []string{
	`'; touch pwned; '`,
	`$(touch pwned)`,
	"`touch pwned`",
	`;touch pwned`,
	"x\ntouch pwned",
	`x" ; touch pwned ; echo "`,
	`%PATH%`,
	`$HOME`,
	`x&touch pwned`,
	`* ?`,
}

// expand turns {{order.id}} in a command into the variable reference for its
// quoting context, as webhooktrap test and agent do.
func expand(cmd string) string {
	return plan.ReplaceTemplates(cmd, func(t string, at int) string {
		return execute.EnvRef(cmd, at, execute.TemplateEnvName(t))
	})
}

// Every place a template can sit in a POSIX command line: the value arrives
// through the environment and is printed back literally, as one word; nothing
// runs. The values here bypass plan.PlainID on purpose.
func TestCommandProbeValuesAreNeverShellSyntax(t *testing.T) {
	layouts := []struct {
		name, cmd string
		want      func(v string) string
	}{
		{"unquoted", `printf '[%s]' {{order.id}}`, func(v string) string { return "[" + v + "]" }},
		{"inside double quotes", `printf '[%s]' "id = '{{order.id}}'"`, func(v string) string { return "[id = '" + v + "']" }},
		{"psql -c style", `printf '[%s]' "select status from orders where id = '{{ order.id }}'"`,
			func(v string) string { return "[select status from orders where id = '" + v + "']" }},
		{"inside single quotes", `printf '[%s]' 'id={{order.id}}'`, func(v string) string { return "[id=" + v + "]" }},
		{"after an escaped quote", `printf '[%s]' "a\"{{order.id}}"`, func(v string) string { return `[a"` + v + "]" }},
		{"nested sh -c", `sh -c 'printf "[%s]" "$WT_ORDER_ID"'`, func(v string) string { return "[" + v + "]" }},
		{"several in one command", `printf '[%s|%s]' {{order.id}} "{{order.id}}"`, func(v string) string { return "[" + v + "|" + v + "]" }},
	}
	for _, l := range layouts {
		cmd := expand(l.cmd)
		if strings.Contains(cmd, "{{") {
			t.Fatalf("%s: template left in %q", l.name, cmd)
		}
		for _, v := range probeHostile {
			dir := t.TempDir()
			t.Chdir(dir)
			p := &execute.CommandProbe{
				Env:     []string{"PATH=" + os.Getenv("PATH"), "WT_ORDER_ID=inherited"},
				Entries: []execute.ProbeEntry{{Name: "x", Kind: "cmd", Text: cmd, Env: []string{"WT_ORDER_ID=" + v}}},
			}
			got, err := p.Read(context.Background())
			if _, serr := os.Stat(filepath.Join(dir, "pwned")); serr == nil {
				t.Fatalf("%s: value %q ran a command", l.name, v)
			}
			if err != nil {
				t.Errorf("%s: value %q: %v", l.name, v, err)
				continue
			}
			if got[0] != l.want(v) {
				t.Errorf("%s (%s): value %q printed %q, want %q", l.name, cmd, v, got[0], l.want(v))
			}
		}
	}
}

func TestEnvRefContexts(t *testing.T) {
	for cmd, want := range map[string]string{
		`a {{x.id}}`:     `a "${WT_X_ID}"`,
		`a "{{x.id}}"`:   `a "${WT_X_ID}"`,
		`a '{{x.id}}'`:   `a ''"${WT_X_ID}"''`,
		`a "'{{x.id}}'"`: `a "'${WT_X_ID}'"`,
		`a '"'{{x.id}}`:  `a '"'"${WT_X_ID}"`,
		`a \'{{x.id}}`:   `a \'"${WT_X_ID}"`,
		`a "\"{{x.id}}"`: `a "\"${WT_X_ID}"`,
	} {
		if got := expand(cmd); got != want {
			t.Errorf("%s → %s, want %s", cmd, got, want)
		}
	}
}
