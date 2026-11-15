package main

import (
	"context"
	"crypto/ed25519"
	"crypto/sha256"
	"encoding/base64"
	"errors"
	"flag"
	"fmt"
	"io"
	"regexp"
	"strings"

	"github.com/jiordiviera/webhooktrap/agent/internal/agentapi"
	"github.com/jiordiviera/webhooktrap/agent/internal/agentstate"
	"github.com/jiordiviera/webhooktrap/agent/internal/version"
	"github.com/jiordiviera/webhooktrap/agent/scrub"
)

const defaultServer = "https://webhooktrap.dev"

const usagePair = `usage: webhooktrap pair <CODE> [flags]

Joins this machine to the workspace that showed the pairing code in its
browser. The agent creates a key for the server, sends the code, prints the
server it connected to and the workspace, and asks before it joins.

flags:
  --server URL            server origin (default $WEBHOOKTRAP_SERVER or https://webhooktrap.dev)
  --name NAME             agent name shown in the browser (default: this machine's name)
  --yes                   skip the join question for an account workspace; a guest
                          workspace always asks
  --insecure-dev-origin   allow a plain http:// server, only for a local development
                          origin (localhost, *.localhost, *.test, loopback). Refused
                          when the server reports itself as hosted, and warned about
                          on every start; https:// is otherwise required and verified
                          against the system roots

Also available as webhooktrap agent pair.
`

const joinQuestion = "Join? Only continue if you created this code in your own browser just now. [y/N] "

var (
	codeRe      = regexp.MustCompile(`^[0-9A-HJKMNP-TV-Z]{10}$`) // Crockford base32 without I L O U
	fpRe        = regexp.MustCompile(`^SHA256:[A-Za-z0-9+/]{43}$`)
	agentNameRe = regexp.MustCompile(`^[A-Za-z0-9._-]{1,48}$`) // contracts §5.11
	nameStrip   = regexp.MustCompile(`[^A-Za-z0-9._-]+`)
)

// normalizeCode accepts the code case-insensitively with an optional hyphen.
func normalizeCode(s string) (string, bool) {
	c := strings.ToUpper(strings.ReplaceAll(strings.TrimSpace(s), "-", ""))
	return c, codeRe.MatchString(c)
}

func defaultName(e env) string {
	h := "agent"
	if e.hostname != nil {
		if n, err := e.hostname(); err == nil && n != "" {
			h = n
		}
	}
	h = strings.Trim(nameStrip.ReplaceAllString(strings.SplitN(h, ".", 2)[0], "-"), "-")
	if len(h) > 48 {
		h = h[:48]
	}
	if !agentNameRe.MatchString(h) {
		h = "agent"
	}
	return h
}

// KeyFingerprint is the server's fingerprint of a public key:
// "SHA256:" + unpadded base64 of SHA-256 over the raw 32-byte key.
func KeyFingerprint(pub ed25519.PublicKey) string {
	sum := sha256.Sum256(pub)
	return "SHA256:" + base64.RawStdEncoding.EncodeToString(sum[:])
}

// keptName is the name the server kept (AC-3). An older server that doesn't
// send one keeps the requested name; a name outside §5.11 is refused.
func keptName(resp *agentapi.PairResponse, requested string) (string, bool) {
	if resp.Name == "" {
		return requested, true
	}
	return resp.Name, agentNameRe.MatchString(resp.Name)
}

// serverOrigin resolves --server, $WEBHOOKTRAP_SERVER or the default.
func serverOrigin(e env, flagVal string, insecureDev bool) (string, error) {
	raw := flagVal
	if raw == "" && e.getenv != nil {
		raw = e.getenv("WEBHOOKTRAP_SERVER")
	}
	if raw == "" {
		raw = defaultServer
	}
	return agentapi.NormalizeOrigin(raw, insecureDev)
}

func (e env) stateStore() (*agentstate.Store, error) {
	if e.store != nil {
		return e.store, nil
	}
	return agentstate.Default(e.getenv)
}

func (e env) client(origin string) *agentapi.Client {
	return agentapi.New(origin, e.http, "webhooktrap/"+version.Current().Version, e.now)
}

