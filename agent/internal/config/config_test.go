package config

import (
	"strings"
	"testing"
	"time"
)

func TestParseRD(t *testing.T) {
	src := `target: http://localhost:3000/webhooks/stripe   # comment
database: env:DATABASE_URL
secret: env:STRIPE_WEBHOOK_SECRET
fixtures:
  - webhooktrap/fixtures/stripe/invoice.paid.json
scenarios: [once, twice, "late"]
probe:
  - as: orders.count
    cmd: psql "$DATABASE_URL" -Atc "select count(*) from orders where stripe_invoice_id = '{{invoice.id}}'"
  - as: subscriptions.status
    url: http://localhost:3000/_debug/sub?id={{subscription.id}}
    covers: [subscription]
settle: 2s
reset: npm run db:reset:webhooks
`
	c, err := Parse([]byte(src))
	if err != nil {
		t.Fatal(err)
	}
	if c.Target != "http://localhost:3000/webhooks/stripe" || c.Secret != "STRIPE_WEBHOOK_SECRET" || len(c.Fixtures) != 1 ||
		strings.Join(c.Scenarios, ",") != "once,twice,late" || c.Settle != 2*time.Second || c.Reset != "npm run db:reset:webhooks" {
		t.Fatalf("%+v", c)
	}
	if len(c.Probe) != 2 || c.Probe[0].Kind != "cmd" || !strings.Contains(c.Probe[0].Text, "'{{invoice.id}}'") || c.Probe[1].Covers[0] != "subscription" {
		t.Fatalf("%+v", c.Probe)
	}
}

func TestRefused(t *testing.T) {
	for _, src := range []string{
		"secret: whsec_literal\n", // a value, not an env name
		"budget: 50\n",            // BUD-2 range
		"probe:\n  - as: Bad Name\n    cmd: x\n",
		"probe:\n  - as: a\n    cmd: x\n    url: y\n",
		"unknown: 1\n",
		"target: &anchor x\n",
		"target: a\ntarget: b\n",                     // repeated key
		"a: &x y\ntarget: *x\n",                      // alias
		"target: !custom x\n",                        // custom tag
		"target: x\n---\ntarget: y\n",                // second document
		"target: {a: b}\n",                           // map where a value is expected
		"scenarios: [[once]]\n",                      // nested list
		"probe:\n  - as: a\n    cmd: x\n  - plain\n", // mixed list
		"probe:\n  - as: a\n    cmd: x\n    as: b\n", // repeated probe key
		"- just\n- a list\n",
		"target: [unclosed\n",
	} {
		if _, err := Parse([]byte(src)); err == nil {
			t.Errorf("accepted %q", src)
		}
	}
}

func TestBlockScalarAndEmpty(t *testing.T) {
	c, err := Parse([]byte("reset: |\n  npm run a\n  npm run b\nfixtures:\n"))
	if err != nil {
		t.Fatal(err)
	}
	if c.Reset != "npm run a\nnpm run b\n" || c.Fixtures != nil {
		t.Fatalf("%+v", c)
	}
	if c, err := Parse(nil); err != nil || c.Target != "" {
		t.Fatalf("empty file: %v %+v", err, c)
	}
}
