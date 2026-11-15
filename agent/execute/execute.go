package execute

import (
	"context"
	"errors"
	"slices"
	"strconv"
	"sync"
	"time"

	"github.com/jiordiviera/webhooktrap/agent/guard"
	"github.com/jiordiviera/webhooktrap/agent/observe"
	"github.com/jiordiviera/webhooktrap/agent/pack"
	"github.com/jiordiviera/webhooktrap/agent/plan"
	"github.com/jiordiviera/webhooktrap/agent/signing"
)

// Timing constants (PRB-6, PRB-7, PRB-10, CAP-2).
const (
	SettlePoll       = 250 * time.Millisecond
	SettleExtra      = 5 * time.Second
	AsyncCheckAfter  = 1000 * time.Millisecond
	StabilityGap     = 500 * time.Millisecond
	MaxRunDuration   = 15 * time.Minute
	probeFailureStop = 3
	lostAfter        = 3 // RUN-5
)

// Options is everything one run needs. Secrets live only in Secret.
type Options struct {
	RunID     string
	Plan      *plan.Plan
	Captures  []plan.Capture
	Pack      *pack.Pack
	Secret    signing.Secret
	Target    *guard.Target
	Transport Transport
	Clock     Clock
	Scheduler Scheduler
	Probe     Prober   // nil: Level 1 only
	Reset     Resetter // nil: no reset hook
	Settle    time.Duration
	// MaxDuration defaults to CAP-2's 15 minutes.
	MaxDuration time.Duration
	// OnDelivery is called after every completed delivery (live output).
	OnDelivery func(plan.Delivery, observe.Delivery)
	// OnProbeError receives local-only probe and reset errors (PRB-5).
	OnProbeError func(error)

	captures   map[string]plan.Capture
	hostHeader string
	path       string
}

// ErrNoPlan is returned for incomplete options.
var ErrNoPlan = errors.New("execute: plan, pack, transport, clock and scheduler are required")

// Run sends the plan and records observations. It never judges.
func Run(ctx context.Context, o Options) (*observe.Run, error) {
	if o.Plan == nil || o.Pack == nil || o.Transport == nil || o.Clock == nil || o.Scheduler == nil {
		return nil, ErrNoPlan
	}
	if o.MaxDuration <= 0 {
		o.MaxDuration = MaxRunDuration
	}
	o.captures = map[string]plan.Capture{}
	for _, c := range o.Captures {
		o.captures[c.Ref] = c
	}
	o.hostHeader, o.path = "localhost", "/"
	if o.Target != nil {
		o.hostHeader = o.Target.Host
		if isIPv6Literal(o.Target.Host) {
			o.hostHeader = "[" + o.Target.Host + "]"
		}
		if (o.Target.Scheme == "http" && o.Target.Port != 80) || (o.Target.Scheme == "https" && o.Target.Port != 443) {
			o.hostHeader += ":" + strconv.Itoa(o.Target.Port)
		}
		o.path = o.Target.Path
	}
	e := &runner{o: &o, start: o.Clock.Mono()}
	e.r = &observe.Run{ResetConfigured: o.Reset != nil}
	if o.Probe != nil {
		e.r.ProbeNames = o.Probe.Names()
	}
	for _, d := range o.Plan.Deliveries {
		e.r.Deliveries = append(e.r.Deliveries, observe.Delivery{N: d.N})
	}
	for _, s := range o.Plan.Scenarios {
		e.r.Scenarios = append(e.r.Scenarios, observe.Scenario{Name: string(s.Name)})
	}
	defer func() { e.r.DurationMS = ms(o.Clock.Mono() - e.start) }()

	if o.Plan.NeedsSecret {
		e.stop(observe.CouldntStart, "needs_secret")
		return e.r, nil
	}
	e.preflightSign()
	e.run(ctx)
	return e.r, nil
}

type runner struct {
	o           *Options
	r           *observe.Run
	start       time.Duration
	probeOff    bool
	probeFails  int
	unreachable int
	stopped     bool
	last        []string // last probe values read
}

