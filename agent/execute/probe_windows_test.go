//go:build windows

package execute_test

import (
	"context"
	"os"
	"strings"
	"testing"

	"github.com/jiordiviera/webhooktrap/agent/execute"
)

// On Windows a template is a delayed-expansion reference, in every position,
// never %NAME% (expanded before cmd.exe parses the line).
func TestEnvRefWindows(t *testing.T) {
	for _, cmd := range []string{`a {{x.id}}`, `a "{{x.id}}"`, `a '{{x.id}}'`} {
		if got := execute.EnvRef(cmd, 2, "WT_X_ID"); got != "!WT_X_ID!" {
			t.Errorf("%s → %s", cmd, got)
		}
	}
}

func TestCommandProbeValuesAreNeverCmdSyntax(t *testing.T) {
	for _, v := range []string{`x & echo PWNED`, `x | echo PWNED`, `%PATH%`, `x ^& echo PWNED`, `x" & echo PWNED & echo "`} {
		p := &execute.CommandProbe{
			Env:     append(os.Environ(), "WT_ORDER_ID=inherited"),
			Entries: []execute.ProbeEntry{{Name: "x", Kind: "cmd", Text: `echo [!WT_ORDER_ID!]`, Env: []string{"WT_ORDER_ID=" + v}}},
		}
		got, err := p.Read(context.Background())
		if err != nil {
			t.Fatalf("%q: %v", v, err)
		}
		if strings.Count(got[0], "PWNED") > strings.Count(v, "PWNED") || !strings.Contains(got[0], "[") {
			t.Errorf("%q printed %q", v, got[0])
		}
	}
}
