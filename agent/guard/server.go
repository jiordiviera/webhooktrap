package guard

import (
	"context"
	"errors"
	"fmt"
	"net"
	"net/netip"
	"strconv"
	"strings"
)

// Server-runner modes (ADR-010 §7, §12.1; ADR-006 amendment 1).
const (
	// Public reaches public internet addresses only (hosted runner).
	Public Mode = "public"
	// Allowlist reaches public addresses plus the entries of an allow-list
	// (self-host B′).
	Allowlist Mode = "allowlist"
)

// AllowEntry is one allow-list entry: a host name or a prefix, and an
// inclusive port range.
type AllowEntry struct {
	Host     string       // lower-case host name, or "" for a prefix entry
	Prefix   netip.Prefix // valid for a prefix entry
	PortFrom int
	PortTo   int
}

// String is the entry's canonical form, used to compare a job's entries
// with the runner's local ones (RN-3).
func (e AllowEntry) String() string {
	h := e.Host
	if h == "" {
		h = e.Prefix.String()
	}
	return h + ":" + strconv.Itoa(e.PortFrom) + "-" + strconv.Itoa(e.PortTo)
}

// ParseAllowEntry reads "host:port", "host:from-to", "cidr:port" or
// "cidr:from-to" (an IPv6 prefix is written in brackets: "[fd00::/8]:443").
func ParseAllowEntry(s string) (AllowEntry, error) {
	s = strings.TrimSpace(s)
	i := strings.LastIndexByte(s, ':')
	if i <= 0 {
		return AllowEntry{}, fmt.Errorf("allow-list entry %q: want host:port or cidr:port", s)
	}
	hostPart, portPart := s[:i], s[i+1:]
	from, to, ok := strings.Cut(portPart, "-")
	if !ok {
		to = from
	}
	pf, err1 := strconv.Atoi(from)
	pt, err2 := strconv.Atoi(to)
	if err1 != nil || err2 != nil {
		return AllowEntry{}, fmt.Errorf("allow-list entry %q: bad port", s)
	}
	return NewAllowEntry(strings.Trim(hostPart, "[]"), "", pf, pt)
}

// NewAllowEntry builds an entry from a host or a CIDR (exactly one) and a
// port range.
func NewAllowEntry(host, cidr string, from, to int) (AllowEntry, error) {
	if from < 1 || to > 65535 || from > to {
		return AllowEntry{}, fmt.Errorf("allow-list port range %d-%d is invalid", from, to)
	}
	e := AllowEntry{PortFrom: from, PortTo: to}
	if host != "" && cidr == "" && strings.Contains(host, "/") {
		host, cidr = "", host
	}
	switch {
	case host != "" && cidr == "":
		h := normHost(host)
		if h == "" || strings.ContainsAny(h, " /") {
			return AllowEntry{}, fmt.Errorf("allow-list host %q is invalid", host)
		}
		if a, ok := literal(h); ok {
			e.Prefix = netip.PrefixFrom(a.Unmap(), a.Unmap().BitLen())
		} else {
			e.Host = h
		}
	case cidr != "" && host == "":
		p, err := netip.ParsePrefix(strings.TrimSpace(cidr))
		if err != nil {
			return AllowEntry{}, fmt.Errorf("allow-list prefix %q is invalid", cidr)
		}
		e.Prefix = netip.PrefixFrom(p.Addr().Unmap(), max(0, p.Bits()-(p.Addr().BitLen()-p.Addr().Unmap().BitLen()))).Masked()
	default:
		return AllowEntry{}, errors.New("allow-list entry needs a host or a cidr")
	}
	return e, nil
}

func (e AllowEntry) port(p int) bool { return p >= e.PortFrom && p <= e.PortTo }

// within reports whether e is covered by some entry of local (same host or
// a prefix inside a local prefix, port range inside): a job's entries can
// only narrow the runner's own list (RN-3).
func (e AllowEntry) within(local []AllowEntry) bool {
	for _, l := range local {
		if e.PortFrom < l.PortFrom || e.PortTo > l.PortTo {
			continue
		}
		switch {
		case e.Host != "" && l.Host == e.Host:
			return true
		case e.Host == "" && l.Host == "" && l.Prefix.Bits() <= e.Prefix.Bits() && l.Prefix.Contains(e.Prefix.Addr()):
			return true
		}
	}
	return false
}

// CeilingError is a job asking for more than the local ceiling (RN-3).
type CeilingError struct{ Msg string }

func (e *CeilingError) Error() string { return e.Msg }

// NewServer returns the guard for one runner job. ceiling and local are the
// runner's own configuration; requested and entries come from the job and
// may only narrow them (RN-3): the hosted runner (ceiling public) refuses
// every allowlist job, and an allowlist job's entries must each sit inside
// a local entry. deny holds addresses the runner never dials whatever the
// mode (RN-4: the runner-API origin, its own networks).
func NewServer(ceiling Mode, local []AllowEntry, requested Mode, entries []AllowEntry, deny []netip.Prefix) (*Guard, error) {
	switch ceiling {
	case Public, Allowlist:
	default:
		return nil, &CeilingError{fmt.Sprintf("runner ceiling %q is not a server mode", ceiling)}
	}
	g := &Guard{Mode: requested, Resolver: net.DefaultResolver, Deny: deny}
	switch requested {
	case Public:
		if len(entries) > 0 {
			return nil, &CeilingError{"a public job carries no allow-list"}
		}
	case Allowlist:
		if ceiling != Allowlist {
			return nil, &CeilingError{"this runner's ceiling is public: it refuses every allowlist job"}
		}
		if len(entries) == 0 {
			return nil, &CeilingError{"an allowlist job needs at least one entry"}
		}
		for _, e := range entries {
			if !e.within(local) {
				return nil, &CeilingError{"allow-list entry " + e.String() + " is not in this runner's local allow-list"}
			}
		}
		g.Allow = entries
	default:
		return nil, &CeilingError{fmt.Sprintf("guard mode %q is not available to a runner", requested)}
	}
	return g, nil
}