func (e *runner) stop(status, reason string) {
	if !e.stopped {
		e.stopped = true
		e.r.Stop = &observe.Stop{Status: status, Reason: reason}
	}
}

func (e *runner) scenarioObs(name plan.Scenario) *observe.Scenario {
	for i := range e.r.Scenarios {
		if e.r.Scenarios[i].Name == string(name) {
			return &e.r.Scenarios[i]
		}
	}
	return nil
}

// preflightSign builds every request at the current wall time and checks it
// with the provider's verifier (SIGN-4). A scenario whose request isn't
// faithful is n/a and is not sent.
func (e *runner) preflightSign() {
	now := e.o.Clock.Wall()
	for _, d := range e.o.Plan.Deliveries {
		req, err := e.o.buildRequest(d, now)
		if err != nil || !e.o.faithful(d, req, now) {
			if s := e.scenarioObs(d.Scenario); s != nil && s.NA == "" {
				s.NA, s.NAText = "unfaithful_request", "couldn't build a faithful request"
			}
		}
	}
}

func (e *runner) run(ctx context.Context) {
	first := true
	for _, sp := range e.o.Plan.Scenarios {
		if sp.NA != "" || len(sp.Deliveries) == 0 {
			continue
		}
		if s := e.scenarioObs(sp.Name); s != nil && s.NA != "" {
			continue
		}
		if e.overTime() {
			return
		}
		if e.o.Reset != nil { // PRB-9: before every scenario, Once included
			if err := e.o.Reset.Reset(ctx); err != nil {
				if ctx.Err() != nil {
					e.stop(observe.Aborted, "user_abort")
					return
				}
				e.report(err)
				if first {
					e.stop(observe.CouldntStart, "reset_failed")
				} else {
					e.stop(observe.Aborted, "reset_failed")
				}
				return
			}
		}
		if first && e.o.Probe != nil { // PRB-8, PRB-10
			a, okA := e.read(ctx, 0)
			if err := e.o.Scheduler.Sleep(ctx, StabilityGap); err != nil {
				e.stop(observe.Aborted, "user_abort")
				return
			}
			b, okB := e.read(ctx, 0)
			if okA && okB && !slices.Equal(a, b) {
				e.stop(observe.CouldntStart, "probe_unstable")
				return
			}
		}
		first = false
		var lastReading []string
		if sp.Name == plan.Burst {
			lastReading = e.burst(ctx, sp)
		} else {
			for _, n := range sp.Deliveries {
				lastReading = e.sequential(ctx, e.o.Plan.Deliveries[n-1])
				if e.stopped {
					return
				}
			}
		}
		if e.stopped {
			return
		}
		// PRB-7: one more read 1 s after the scenario's last reading.
		if e.o.Probe != nil && !e.probeOff && lastReading != nil {
			if err := e.o.Scheduler.Sleep(ctx, AsyncCheckAfter); err != nil {
				e.stop(observe.Aborted, "user_abort")
				return
			}
			if v, ok := e.read(ctx, 0); ok && !slices.Equal(v, lastReading) {
				e.scenarioObs(sp.Name).AsyncChanged = true
			}
		}
	}
}

func (e *runner) overTime() bool {
	if e.o.Clock.Mono()-e.start > e.o.MaxDuration {
		e.stop(observe.Aborted, "time_limit")
		return true
	}
	return false
}

func (e *runner) sleep(ctx context.Context, ms int) bool {
	if err := e.o.Scheduler.Sleep(ctx, time.Duration(ms)*time.Millisecond); err != nil {
		e.stop(observe.Aborted, "user_abort")
		return false
	}
	return true
}

