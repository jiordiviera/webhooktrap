package execute

import (
	"errors"
	"strings"
	"time"

	"github.com/jiordiviera/webhooktrap/agent/plan"
	"github.com/jiordiviera/webhooktrap/agent/signing"
)

// buildRequest turns a planned delivery into bytes on the wire at wall time
// now. Headers follow the re-send allow-list (TM-51): content type, the
// pack's event headers, the signature as the scenario sets it, and
// Webhooktrap-* (SIGN-3). Captured signature, host, forwarding and
// hop-by-hop headers are never re-sent.
func (o *Options) buildRequest(d plan.Delivery, now time.Time) (*Request, error) {
	ev := o.Plan.Events[d.Event]
	c, ok := o.captures[ev.Ref]
	if !ok {
		return nil, errors.New("capture missing for " + ev.Ref)
	}
	p := o.Pack
	scheme := p.Signing.Scheme
	body := c.Body
	if d.Mode == plan.ModeTampered {
		tb, _, err := plan.TamperBody(p, ev.EventType, c.Body)
		if err != nil {
			return nil, err
		}
		body = tb
	}

	sigNames := map[string]bool{}
	for _, h := range append(append([]string{}, p.Signing.Headers...), signing.Headers(scheme)...) {
		sigNames[strings.ToLower(h)] = true
	}
	keep := map[string]bool{"content-type": true}
	for _, h := range p.Resend {
		keep[strings.ToLower(h)] = true
	}
	var hdr [][2]string
	var captured [][2]string // captured signature headers, for kind "original"
	for _, kv := range c.Headers {
		name := strings.ToLower(kv[0])
		switch {
		case sigNames[name]:
			captured = append(captured, kv)
		case keep[name]:
			hdr = append(hdr, kv)
		}
	}

	var sig [][2]string
	switch d.Signing.Kind {
	case plan.SignResigned, plan.SignOriginalBodyNow:
		ts := now.Unix() + int64(d.Signing.TimestampOffsetS)
		h, err := signing.Sign(scheme, o.Secret, o.messageID(ev.ProviderEventID, c.Headers), c.Body, ts) // TAMP-2: original body
		if err != nil {
			return nil, err
		}
		sig = h
	case plan.SignOriginal:
		sig = captured
	case plan.SignNone:
		if scheme == "standard_webhooks_v1" && !o.Secret.IsZero() { // UNS-1 keeps webhook-id and webhook-timestamp
			h, err := signing.Sign(scheme, o.Secret, o.messageID(ev.ProviderEventID, c.Headers), c.Body, now.Unix())
			if err == nil {
				for _, kv := range h {
					if !strings.EqualFold(kv[0], signing.StdSignatureHeader) {
						sig = append(sig, kv)
					}
				}
			}
		}
	}
	// A header the signature sets replaces a captured one of the same name.
	set := map[string]bool{}
	for _, kv := range sig {
		set[strings.ToLower(kv[0])] = true
	}
	out := hdr[:0:0]
	for _, kv := range hdr {
		if !set[strings.ToLower(kv[0])] {
			out = append(out, kv)
		}
	}
	out = append(out, sig...)
	out = append(out,
		[2]string{"Webhooktrap-Run", o.RunID},
		[2]string{"Webhooktrap-Delivery", "D" + itoa(d.N)},
		[2]string{"Webhooktrap-Scenario", string(d.Scenario)},
		[2]string{"Webhooktrap-Dry-Run", "1"},
	)
	return &Request{N: d.N, Method: "POST", Host: o.hostHeader, Path: o.path, Header: out, Body: body}, nil
}

// messageID is the Standard Webhooks message id: the captured webhook-id,
// else the provider event id (DUP-1: kept on duplicates and retries).
func (o *Options) messageID(eventID string, h [][2]string) string {
	if o.Pack.Event.IDHeader != "" {
		if v := plan.Header(h, o.Pack.Event.IDHeader); v != "" {
			return v
		}
	}
	return eventID
}

// faithful checks a built request with the provider's own verifier (SIGN-4):
// valid deliveries pass, Late fails on the timestamp only, Tampered on the
// signature only, Unsigned for a missing header.
func (o *Options) faithful(d plan.Delivery, r *Request, now time.Time) bool {
	if o.Secret.IsZero() || o.Pack.Signing.Scheme == "none" {
		return true // nothing to check without a secret (SIGN-2 sends as captured)
	}
	c := signing.Verify(o.Pack.Signing.Scheme, o.Secret, r.Header, r.Body, now.Unix(), o.Pack.Signing.ToleranceS)
	switch d.Mode {
	case plan.ModeLate:
		return c.HeaderPresent && c.WellFormed && c.SignatureOK && !c.TimestampOK
	case plan.ModeTampered:
		return c.HeaderPresent && c.WellFormed && !c.SignatureOK && c.TimestampOK
	case plan.ModeUnsigned:
		return !c.HeaderPresent
	}
	return c.Valid()
}

func itoa(n int) string {
	if n == 0 {
		return "0"
	}
	var b [20]byte
	i := len(b)
	for n > 0 {
		i--
		b[i] = byte('0' + n%10)
		n /= 10
	}
	return string(b[i:])
}
