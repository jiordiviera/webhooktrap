package guard

import (
	"context"
	"errors"
	"fmt"
	"net"
	"net/netip"
	"net/url"
	"strconv"
	"strings"
	"time"
)

// Mode is the guard mode of a role (ADR-010 §7). Only `local` is built in this
// increment; `public` and `allowlist` belong to the server runner.
type Mode string

const Local Mode = "local"

// Resolver resolves host names. Tests inject a fake; production uses net.
type Resolver interface {
	LookupNetIP(ctx context.Context, network, host string) ([]netip.Addr, error)
}

// Error is a refused target. Reason is the RUN-1 code.
type Error struct {
	Reason string // target_not_allowed
	Msg    string
}

func (e *Error) Error() string { return e.Msg }

func refuse(format string, a ...any) error {
	return &Error{Reason: "target_not_allowed", Msg: fmt.Sprintf(format, a...)}
}

// Guard decides which targets a run may reach.
type Guard struct {
	Mode Mode
	// Declared holds exact "host:port" entries (TGT-2), lower-case.
	Declared map[string]bool
	Resolver Resolver
}

// New returns a local-mode guard with the declared entries.
func New(declared []string) *Guard {
	g := &Guard{Mode: Local, Declared: map[string]bool{}, Resolver: net.DefaultResolver}
	for _, d := range declared {
		if d = strings.ToLower(strings.TrimSpace(d)); d != "" {
			g.Declared[d] = true
		}
	}
	return g
}

// Target is a checked target with its addresses resolved once and pinned (TGT-5).
type Target struct {
	URL     *url.URL
	Scheme  string
	Host    string
	Port    int
	Path    string
	Addrs   []netip.Addr
	Local   bool
	Warning string // printed on every run for a declared non-local target
}

// providerDeny: provider domains are never targets (prototype deny list).
var providerDeny = []string{
	"stripe.com", "github.com", "githubusercontent.com", "shopify.com", "myshopify.com",
	"twilio.com", "slack.com", "svix.com",
}

// metadata holds cloud metadata addresses refused even when declared (TM-55).
// Link-local ranges are refused as a whole below; these are the ones outside them.
var metadata = []netip.Prefix{
	netip.MustParsePrefix("fd00:ec2::254/128"),  // AWS IPv6 IMDS
	netip.MustParsePrefix("100.100.100.200/32"), // Alibaba Cloud
	netip.MustParsePrefix("168.63.129.16/32"),   // Azure wire server
	netip.MustParsePrefix("192.0.0.192/32"),     // Oracle Cloud
	netip.MustParsePrefix("fd20:ce::254/128"),   // GCP IPv6 metadata
}

var metadataNames = []string{"metadata.google.internal", "metadata.goog", "metadata", "instance-data", "metadata.azure.com"}

// Check validates a URL and pins its addresses. Nothing is dialled.
func (g *Guard) Check(ctx context.Context, raw string) (*Target, error) {
	if g.Mode != Local {
		return nil, refuse("guard mode %q is not available in this build", g.Mode)
	}
	u, err := url.Parse(raw)
	if err != nil {
		return nil, refuse("invalid target URL")
	}
	if u.Scheme != "http" && u.Scheme != "https" {
		return nil, refuse("target must be an http:// or https:// URL")
	}
	if u.User != nil {
		return nil, refuse("target must not contain credentials")
	}
	if u.Opaque != "" || u.Fragment != "" {
		return nil, refuse("target URL is malformed")
	}
	host := normHost(u.Hostname())
	if host == "" {
		return nil, refuse("target has no host")
	}
	port := 80
	if u.Scheme == "https" {
		port = 443
	}
	if ps := u.Port(); ps != "" {
		p, err := strconv.Atoi(ps)
		if err != nil || p < 1 || p > 65535 {
			return nil, refuse("target port is invalid")
		}
		port = p
	}
	for _, d := range providerDeny {
		if host == d || strings.HasSuffix(host, "."+d) {
			return nil, refuse("refusing %s: provider endpoints are never targets", host)
		}
	}
	for _, m := range metadataNames {
		if host == m {
			return nil, refuse("refusing %s: metadata endpoints are never targets", host)
		}
	}
	t := &Target{URL: u, Scheme: u.Scheme, Host: host, Port: port, Path: u.EscapedPath()}
	if t.Path == "" {
		t.Path = "/"
	}
	if u.RawQuery != "" {
		t.Path += "?" + u.RawQuery
	}

	var addrs []netip.Addr
	switch {
	case isLocalName(host): // RFC 6761, no DNS query (TGT-1)
		addrs = []netip.Addr{netip.MustParseAddr("127.0.0.1"), netip.MustParseAddr("::1")}
	default:
		if a, ok := literal(host); ok {
			addrs = []netip.Addr{a}
		} else {
			rctx, cancel := context.WithTimeout(ctx, defaultResolveTimeout)
			defer cancel()
			addrs, err = g.Resolver.LookupNetIP(rctx, "ip", host)
			if err != nil || len(addrs) == 0 {
				return nil, &Error{Reason: "target_unreachable", Msg: fmt.Sprintf("cannot resolve %s", host)}
			}
		}
	}
	loop, other := 0, 0
	for _, a := range addrs {
		if err := alwaysRefused(a); err != nil {
			return nil, refuse("refusing %s: %v", host, err)
		}
		if isLoopback(a) {
			loop++
		} else {
			other++
		}
	}
	switch {
	case loop > 0 && other > 0:
		return nil, refuse("refusing %s: it resolves to loopback and non-loopback addresses", host)
	case other == 0:
		t.Local = true
	case g.Declared[strings.ToLower(net.JoinHostPort(host, strconv.Itoa(port)))]:
		t.Warning = fmt.Sprintf("%s:%d is not a local target. Allowed because it is declared in webhooktrap.yml (targets).", host, port)
	default:
		return nil, refuse("refusing %s: not a loopback address and not declared (add %s:%d to targets in webhooktrap.yml)", host, host, port)
	}
	t.Addrs = addrs
	return t, nil
}

