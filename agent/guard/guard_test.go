package guard

import (
	"context"
	"errors"
	"net/netip"
	"testing"
)

type fakeDNS map[string][]string

func (f fakeDNS) LookupNetIP(_ context.Context, _, host string) ([]netip.Addr, error) {
	var out []netip.Addr
	for _, a := range f[host] {
		out = append(out, netip.MustParseAddr(a))
	}
	if len(out) == 0 {
		return nil, errors.New("nxdomain")
	}
	return out, nil
}

func TestLocalMode(t *testing.T) {
	g := New([]string{"staging.example.test:8443", "10.0.4.12:3000"})
	g.Resolver = fakeDNS{
		"dev.test":             {"127.0.0.1"},
		"mixed.test":           {"127.0.0.1", "93.184.216.34"},
		"public.test":          {"93.184.216.34"},
		"staging.example.test": {"203.0.113.7"},
		"linklocal.test":       {"169.254.169.254"},
	}
	ok := []string{
		"http://localhost:3000/webhooks/stripe", "http://api.localhost/x", "http://127.0.0.1:3000/",
		"http://[::1]:3000/", "http://127.1:3000/", "http://dev.test:3000/", "https://staging.example.test:8443/hook",
		"http://[::ffff:127.0.0.1]:3000/",
	}
	for _, u := range ok {
		if _, err := g.Check(context.Background(), u); err != nil {
			t.Errorf("%s refused: %v", u, err)
		}
	}
	refused := []string{
		"http://0.0.0.0:3000/", "http://[::]:3000/", "http://mixed.test/", "http://public.test/",
		"http://user:pw@localhost/", "ftp://localhost/", "https://api.stripe.com/", "http://hooks.myshopify.com/",
		"http://169.254.169.254/latest/meta-data", "http://linklocal.test/", "http://metadata.google.internal/",
		"http://[fe80::1]:3000/", "http://staging.example.test:9999/", "http://[fd00:ec2::254]/",
		"http://10.0.4.12:3001/", "http://224.0.0.1/",
	}
	for _, u := range refused {
		if _, err := g.Check(context.Background(), u); err == nil {
			t.Errorf("%s allowed", u)
		}
	}
	// A declared non-local target is allowed with a warning on every run (TGT-2).
	tg, err := g.Check(context.Background(), "http://10.0.4.12:3000/webhooks/shopify")
	if err != nil || tg.Local || tg.Warning == "" {
		t.Fatalf("declared private target: %+v %v", tg, err)
	}
	// Link-local and metadata are refused even when declared (TM-55).
	g2 := New([]string{"169.254.169.254:80"})
	if _, err := g2.Check(context.Background(), "http://169.254.169.254/"); err == nil {
		t.Fatal("declared metadata address allowed")
	}
}
