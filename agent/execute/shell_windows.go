//go:build windows

package execute

import (
	"context"
	"os/exec"
	"syscall"
)

// EnvRef is what a {{T.id}} template becomes in a cmd: probe on Windows: a
// delayed-expansion reference !NAME!, which cmd.exe expands after it has
// parsed the line, so &, |, ^, % and quotes in a value are never syntax.
// %NAME% would be expanded before parsing and is never used (AC-1).
func EnvRef(_ string, _ int, name string) string { return "!" + name + "!" }

// shellCommand runs command with cmd.exe, delayed expansion on (/V:ON), no
// AutoRun (/D). The command line is passed as is (/S strips only the outer
// quotes), not re-quoted with the argv rules cmd.exe doesn't follow.
func shellCommand(ctx context.Context, command string) *exec.Cmd {
	cmd := exec.CommandContext(ctx, "cmd.exe")
	cmd.SysProcAttr = &syscall.SysProcAttr{CmdLine: `cmd.exe /D /V:ON /S /C "` + command + `"`}
	return cmd
}