// sequential sends one delivery and returns the after-reading.
func (e *runner) sequential(ctx context.Context, d plan.Delivery) []string {
	if !e.sleep(ctx, d.GapMS) || e.overTime() {
		return nil
	}
	od := e.r.Delivery(d.N)
	var st *observe.State
	if e.o.Probe != nil {
		st = &observe.State{}
		if v, ok := e.read(ctx, d.N); ok {
			st.Before = v
		} else {
			st.Failed = true
		}
	}
	req, err := e.o.buildRequest(d, e.o.Clock.Wall())
	if err != nil {
		e.stop(observe.Aborted, "agent_lost")
		return nil
	}
	conn, kind := e.open(ctx)
	if ctx.Err() != nil {
		e.stop(observe.Aborted, "user_abort")
		return nil
	}
	od.OffsetMS = ms(e.o.Clock.Mono() - e.start)
	if conn == nil {
		od.Sent, od.Completed, od.Transport = true, true, kind
		e.unreachable++
		if e.isFirstOnce(d) {
			e.stop(observe.CouldntStart, "target_unreachable") // ONCE-6
		} else if e.unreachable >= lostAfter {
			e.stop(observe.Aborted, "target_lost") // RUN-5
		}
		e.notify(d, *od)
		return nil
	}
	e.unreachable = 0
	od.Sent = true
	res := conn.Do(ctx, req)
	_ = conn.Close()
	if ctx.Err() != nil {
		e.stop(observe.Aborted, "user_abort") // sent, no result
		return nil
	}
	e.record(od, res)
	if e.o.Probe != nil && !st.Failed {
		v, unsettled, ok := e.readSettled(ctx, d.N)
		if ok {
			st.After, st.Unsettled = v, unsettled
		} else {
			st.Failed = true
		}
	}
	od.State = st
	od.Completed = true
	e.notify(d, *od)
	if e.isFirstOnce(d) && res.Responded && res.Status >= 300 && res.Status < 500 { // ONCE-4
		e.stop(observe.CouldntStart, "baseline_rejected")
		e.r.Stop.HTTPStatus = res.Status
	}
	if st != nil && !st.Failed {
		return st.After
	}
	return nil
}

func (e *runner) isFirstOnce(d plan.Delivery) bool {
	return d.Scenario == plan.Once && d.N == 1
}

// burst opens every connection, then releases all requests together (BURST-1, BURST-2).
func (e *runner) burst(ctx context.Context, sp plan.ScenarioPlan) []string {
	ds := make([]plan.Delivery, len(sp.Deliveries))
	for i, n := range sp.Deliveries {
		ds[i] = e.o.Plan.Deliveries[n-1]
	}
	if !e.sleep(ctx, ds[0].GapMS) || e.overTime() {
		return nil
	}
	var st *observe.State
	if e.o.Probe != nil {
		st = &observe.State{}
		if v, ok := e.read(ctx, ds[0].N); ok {
			st.Before = v
		} else {
			st.Failed = true
		}
	}
	now := e.o.Clock.Wall()
	reqs := make([]*Request, len(ds))
	conns := make([]Conn, len(ds))
	kinds := make([]string, len(ds))
	for i, d := range ds {
		r, err := e.o.buildRequest(d, now)
		if err != nil {
			e.stop(observe.Aborted, "agent_lost")
			return nil
		}
		reqs[i] = r
		conns[i], kinds[i] = e.open(ctx)
		if ctx.Err() != nil {
			e.closeAll(conns)
			e.stop(observe.Aborted, "user_abort")
			return nil
		}
	}
	results := make([]Result, len(ds))
	release := make(chan struct{})
	var wg sync.WaitGroup
	for i := range ds {
		if conns[i] == nil {
			continue
		}
		wg.Add(1)
		go func(i int) {
			defer wg.Done()
			<-release
			results[i] = conns[i].Do(ctx, reqs[i])
		}(i)
	}
	close(release)
	wg.Wait()
	e.closeAll(conns)
	if ctx.Err() != nil {
		for i, d := range ds {
			if conns[i] != nil {
				e.r.Delivery(d.N).Sent = true
			}
		}
		e.stop(observe.Aborted, "user_abort")
		return nil
	}
	var lo, hi time.Duration
	seen := false
	for i, d := range ds {
		od := e.r.Delivery(d.N)
		od.Sent, od.Completed = true, true
		if conns[i] == nil {
			od.Transport = kinds[i]
			od.OffsetMS = ms(e.o.Clock.Mono() - e.start)
			e.unreachable++
			continue
		}
		e.unreachable = 0
		e.record(od, results[i])
		od.OffsetMS = ms(results[i].FirstByteAt - e.start)
		if !seen || results[i].FirstByteAt < lo {
			lo = results[i].FirstByteAt
		}
		if !seen || results[i].FirstByteAt > hi {
			hi = results[i].FirstByteAt
		}
		seen = true
	}
	so := e.scenarioObs(sp.Name)
	if seen {
		spread := ms(hi - lo)
		so.SpreadMS = &spread
	}
	if st != nil && !st.Failed {
		v, unsettled, ok := e.readSettled(ctx, ds[len(ds)-1].N)
		if ok {
			st.After, st.Unsettled = v, unsettled
		} else {
			st.Failed = true
		}
	}
	so.Burst = st
	for _, d := range ds {
		e.notify(d, *e.r.Delivery(d.N))
	}
	if e.unreachable >= lostAfter {
		e.stop(observe.Aborted, "target_lost")
	}
	if st != nil && !st.Failed {
		return st.After
	}
	return nil
}

