package main

import (
	"errors"
	"flag"
	"fmt"
	"io"
	"os"
	"time"
)

const usageRunnerHealth = `usage: webhooktrap runner-health [--file FILE] [--max-age DURATION]

Exits 0 when serve-run's health file (RUNNER_HEALTH_FILE) was touched less
than --max-age ago (default 90s), that is when the app accepted the runner's
hello recently; 1 otherwise. Meant for a container health check.
`

func runRunnerHealth(args []string, e env) int {
	fs := flag.NewFlagSet("runner-health", flag.ContinueOnError)
	fs.SetOutput(io.Discard)
	getenv := e.getenv
	if getenv == nil {
		getenv = func(string) string { return "" }
	}
	file := fs.String("file", getenv("RUNNER_HEALTH_FILE"), "")
	maxAge := fs.Duration("max-age", 90*time.Second, "")
	if err := fs.Parse(args); err != nil || fs.NArg() > 0 || *maxAge <= 0 {
		if errors.Is(err, flag.ErrHelp) {
			fmt.Fprint(e.stdout, usageRunnerHealth)
			return 0
		}
		fmt.Fprint(e.stderr, usageRunnerHealth)
		return 2
	}
	if *file == "" {
		fmt.Fprintln(e.stderr, "runner-health: no health file: set RUNNER_HEALTH_FILE or --file")
		return 2
	}
	st, err := os.Stat(*file)
	if err != nil {
		fmt.Fprintln(e.stderr, "runner-health: the runner has not reached the app yet")
		return 1
	}
	now := time.Now()
	if e.now != nil {
		now = e.now()
	}
	if age := now.Sub(st.ModTime()); age > *maxAge {
		fmt.Fprintf(e.stderr, "runner-health: last hello accepted %s ago (max %s)\n", age.Round(time.Second), *maxAge)
		return 1
	}
	fmt.Fprintln(e.stdout, "ok")
	return 0
}
