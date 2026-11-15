package execute_test

import (
	"context"
	"io"
	"net"
	"net/http"
	"net/http/httptest"
	"sync"
	"testing"
	"time"

	"github.com/jiordiviera/webhooktrap/agent/execute"
	"github.com/jiordiviera/webhooktrap/agent/guard"
	"github.com/jiordiviera/webhooktrap/agent/internal/packdata"
	"github.com/jiordiviera/webhooktrap/agent/observe"
	"github.com/jiordiviera/webhooktrap/agent/pack"
	"github.com/jiordiviera/webhooktrap/agent/plan"
	"github.com/jiordiviera/webhooktrap/agent/signing"
)

const body = `{"id":"evt_1","object":"event","type":"invoice.paid","data":{"object":{"id":"in_1","object":"invoice","amount_paid":2400}}}`

func setup(t *testing.T, events int, sc ...plan.Scenario) (*plan.Plan, *pack.Pack, []plan.Capture) {
	t.Helper()
	pk, err := packdata.Source().Load("stripe", "")
	if err != nil {
		t.Fatal(err)
	}
	var caps []plan.Capture
	for i := 0; i < events; i++ {
		caps = append(caps, plan.Capture{Ref: string(rune('a' + i)), Provider: "stripe", CaptureOrder: i + 1, Body: []byte(body),
			Headers: [][2]string{{"Content-Type", "application/json"}, {"Stripe-Signature", "t=1,v1=00"}, {"X-Forwarded-For", "1.2.3.4"}}})
	}
	pl, err := plan.Build(plan.Input{Captures: caps, Scenarios: sc, Pack: pk, Seed: 1, HasSecret: true, Budget: plan.Budget{MS: 10000}})
	if err != nil {
		t.Fatal(err)
	}
	return pl, pk, caps
}

func target(t *testing.T, url string) *guard.Target {
	t.Helper()
	tg, err := guard.New(nil).Check(context.Background(), url)
	if err != nil {
		t.Fatal(err)
	}
	return tg
}

// TestRealHTTPBurst runs every scenario against a real loopback server with
// a real clock; go test -race covers the burst goroutines.
func TestRealHTTPBurst(t *testing.T) {
	secret := signing.NewSecret("STRIPE_WEBHOOK_SECRET", []byte("whsec_test"))
	var mu sync.Mutex
	var seen []http.Header
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		b, _ := io.ReadAll(r.Body)
		mu.Lock()
		seen = append(seen, r.Header.Clone())
		mu.Unlock()
		c := signing.Verify("stripe_v1", secret, [][2]string{{"Stripe-Signature", r.Header.Get("Stripe-Signature")}}, b, time.Now().Unix(), 300)
		if !c.Valid() {
			w.WriteHeader(400)
			return
		}
		w.Header().Set("X-Powered-By", "Express")
		_, _ = io.WriteString(w, `{"received":true}`)
	}))
	defer srv.Close()
	pl, pk, caps := setup(t, 3, plan.Order...)
	tg := target(t, srv.URL+"/webhooks/stripe")
	clock := execute.NewSystemClock()
	obs, err := execute.Run(context.Background(), execute.Options{RunID: "run_x", Plan: pl, Captures: caps, Pack: pk, Secret: secret, Target: tg,
		Transport: &execute.HTTPTransport{Target: tg, Clock: clock}, Clock: clock, Scheduler: noWait{}})
	if err != nil || obs.Stop != nil {
		t.Fatalf("%v %+v", err, obs.Stop)
	}
	for _, d := range obs.Deliveries {
		pd := pl.Deliveries[d.N-1]
		invalid := pd.Mode == plan.ModeLate || pd.Mode == plan.ModeTampered || pd.Mode == plan.ModeUnsigned
		if !d.Completed || (invalid != (d.Status == 400)) || d.Framework != "express" && d.Status == 200 {
			t.Errorf("D%d %s: %+v", d.N, pd.Mode, d)
		}
	}
	for _, h := range seen {
		if h.Get("X-Forwarded-For") != "" || h.Get("Webhooktrap-Dry-Run") != "1" || h.Get("Webhooktrap-Run") != "run_x" {
			t.Fatalf("re-send allow-list broken (TM-51, SIGN-3): %v", h)
		}
	}
	if s := obs.Scenario("burst"); s.SpreadMS == nil || *s.SpreadMS > 20 {
		t.Errorf("burst spread %v", s.SpreadMS)
	}
}

type noWait struct{}

