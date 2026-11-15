// Package refhandler is the reference Stripe handler of the regression suite
// (ADR-010 D-12): an Express-like handler with a buggy and a fixed mode, used
// as an httptest server. The buggy mode reproduces RD Run #3:
//
//   - it checks the timestamp, so Late is rejected, but a failed signature
//     check is only logged and the request goes on (F1, accepts_tampered);
//   - it doesn't record the event id, so a duplicate or a retry creates a
//     second order (F2, duplicate_changed_state);
//   - its slow external call is intermittent: the deliveries listed in Slow
//     take longer than the budget (F3, over_budget);
//   - it updates subscription status, which the orders.count probe can't see
//     (F4, probe_gap).
//
// The fixed mode stops on a failed check, records the event id before the
// write, and acknowledges before slow work.
package refhandler

import (
	"crypto/hmac"
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"io"
	"net/http"
	"strconv"
	"strings"
	"sync"
	"time"
)

// LatencyHeader carries a simulated latency in ms when Simulate is on, so a
// golden test can use a virtual clock instead of sleeping 11 s.
const LatencyHeader = "X-Refhandler-Latency-Ms"

// Handler is the reference handler.
type Handler struct {
	Fixed  bool
	Secret []byte
	Now    func() time.Time
	// Latency per Webhooktrap-Delivery value ("D10"): the time the handler's
	// external call takes on that delivery. Base applies to every other one.
	Latency map[string]time.Duration
	Base    time.Duration
	// Simulate reports latency in LatencyHeader instead of sleeping.
	Simulate bool

	mu        sync.Mutex
	orders    map[string]int
	processed map[string]bool
	subs      map[string]string
	Logged    []string
}

// New returns a handler with empty tables.
func New(fixed bool, secret []byte, now func() time.Time) *Handler {
	h := &Handler{Fixed: fixed, Secret: secret, Now: now}
	h.Reset()
	return h
}

// Reset empties the handler's tables (the reset hook).
func (h *Handler) Reset() {
	h.mu.Lock()
	defer h.mu.Unlock()
	h.orders, h.processed, h.subs = map[string]int{}, map[string]bool{}, map[string]string{}
}

// Orders returns the order count for an invoice.
func (h *Handler) Orders(invoice string) int {
	h.mu.Lock()
	defer h.mu.Unlock()
	return h.orders[invoice]
}

func (h *Handler) ServeHTTP(w http.ResponseWriter, r *http.Request) {
	w.Header().Set("X-Powered-By", "Express")
	switch {
	case r.URL.Path == "/_debug/orders" && r.Method == http.MethodGet:
		_, _ = io.WriteString(w, strconv.Itoa(h.Orders(r.URL.Query().Get("invoice")))+"\n")
	case r.URL.Path == "/_debug/reset" && r.Method == http.MethodPost:
		h.Reset()
		_, _ = io.WriteString(w, "ok\n")
	case r.URL.Path == "/webhooks/stripe" && r.Method == http.MethodPost:
		h.webhook(w, r)
	default:
		http.NotFound(w, r)
	}
}

// RejectLatency is the simulated time of a request rejected before any work.
const RejectLatency = 41 * time.Millisecond

func (h *Handler) reject(w http.ResponseWriter, body string) {
	if h.Simulate {
		w.Header().Set(LatencyHeader, strconv.FormatInt(RejectLatency.Milliseconds(), 10))
	}
	reply(w, 400, body)
}

func reply(w http.ResponseWriter, status int, body string) {
	w.Header().Set("Content-Type", "application/json; charset=utf-8")
	w.WriteHeader(status)
	_, _ = io.WriteString(w, body)
}

func (h *Handler) webhook(w http.ResponseWriter, r *http.Request) {
	body, err := io.ReadAll(io.LimitReader(r.Body, 1<<20))
	if err != nil {
		h.reject(w, `{"error":"bad body"}`)
		return
	}
	sig := r.Header.Get("Stripe-Signature")
	var t string
	var v1 []string
	for _, part := range strings.Split(sig, ",") {
		k, v, _ := strings.Cut(part, "=")
		switch k {
		case "t":
			t = v
		case "v1":
			v1 = append(v1, v)
		}
	}
	ts, err := strconv.ParseInt(t, 10, 64)
	if sig == "" || err != nil || len(v1) == 0 {
		h.reject(w, `{"error":"No signatures found matching the expected signature for payload"}`)
		return
	}
	if d := h.Now().Unix() - ts; d > 300 || d < -300 {
		h.reject(w, `{"error":"Timestamp outside the tolerance zone"}`)
		return
	}
	m := hmac.New(sha256.New, h.Secret)
	m.Write([]byte(t + "." + string(body)))
	want := m.Sum(nil)
	match := false
	for _, s := range v1 {
		if got, err := hex.DecodeString(s); err == nil && hmac.Equal(got, want) {
			match = true
		}
	}
	if !match {
		if h.Fixed {
			h.reject(w, `{"error":"Invalid signature"}`)
			return
		}
		h.mu.Lock()
		h.Logged = append(h.Logged, "signature mismatch") // BUG: logs and goes on
		h.mu.Unlock()
	}
	var ev struct {
		ID   string `json:"id"`
		Type string `json:"type"`
		Data struct {
			Object struct {
				ID     string `json:"id"`
				Status string `json:"status"`
			} `json:"object"`
		} `json:"data"`
	}
	if err := json.Unmarshal(body, &ev); err != nil {
		h.reject(w, `{"error":"bad json"}`)
		return
	}
	h.mu.Lock()
	if h.Fixed {
		if h.processed[ev.ID] {
			h.mu.Unlock()
			h.wait(w, r)
			reply(w, 200, `{"received":true}`)
			return
		}
		h.processed[ev.ID] = true // record the event id before the write
	}
	switch ev.Type {
	case "invoice.paid":
		h.orders[ev.Data.Object.ID]++ // BUG (buggy mode): no idempotency
	case "customer.subscription.updated":
		h.subs[ev.Data.Object.ID] = ev.Data.Object.Status
	}
	h.mu.Unlock()

	h.wait(w, r)
	reply(w, 200, `{"received":true}`)
}

// wait spends the handler's work time for this delivery.
func (h *Handler) wait(w http.ResponseWriter, r *http.Request) {
	lat := h.Base
	if d, ok := h.Latency[r.Header.Get("Webhooktrap-Delivery")]; ok {
		lat = d
		if h.Fixed && lat > 250*time.Millisecond {
			lat = 250 * time.Millisecond // fixed: acknowledge first, queue the slow call
		}
	}
	if h.Simulate {
		w.Header().Set(LatencyHeader, strconv.FormatInt(lat.Milliseconds(), 10))
	} else if lat > 0 {
		time.Sleep(lat)
	}
}