// alwaysRefused covers addresses refused in every mode, declared or not.
func alwaysRefused(a netip.Addr) error {
	u := a.Unmap()
	switch {
	case a.Zone() != "":
		return errors.New("zoned addresses are not allowed")
	case u.IsUnspecified():
		return errors.New("0.0.0.0 and :: are not targets")
	case u.IsLinkLocalUnicast() || u.IsLinkLocalMulticast():
		return errors.New("link-local addresses are never targets")
	case u.IsMulticast():
		return errors.New("multicast addresses are never targets")
	case u.Is4() && u.As4()[0] == 255:
		return errors.New("broadcast addresses are never targets")
	}
	for _, m := range metadata {
		if m.Contains(u) {
			return errors.New("metadata addresses are never targets")
		}
	}
	return nil
}

func isLoopback(a netip.Addr) bool { return a.Unmap().IsLoopback() }

func isLocalName(h string) bool { return h == "localhost" || strings.HasSuffix(h, ".localhost") }

func normHost(h string) string {
	h = strings.ToLower(strings.TrimSpace(h))
	h = strings.TrimPrefix(strings.TrimSuffix(h, "]"), "[")
	return strings.TrimRight(h, ".")
}

// literal parses IP literals including inet_aton forms (127.1, 0x7f.1).
func literal(host string) (netip.Addr, bool) {
	if a, err := netip.ParseAddr(host); err == nil {
		return a, true
	}
	parts := strings.Split(host, ".")
	if len(parts) == 0 || len(parts) > 4 {
		return netip.Addr{}, false
	}
	vals := make([]uint64, len(parts))
	for i, p := range parts {
		v, err := strconv.ParseUint(p, 0, 32)
		if p == "" || err != nil {
			return netip.Addr{}, false
		}
		vals[i] = v
	}
	var n uint64
	last := len(vals) - 1
	for i := 0; i < last; i++ {
		if vals[i] > 255 {
			return netip.Addr{}, false
		}
		n |= vals[i] << (8 * uint(3-i))
	}
	if vals[last] >= uint64(1)<<(8*uint(4-last)) {
		return netip.Addr{}, false
	}
	n |= vals[last]
	return netip.AddrFrom4([4]byte{byte(n >> 24), byte(n >> 16), byte(n >> 8), byte(n)}), true
}

// Dial connects to one of the pinned addresses and re-checks the peer address
// of the connection (ADR-010 §7: check, then re-check at dial).
func (t *Target) Dial(ctx context.Context, d *net.Dialer) (net.Conn, error) {
	var last error
	for _, a := range t.Addrs {
		c, err := d.DialContext(ctx, "tcp", net.JoinHostPort(a.Unmap().String(), strconv.Itoa(t.Port)))
		if err != nil {
			last = err
			continue
		}
		peer, perr := netip.ParseAddrPort(c.RemoteAddr().String())
		if perr != nil || peer.Addr().Unmap() != a.Unmap() || alwaysRefused(peer.Addr()) != nil || (t.Local && !isLoopback(peer.Addr())) {
			_ = c.Close()
			return nil, refuse("connection peer %s is not the pinned address", c.RemoteAddr())
		}
		return c, nil
	}
	if last == nil {
		last = errors.New("no address")
	}
	return nil, last
}

const defaultResolveTimeout = 5 * time.Second
