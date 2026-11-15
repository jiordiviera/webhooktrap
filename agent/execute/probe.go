package execute

import (
	"bytes"
	"context"
	"errors"
	"fmt"
	"io"
	"net"
	"net/http"
	"strings"
	"time"

	"github.com/jiordiviera/webhooktrap/agent/guard"
)

// Probe limits (PRB-1, PRB-2, PRB-9).
const (
	DefaultProbeTimeout = 5 * time.Second
	MaxProbeTimeout     = 30 * time.Second
	ResetTimeout        = 60 * time.Second
	maxProbeValue       = 64 << 10
	errorExcerpt        = 200
)

// ProbeEntry is one resolved probe entry. For url: Text is the URL with
// template values filled in, query-escaped. For cmd: Text is the command as
// written in webhooktrap.yml with each {{T.id}} replaced by a reference to
// the environment variable WT_<T>_ID (EnvRef), never by the value; the values
// are in Env, which the command gets on top of the safe environment (AC-1).
// It never leaves the machine (PV-8).
type ProbeEntry struct {
	Name string
	Kind string   // cmd | url
	Text string   // command or URL
	Env  []string // NAME=value for each template value (cmd: only)
}

// TemplateEnvName is the environment variable that carries the value of
// {{t.id}} to a cmd: probe: WT_<T>_ID, t upper-cased (t is [a-z0-9_]+).
func TemplateEnvName(t string) string { return "WT_" + strings.ToUpper(t) + "_ID" }

// ProbeError carries the local-only excerpt of a failed entry (PRB-5).
type ProbeError struct {
	Entry   string
	Excerpt string
}

func (e *ProbeError) Error() string { return "probe " + e.Entry + " failed: " + e.Excerpt }

// CommandProbe runs cmd: entries through the shell and url: entries through
// the guard. Commands get an environment with every secret variable removed
// (TM-53).
type CommandProbe struct {
	Entries []ProbeEntry
	Env     []string
	Timeout time.Duration
	Guard   *guard.Guard
}

// Names implements Prober.
func (p *CommandProbe) Names() []string {
	out := make([]string, len(p.Entries))
	for i, e := range p.Entries {
		out[i] = e.Name
	}
	return out
}

// Read implements Prober. Any failing entry fails the reading.
func (p *CommandProbe) Read(ctx context.Context) ([]string, error) {
	out := make([]string, len(p.Entries))
	for i, e := range p.Entries {
		tctx, cancel := context.WithTimeout(ctx, p.timeout())
		var v string
		var err error
		switch e.Kind {
		case "cmd":
			v, err = runShell(tctx, e.Text, withEnv(p.Env, e.Env))
		case "url":
			v, err = p.get(tctx, e.Text)
		default:
			err = fmt.Errorf("unsupported probe kind %q", e.Kind)
		}
		cancel()
		if err == nil && v == "" {
			err = errors.New("empty value")
		}
		if err != nil {
			return nil, &ProbeError{Entry: e.Name, Excerpt: excerpt(err.Error())}
		}
		out[i] = v
	}
	return out, nil
}

func (p *CommandProbe) timeout() time.Duration {
	if p.Timeout <= 0 {
		return DefaultProbeTimeout
	}
	return min(p.Timeout, MaxProbeTimeout)
}

func (p *CommandProbe) get(ctx context.Context, raw string) (string, error) {
	t, err := p.Guard.Check(ctx, raw) // url: probes pass TGT-1/TGT-2 (PRB-2)
	if err != nil {
		return "", err
	}
	client := &http.Client{
		Transport: &http.Transport{
			Proxy: nil,
			DialContext: func(ctx context.Context, _, _ string) (net.Conn, error) {
				return t.Dial(ctx, &net.Dialer{Timeout: dialTimeout})
			},
			DisableKeepAlives: true,
		},
		CheckRedirect: func(*http.Request, []*http.Request) error { return http.ErrUseLastResponse },
	}
	req, err := http.NewRequestWithContext(ctx, http.MethodGet, raw, nil)
	if err != nil {
		return "", err
	}
	resp, err := client.Do(req)
	if err != nil {
		return "", err
	}
	defer resp.Body.Close()
	body, _ := io.ReadAll(io.LimitReader(resp.Body, maxProbeValue))
	if resp.StatusCode < 200 || resp.StatusCode > 299 {
		return "", fmt.Errorf("HTTP %d", resp.StatusCode)
	}
	return strings.TrimRight(string(body), " \t\r\n"), nil
}

// ShellReset runs the reset command (PRB-9).
type ShellReset struct {
	Command string
	Env     []string
}

// Reset implements Resetter.
func (r *ShellReset) Reset(ctx context.Context) error {
	tctx, cancel := context.WithTimeout(ctx, ResetTimeout)
	defer cancel()
	_, err := runShell(tctx, r.Command, r.Env)
	return err
}

// withEnv returns base with extra appended, dropping any variable of base
// that extra sets: a template value always wins over an inherited WT_*_ID.
func withEnv(base, extra []string) []string {
	if len(extra) == 0 {
		return base
	}
	set := map[string]bool{}
	for _, kv := range extra {
		name, _, _ := strings.Cut(kv, "=")
		set[name] = true
	}
	out := make([]string, 0, len(base)+len(extra))
	for _, kv := range base {
		name, _, _ := strings.Cut(kv, "=")
		if !set[name] {
			out = append(out, kv)
		}
	}
	return append(out, extra...)
}

// runShell runs the user's own command text (confirmed per file hash,
// PRB-11) through the system shell (shellCommand). Capture-derived values
// are never part of that text: they arrive in env only.
func runShell(ctx context.Context, command string, env []string) (string, error) {
	cmd := shellCommand(ctx, command)
	cmd.Env = env
	var stdout, stderr bytes.Buffer
	cmd.Stdout = &limitWriter{w: &stdout, n: maxProbeValue}
	cmd.Stderr = &limitWriter{w: &stderr, n: errorExcerpt}
	cmd.WaitDelay = time.Second
	if err := cmd.Run(); err != nil {
		if ctx.Err() != nil {
			return "", errors.New("timed out")
		}
		msg := strings.TrimSpace(stderr.String())
		if msg == "" {
			msg = err.Error()
		}
		return "", errors.New(msg)
	}
	return strings.TrimRight(stdout.String(), " \t\r\n"), nil
}

type limitWriter struct {
	w io.Writer
	n int
}

func (l *limitWriter) Write(p []byte) (int, error) {
	if l.n > 0 {
		k := min(len(p), l.n)
		_, _ = l.w.Write(p[:k])
		l.n -= k
	}
	return len(p), nil
}

func excerpt(s string) string {
	if len(s) > errorExcerpt {
		return s[:errorExcerpt]
	}
	return s
}

// SafeEnv returns environ without the named variables (secret names, the CI
// token): probe and reset commands never see a secret (TM-53).
func SafeEnv(environ []string, strip []string) []string {
	drop := map[string]bool{"WEBHOOKTRAP_TOKEN": true}
	for _, s := range strip {
		drop[s] = true
	}
	var out []string
	for _, kv := range environ {
		name, _, _ := strings.Cut(kv, "=")
		if !drop[name] {
			out = append(out, kv)
		}
	}
	return out
}