func runPair(ctx context.Context, args []string, e env) int {
	fs := flag.NewFlagSet("pair", flag.ContinueOnError)
	fs.SetOutput(io.Discard)
	server := fs.String("server", "", "")
	name := fs.String("name", "", "")
	yes := fs.Bool("yes", false, "")
	insecure := fs.Bool("insecure-dev-origin", false, "")
	// Accept the code before or after the flags.
	var code string
	if len(args) > 0 && !strings.HasPrefix(args[0], "-") {
		code, args = args[0], args[1:]
	}
	if err := fs.Parse(args); err != nil {
		if errors.Is(err, flag.ErrHelp) {
			fmt.Fprint(e.stdout, usagePair)
			return 0
		}
		fmt.Fprintf(e.stderr, "webhooktrap pair: %s\n\n%s", clean(err.Error()), usagePair)
		return 2
	}
	if code == "" && fs.NArg() > 0 {
		code = fs.Arg(0)
	}
	if code == "" || fs.NArg() > 1 || (fs.NArg() == 1 && fs.Arg(0) != code) {
		fmt.Fprint(e.stderr, usagePair)
		return 2
	}
	c, ok := normalizeCode(code)
	if !ok {
		fmt.Fprintln(e.stderr, "webhooktrap pair: the code is 10 characters such as 7KQ2M-XR9TD; copy it again from your browser")
		return 2
	}
	origin, err := serverOrigin(e, *server, *insecure)
	if err != nil {
		fmt.Fprintln(e.stderr, "webhooktrap pair: "+clean(err.Error()))
		return 2
	}
	plainHTTP := strings.HasPrefix(origin, "http://")
	if plainHTTP {
		fmt.Fprintf(e.stderr, insecureOriginWarning, origin)
	}
	if w := agentstate.PlatformWarning(); w != "" {
		fmt.Fprintln(e.stderr, w)
	}
	agentName := *name
	if agentName == "" {
		agentName = defaultName(e)
	}
	if !agentNameRe.MatchString(agentName) {
		fmt.Fprintln(e.stderr, "webhooktrap pair: --name must be 1 to 48 letters, digits, '.', '_' or '-'")
		return 2
	}
	st, err := e.stateStore()
	if err != nil {
		fmt.Fprintln(e.stderr, "webhooktrap pair: no config directory: "+clean(err.Error()))
		return 1
	}
	key, keyPath, err := st.KeyFor(origin)
	if err != nil {
		fmt.Fprintln(e.stderr, "webhooktrap pair: "+clean(err.Error()))
		return 1
	}
	cl := e.client(origin)
	info := version.Current()
	resp, err := cl.Pair(ctx, agentapi.PairRequest{
		Code: c, PublicKey: base64.StdEncoding.EncodeToString(key.Public().(ed25519.PublicKey)),
		Name: agentName, Version: info.Version, OS: info.OS, Arch: info.Arch,
	})
	if err != nil {
		fmt.Fprintln(e.stderr, "webhooktrap pair: "+pairError(err))
		return 1
	}
	if !agentapi.ValidAgentID(resp.AgentID) {
		fmt.Fprintln(e.stderr, "webhooktrap pair: the server's answer has no valid agent id")
		return 1
	}
	cl.AgentID, cl.Key = resp.AgentID, key
	// The origin printed is the one this agent connected to; the server's
	// echo is informational and must agree (contracts §5.1).
	if echo, err := agentapi.NormalizeOrigin(resp.Server.Origin, true); err != nil || echo != origin {
		fmt.Fprintf(e.stderr, "webhooktrap pair: refusing: the server says it is %q but this agent connected to %s\n", clean(display(resp.Server.Origin, 120)), origin)
		_ = cl.Unpair(ctx)
		return 1
	}
	if plainHTTP && resp.Server.Kind == agentapi.ServerKindHosted {
		fmt.Fprintln(e.stderr, "webhooktrap pair: refusing --insecure-dev-origin: the server reports itself as hosted, which is only reached over https://")
		_ = cl.Unpair(ctx)
		_ = st.Delete(origin)
		return 1
	}
	kept, ok := keptName(resp, agentName)
	if !ok {
		fmt.Fprintf(e.stderr, "webhooktrap pair: refusing: the server named this agent %q, which isn't a valid agent name\n", display(resp.Name, 60))
		_ = cl.Unpair(ctx)
		_ = st.Delete(origin)
		return 1
	}
	guest := resp.Guest || resp.Workspace.Guest
	label := display(resp.Workspace.Label, 120)
	kind := resp.Server.Kind
	if kind != agentapi.ServerKindHosted && kind != "selfhost" {
		kind = ""
	}
	a := agentstate.Agent{Origin: origin, AgentID: resp.AgentID, KeyPath: keyPath, Name: kept,
		WorkspaceLabel: label, Guest: guest, PairedAt: rfc3339(e.now()), ServerKind: kind}
	if err := st.Save(a); err != nil {
		fmt.Fprintln(e.stderr, "webhooktrap pair: can't save the pairing: "+clean(err.Error()))
		_ = cl.Unpair(ctx)
		return 1
	}
	fmt.Fprintf(e.stderr, "Server     %s\nWorkspace  %s\nAgent      %s\nKey        %s\n", origin, label, kept, KeyFingerprint(key.Public().(ed25519.PublicKey)))
	switch {
	case resp.Replaces != nil:
		fp := resp.Replaces.KeyFingerprint
		if !fpRe.MatchString(fp) {
			fp = "unknown key"
		}
		fmt.Fprintf(e.stderr, "Replaces   %s (%s); that agent is revoked when you join\n", display(resp.Replaces.Name, 48), fp)
	case kept != agentName:
		fmt.Fprintf(e.stderr, "The name %s is taken in this workspace; this agent is %s.\n", agentName, kept)
	}
	ok = false
	switch {
	case *yes && !guest && resp.Replaces == nil:
		ok = true
	case !e.tty:
		fmt.Fprintln(e.stderr, "webhooktrap pair: joining needs an answer in a terminal (a guest workspace or a replacement always asks); not joined")
	default:
		ok = confirm(e, false, joinQuestion)
	}
	if !ok {
		if err := cl.Unpair(ctx); err != nil {
			fmt.Fprintln(e.stderr, "webhooktrap pair: the server didn't confirm the unpairing ("+clean(err.Error())+"); it expires the agent in 10 minutes")
		}
		_ = st.Delete(origin)
		fmt.Fprintln(e.stderr, "Not joined. The key for this server was deleted.")
		return 1
	}
	if err := cl.Confirm(ctx); err != nil {
		if reason, revoked := agentapi.Revoked(err); revoked {
			_ = st.Delete(origin)
			fmt.Fprintf(e.stderr, "webhooktrap pair: the server revoked this agent (%s); create a new code and pair again\n", clean(reason))
			return 1
		}
		fmt.Fprintln(e.stderr, "webhooktrap pair: confirmation failed: "+clean(err.Error())+"; run webhooktrap agent to try again")
		return 1
	}
	a.Confirmed = true
	if err := st.Save(a); err != nil {
		fmt.Fprintln(e.stderr, "webhooktrap pair: can't save the pairing: "+clean(err.Error()))
		return 1
	}
	fmt.Fprintf(e.stdout, "Joined %s as %s. Run `webhooktrap agent` to take runs from the browser.\n", label, kept)
	return 0
}

func pairError(err error) string {
	var ae *agentapi.APIError
	if errors.As(err, &ae) {
		switch ae.Code {
		case "invalid_code":
			return "the code is unknown, used or expired; create a new one in your browser"
		case "agent_too_old":
			return "this agent is too old for the server; it needs " + clean(display(ae.MinAgentVersion, 32)) + " or later"
		}
		if ae.Status == 429 {
			return fmt.Sprintf("too many attempts; try again in %s", ae.RetryAfter)
		}
	}
	return clean(err.Error())
}

// display cleans a server-provided string for the terminal and caps it at n
// runes (TM-41, decision 33; the shared cleaner, AC-2).
func display(s string, n int) string { return scrub.TerminalCap(s, n) }
