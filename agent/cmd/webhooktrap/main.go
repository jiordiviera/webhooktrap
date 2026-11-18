// Command webhooktrap is the Webhook Trap engine: one binary for the local
// agent, CI runs, the server runner and the network-free sandbox (ADR-010 §1).
// This build answers `version`, `test` (local fixtures, no server),
// `rejudge` (JSON-4), `pair` and `agent` (the /agent/v1 connection),
// `serve-sandbox` and its health check `sandbox-ping` (the app's
// network-free plan/verify service) and `serve-run` (the server runner).
package main

import (
	"context"
	"fmt"
	"io"
	"os"
	"os/signal"
	"syscall"

	"github.com/jiordiviera/webhooktrap/agent/internal/version"
)

func main() {
	os.Exit(run(os.Args[1:], os.Stdout, os.Stderr))
}

func run(args []string, stdout, stderr io.Writer) int {
	if len(args) == 0 || args[0] == "version" || args[0] == "--version" {
		info := version.Current()
		if len(args) > 1 && args[1] == "--json" {
			raw, err := info.JSON()
			if err != nil {
				fmt.Fprintln(stderr, "webhooktrap: cannot encode version")
				return 1
			}
			fmt.Fprintln(stdout, string(raw))
			return 0
		}
		fmt.Fprintln(stdout, info.String())
		return 0
	}
	switch args[0] {
	case "test":
		ctx, stop := signal.NotifyContext(context.Background(), os.Interrupt)
		defer stop()
		return runTest(ctx, args[1:], realEnv(stdout, stderr))
	case "rejudge":
		return runRejudge(args[1:], realEnv(stdout, stderr))
	case "pair", "agent":
		ctx, stop := signal.NotifyContext(context.Background(), os.Interrupt)
		defer stop()
		if args[0] == "pair" {
			return runPair(ctx, args[1:], realEnv(stdout, stderr))
		}
		return runAgent(ctx, args[1:], realEnv(stdout, stderr))
	case "serve-sandbox", "serve-run":
		// Services stop on SIGTERM (Compose) as well as Ctrl-C.
		ctx, stop := signal.NotifyContext(context.Background(), os.Interrupt, syscall.SIGTERM)
		defer stop()
		if args[0] == "serve-sandbox" {
			return runServeSandbox(ctx, args[1:], realEnv(stdout, stderr))
		}
		return runServeRun(ctx, args[1:], realEnv(stdout, stderr))
	case "runner-health":
		return runRunnerHealth(args[1:], realEnv(stdout, stderr))
	case "sandbox-ping":
		return runSandboxPing(context.Background(), args[1:], realEnv(stdout, stderr))
	case "sandbox-call": // serve-sandbox's per-call child, not a user command
		return runSandboxCall(realEnv(stdout, stderr))
	}
	fmt.Fprintf(stderr, "webhooktrap: unknown command %q (available: version, test, rejudge, pair, agent, serve-sandbox, sandbox-ping, serve-run, runner-health)\n", clean(args[0]))
	return 2
}
