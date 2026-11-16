//go:build !linux

package main

// disableCoreDumps is best effort: the runner image is Linux only.
func disableCoreDumps() {}