// nonPublic holds every range a public-mode target never resolves to:
// private, shared, loopback, link-local, documentation, benchmarking,
// reserved, multicast and the IPv6 transition ranges that embed an IPv4
// address (A09 §7.1 item 6).
var nonPublic = func() []netip.Prefix {
	var out []netip.Prefix
	for _, s := range []string{
		"0.0.0.0/8", "10.0.0.0/8", "100.64.0.0/10", "127.0.0.0/8", "169.254.0.0/16", "172.16.0.0/12",
		"192.0.0.0/24", "192.0.2.0/24", "192.88.99.0/24", "192.168.0.0/16", "198.18.0.0/15",
		"198.51.100.0/24", "203.0.113.0/24", "224.0.0.0/4", "240.0.0.0/4",
		"::/128", "::1/128", "::/96", "64:ff9b::/96", "64:ff9b:1::/48", "100::/64", "2001::/23",
		"2001:db8::/32", "2002::/16", "3fff::/20", "5f00::/16", "fc00::/7", "fe80::/10", "fec0::/10", "ff00::/8",
	} {
		out = append(out, netip.MustParsePrefix(s))
	}
	return out
}()

// IsPublic reports whether a is a public unicast address.
func IsPublic(a netip.Addr) bool {
	u := a.Unmap()
	if !u.IsValid() || u.Zone() != "" {
		return false
	}
	for _, p := range nonPublic {
		if p.Contains(u) {
			return false
		}
	}
	return alwaysRefused(u) == nil
}

// serverAddr decides one resolved address of a server-mode target.
func (g *Guard) serverAddr(host string, port int, a netip.Addr) error {
	u := a.Unmap()
	if err := alwaysRefused(a); err != nil {
		return err
	}
	if u.IsLoopback() {
		return errors.New("loopback addresses are never server-run targets")
	}
	for _, d := range g.Deny {
		if d.Contains(u) {
			return errors.New("this address belongs to the server or the runner's own network (RN-4)")
		}
	}
	if IsPublic(u) {
		return nil
	}
	if g.Mode == Allowlist {
		for _, e := range g.Allow {
			if !e.port(port) {
				continue
			}
			if (e.Host != "" && e.Host == host) || (e.Host == "" && e.Prefix.Contains(u)) {
				return nil
			}
		}
		return errors.New("not a public address and not in the job's allow-list")
	}
	return errors.New("not a public address")
}

// checkServer is Check for public and allowlist modes: every resolved
// address must pass, else the whole target is refused.
func (g *Guard) checkServer(ctx context.Context, t *Target) (*Target, error) {
	if isLocalName(t.Host) {
		return nil, refuse("refusing %s: local names are never server-run targets", t.Host)
	}
	var addrs []netip.Addr
	if a, ok := literal(t.Host); ok {
		addrs = []netip.Addr{a}
	} else {
		rctx, cancel := context.WithTimeout(ctx, defaultResolveTimeout)
		defer cancel()
		var err error
		addrs, err = g.Resolver.LookupNetIP(rctx, "ip", t.Host)
		if err != nil || len(addrs) == 0 {
			return nil, &Error{Reason: "target_unreachable", Msg: fmt.Sprintf("cannot resolve %s", t.Host)}
		}
	}
	for _, a := range addrs {
		if err := g.serverAddr(t.Host, t.Port, a); err != nil {
			return nil, refuse("refusing %s: %v", t.Host, err)
		}
	}
	t.Addrs = addrs
	t.peer = func(a netip.Addr) error { return g.serverAddr(t.Host, t.Port, a) }
	return t, nil
}

// OwnNetworks returns the prefixes of this host's interfaces: each address
// exactly, and the whole network for a non-public one (RN-4: a self-host
// runner never reaches its own Compose networks).
func OwnNetworks(addrs []net.Addr) []netip.Prefix {
	var out []netip.Prefix
	for _, a := range addrs {
		ipn, ok := a.(*net.IPNet)
		if !ok {
			continue
		}
		ip, ok := netip.AddrFromSlice(ipn.IP)
		if !ok {
			continue
		}
		ip = ip.Unmap()
		out = append(out, netip.PrefixFrom(ip, ip.BitLen()))
		if ones, bits := ipn.Mask.Size(); bits > 0 && !IsPublic(ip) && !ip.IsLoopback() && !ip.IsLinkLocalUnicast() {
			if ip.Is4() && bits == 128 {
				ones -= 96
			}
			if p, err := ip.Prefix(ones); err == nil {
				out = append(out, p)
			}
		}
	}
	return out
}

// HostPrefixes turns resolved addresses into exact prefixes (RN-4 deny).
func HostPrefixes(addrs []netip.Addr) []netip.Prefix {
	out := make([]netip.Prefix, 0, len(addrs))
	for _, a := range addrs {
		u := a.Unmap()
		out = append(out, netip.PrefixFrom(u, u.BitLen()))
	}
	return out
}