func (e *runner) closeAll(cs []Conn) {
	for _, c := range cs {
		if c != nil {
			_ = c.Close()
		}
	}
}

// open connects, retrying a refused connect every 500 ms for up to 5 s (TGT-5).
func (e *runner) open(ctx context.Context) (Conn, string) {
	deadline := e.o.Clock.Mono() + connectRetryFor
	for {
		c, err := e.o.Transport.Open(ctx)
		if err == nil {
			return c, ""
		}
		var ue *UnreachableError
		kind := observe.TransportRefused
		if errors.As(err, &ue) {
			kind = ue.Kind
		}
		if kind != observe.TransportRefused || e.o.Clock.Mono() >= deadline {
			return nil, kind
		}
		if e.o.Scheduler.Sleep(ctx, connectRetry) != nil {
			return nil, kind
		}
	}
}

func (e *runner) record(od *observe.Delivery, res Result) {
	od.Transport = res.Transport
	od.Responded = res.Responded
	od.Framework = res.Framework
	if res.Responded {
		od.Status = res.Status
		od.LatencyMS = ms(res.Latency)
		rr := res.Response
		od.Response = &rr
	}
}

// read takes one probe reading and tracks PRB-5's consecutive failures.
func (e *runner) read(ctx context.Context, n int) ([]string, bool) {
	if e.o.Probe == nil || e.probeOff {
		return nil, false
	}
	v, err := e.o.Probe.Read(ctx)
	if err != nil {
		e.report(err)
		e.probeFails++
		if e.probeFails >= probeFailureStop {
			e.probeOff = true
			e.r.ProbeOffAfter = n
		}
		return nil, false
	}
	e.probeFails = 0
	e.last = v
	return v, true
}

// readSettled applies PRB-6.
func (e *runner) readSettled(ctx context.Context, n int) (v []string, unsettled, ok bool) {
	v, ok = e.read(ctx, n)
	if !ok || e.o.Settle <= 0 {
		return v, false, ok
	}
	begin := e.o.Clock.Mono()
	stable := begin
	for {
		if e.o.Clock.Mono()-stable >= e.o.Settle {
			return v, false, true
		}
		if e.o.Clock.Mono()-begin >= e.o.Settle+SettleExtra {
			return v, true, true
		}
		if e.o.Scheduler.Sleep(ctx, SettlePoll) != nil {
			return v, true, true
		}
		nv, ok := e.read(ctx, n)
		if !ok {
			return nil, false, false
		}
		if !slices.Equal(nv, v) {
			v, stable = nv, e.o.Clock.Mono()
		}
	}
}

func (e *runner) report(err error) {
	if e.o.OnProbeError != nil {
		e.o.OnProbeError(err)
	}
}

func (e *runner) notify(d plan.Delivery, od observe.Delivery) {
	if e.o.OnDelivery != nil {
		e.o.OnDelivery(d, od)
	}
}

func ms(d time.Duration) int64 { return int64(d / time.Millisecond) }

func isIPv6Literal(h string) bool {
	for i := 0; i < len(h); i++ {
		if h[i] == ':' {
			return true
		}
	}
	return false
}