func (noWait) Sleep(ctx context.Context, _ time.Duration) error { return ctx.Err() }

// refused is a transport whose connections are always refused.
type refused struct{ opens int }

func (r *refused) Open(context.Context) (execute.Conn, error) {
	r.opens++
	return nil, &execute.UnreachableError{Kind: observe.TransportRefused}
}

func TestUnreachableStart(t *testing.T) {
	pl, pk, caps := setup(t, 1, plan.Twice)
	clock := execute.NewVirtualClock(time.Unix(1794406143, 0))
	tr := &refused{}
	obs, _ := execute.Run(context.Background(), execute.Options{Plan: pl, Captures: caps, Pack: pk,
		Secret: signing.NewSecret("S", []byte("whsec_x")), Transport: tr, Clock: clock, Scheduler: clock})
	if obs.Stop == nil || obs.Stop.Reason != "target_unreachable" {
		t.Fatalf("ONCE-6: %+v", obs.Stop)
	}
	// TGT-5: connect retried every 500 ms for 5 s → 11 attempts.
	if tr.opens != 11 {
		t.Fatalf("connect attempts %d, want 11", tr.opens)
	}
}

type lostAfterFirst struct {
	inner execute.Transport
	n     int
}

func (l *lostAfterFirst) Open(ctx context.Context) (execute.Conn, error) {
	l.n++
	if l.n > 1 {
		return nil, &execute.UnreachableError{Kind: observe.TransportRefused}
	}
	return l.inner.Open(ctx)
}

func TestTargetLost(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {}))
	defer srv.Close()
	pl, pk, caps := setup(t, 1, plan.Twice, plan.Retry, plan.SlowBudget)
	tg := target(t, srv.URL)
	clock := execute.NewVirtualClock(time.Now())
	obs, _ := execute.Run(context.Background(), execute.Options{Plan: pl, Captures: caps, Pack: pk,
		Secret: signing.NewSecret("S", []byte("whsec_x")), Target: tg,
		Transport: &lostAfterFirst{inner: &execute.HTTPTransport{Target: tg, Clock: clock}}, Clock: clock, Scheduler: clock})
	if obs.Stop == nil || obs.Stop.Reason != "target_lost" || obs.Deliveries[4].Sent {
		t.Fatalf("RUN-5: %+v", obs.Stop)
	}
}

func TestBaselineRejectedStops(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) { w.WriteHeader(401) }))
	defer srv.Close()
	pl, pk, caps := setup(t, 1, plan.Twice)
	tg := target(t, srv.URL)
	clock := execute.NewVirtualClock(time.Now())
	obs, _ := execute.Run(context.Background(), execute.Options{Plan: pl, Captures: caps, Pack: pk,
		Secret: signing.NewSecret("S", []byte("whsec_x")), Target: tg,
		Transport: &execute.HTTPTransport{Target: tg, Clock: clock}, Clock: clock, Scheduler: clock})
	if obs.Stop == nil || obs.Stop.Reason != "baseline_rejected" || obs.Stop.HTTPStatus != 401 || obs.Deliveries[1].Sent {
		t.Fatalf("ONCE-4: %+v", obs.Stop)
	}
}

func TestTimeoutIsTransportFailure(t *testing.T) {
	ln, err := net.Listen("tcp", "127.0.0.1:0")
	if err != nil {
		t.Fatal(err)
	}
	defer ln.Close()
	go func() { // accept, read, then close without a status line
		c, err := ln.Accept()
		if err == nil {
			buf := make([]byte, 4096)
			_, _ = c.Read(buf)
			_ = c.Close()
		}
	}()
	tg := target(t, "http://"+ln.Addr().String()+"/")
	tr := &execute.HTTPTransport{Target: tg, Clock: execute.NewSystemClock()}
	c, err := tr.Open(context.Background())
	if err != nil {
		t.Fatal(err)
	}
	res := c.Do(context.Background(), &execute.Request{Method: "POST", Host: "x", Path: "/", Body: []byte("{}")})
	if res.Responded || res.Transport != observe.TransportReset {
		t.Fatalf("%+v", res)
	}
}

func TestSafeEnvStripsSecrets(t *testing.T) {
	env := execute.SafeEnv([]string{"PATH=/bin", "STRIPE_WEBHOOK_SECRET=x", "WEBHOOKTRAP_TOKEN=y"}, []string{"STRIPE_WEBHOOK_SECRET"})
	if len(env) != 1 || env[0] != "PATH=/bin" {
		t.Fatalf("%v", env)
	}
}
