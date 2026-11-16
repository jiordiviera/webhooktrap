package guard

import (
	"context"
	"errors"
	"net"
	"net/netip"
	"testing"
)

func mustEntry(t *testing.T, s string) AllowEntry {
	t.Helper()
	e, err := ParseAllowEntry(s)
	if err != nil {
		t.Fatal(err)
	}
	return e
}

func TestServerCeiling(t *testing.T) {
	local := []AllowEntry{mustEntry(t, "10.1.2.0/24:8080"), mustEntry(t, "billing.internal:443-444")}
	var ce *CeilingError
	for name, c := range map[string]struct {
		ceiling, requested Mode
		entries            []AllowEntry
		ok                 bool
	}{
		"public job, public runner":         {Public, Public, nil, true},
		"allowlist job, public runner":      {Public, Allowlist, []AllowEntry{local[0]}, false},
		"public job with entries":           {Allowlist, Public, []AllowEntry{local[0]}, false},
		"allowlist job, no entries":         {Allowlist, Allowlist, nil, false},
		"narrower prefix":                   {Allowlist, Allowlist, []AllowEntry{mustEntry(t, "10.1.2.128/25:8080")}, true},
		"wider prefix":                      {Allowlist, Allowlist, []AllowEntry{mustEntry(t, "10.0.0.0/8:8080")}, false},
		"wider ports":                       {Allowlist, Allowlist, []AllowEntry{mustEntry(t, "billing.internal:443-445")}, false},
		"same host":                         {Allowlist, Allowlist, []AllowEntry{mustEntry(t, "billing.internal:444")}, true},
		"local mode on a runner":            {Allowlist, Local, nil, false},
		"local ceiling is not a server one": {Local, Public, nil, false},
	} {
		_, err := NewServer(c.ceiling, local, c.requested, c.entries, nil)
		if c.ok != (err == nil) || (err != nil && !errors.As(err, &ce)) {
			t.Errorf("%s: %v", name, err)
		}
	}
}

func TestServerCheck(t *testing.T) {
	dns := fakeDNS{
		"public.example":   {"93.184.216.34"},
		"mixed.example":    {"93.184.216.34", "10.0.0.1"},
		"rebind.example":   {"127.0.0.1"},
		"app.example":      {"198.51.100.200"},
		"billing.internal": {"10.1.2.9"},
		"nat64.example":    {"64:ff9b::a00:1"},
	}
	deny := HostPrefixes([]netip.Addr{netip.MustParseAddr("93.184.216.99")})
	pub, _ := NewServer(Public, nil, Public, nil, deny)
	pub.Resolver = dns
	allow, _ := NewServer(Allowlist, []AllowEntry{mustEntry(t, "billing.internal:443")}, Allowlist, []AllowEntry{mustEntry(t, "billing.internal:443")}, deny)
	allow.Resolver = dns
	for _, c := range []struct {
		g   *Guard
		url string
		ok  bool
	}{
		{pub, "https://public.example/hook", true},
		{pub, "https://mixed.example/hook", false}, // every address must pass
		{pub, "https://rebind.example/", false},
		{pub, "http://localhost:3000/", false},
		{pub, "http://10.0.0.1/", false},
		{pub, "http://169.254.169.254/", false},
		{pub, "http://[fd00::1]/", false},
		{pub, "https://nat64.example/", false},
		{pub, "https://93.184.216.99/", false}, // RN-4 deny
		{pub, "https://billing.internal/", false},
		{allow, "https://billing.internal/", true},
		{allow, "https://billing.internal:8443/", false}, // port outside the entry
		{allow, "https://public.example/", true},
		{allow, "https://93.184.216.99/", false},
	} {
		_, err := c.g.Check(context.Background(), c.url)
		if c.ok != (err == nil) {
			t.Errorf("%s (%s): %v", c.url, c.g.Mode, err)
		}
	}
}

func TestOwnNetworks(t *testing.T) {
	_, n10, _ := net.ParseCIDR("172.18.0.5/16")
	n10.IP = net.ParseIP("172.18.0.5")
	_, pub, _ := net.ParseCIDR("93.184.216.34/24")
	pub.IP = net.ParseIP("93.184.216.34")
	got := OwnNetworks([]net.Addr{n10, pub})
	has := func(s string) bool {
		for _, p := range got {
			if p.String() == s {
				return true
			}
		}
		return false
	}
	if !has("172.18.0.5/32") || !has("172.18.0.0/16") || !has("93.184.216.34/32") || has("93.184.216.0/24") {
		t.Fatalf("OwnNetworks = %v", got)
	}
}
