//go:build linux

package main

import "syscall"

// disableCoreDumps keeps a runner's job data (and on self-host B′ its
// instance secrets) out of core files (ADR-010 §12.4 rule 5).
func disableCoreDumps() {
	_ = syscall.Setrlimit(syscall.RLIMIT_CORE, &syscall.Rlimit{})
	const prSetDumpable = 4
	_, _, _ = syscall.RawSyscall(syscall.SYS_PRCTL, prSetDumpable, 0, 0)
}
