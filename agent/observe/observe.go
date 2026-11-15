// Package observe holds what execute recorded and judge reads: one record per
// planned delivery and scenario. Pure data, no behaviour, so judge never
// depends on execute (ADR-010 D-1).
package observe

// Run status values set by execute when the plan couldn't finish (RUN-1 rows 1–2).
const (
	CouldntStart = "couldnt_start"
	Aborted      = "aborted"
)

// Transport outcomes (SJ §1, §13).
const (
	TransportTimeout = "timeout"
	TransportReset   = "reset"
	// Unreachable kinds: no connection was established (never the handler's fault).
	TransportRefused = "refused"
	TransportDNS     = "dns"
	TransportTLS     = "tls"
)

// Unreachable reports whether a transport outcome means no connection.
func Unreachable(t string) bool {
	return t == TransportRefused || t == TransportDNS || t == TransportTLS
}

// Response is what is kept of a response body (never the bytes).
type Response struct {
	ContentType string `json:"content_type"`
	BodySHA256  string `json:"body_sha256"`
	BodyBytes   int64  `json:"body_bytes"`
}

// State is the probe reading of a delivery. Values are raw (local only), one
// per probe entry in configuration order, trailing whitespace trimmed (PRB-2).
type State struct {
	Before    []string `json:"before"`
	After     []string `json:"after"`
	Failed    bool     `json:"failed"`
	Unsettled bool     `json:"unsettled"`
}

// Delivery is the observation of one planned delivery.
type Delivery struct {
	N         int       `json:"n"`
	Sent      bool      `json:"sent"`
	Completed bool      `json:"completed"`
	OffsetMS  int64     `json:"offset_ms"`
	Status    int       `json:"status,omitempty"`
	Transport string    `json:"transport,omitempty"`
	LatencyMS int64     `json:"latency_ms"`
	Responded bool      `json:"responded"`
	Response  *Response `json:"response,omitempty"`
	// Framework is the FND-9 detection from this response's headers.
	Framework string `json:"framework,omitempty"`
	State     *State `json:"state,omitempty"`
}

// Scenario is what execute recorded per scenario beyond its deliveries.
type Scenario struct {
	Name string `json:"name"`
	// NA overrides the plan when SIGN-4 refused to build a request.
	NA     string `json:"na,omitempty"`
	NAText string `json:"na_text,omitempty"`
	// AsyncChanged: the PRB-7 re-read differed from the last reading.
	AsyncChanged bool `json:"async_changed,omitempty"`
	// Burst only (BURST-2, BURST-4).
	SpreadMS *int64 `json:"spread_ms,omitempty"`
	Burst    *State `json:"burst_state,omitempty"`
}

// Stop is set when the run ended before the plan finished.
type Stop struct {
	Status string `json:"status"` // couldnt_start | aborted
	Reason string `json:"reason"`
	// HTTPStatus is D1's status for baseline_rejected (ONCE-4 copy).
	HTTPStatus int `json:"http_status,omitempty"`
}

// Run is everything execute observed.
type Run struct {
	ProbeNames      []string   `json:"probe_names"`
	ResetConfigured bool       `json:"reset_configured"`
	Deliveries      []Delivery `json:"deliveries"`
	Scenarios       []Scenario `json:"scenarios"`
	Stop            *Stop      `json:"stop,omitempty"`
	// ProbeOffAfter is the D-number after which the probe was turned off
	// (PRB-5: 3 consecutive failures), 0 if never.
	ProbeOffAfter int   `json:"probe_off_after,omitempty"`
	DurationMS    int64 `json:"duration_ms"`
}

// Delivery returns the observation for D-number n.
func (r *Run) Delivery(n int) *Delivery {
	if n >= 1 && n <= len(r.Deliveries) {
		return &r.Deliveries[n-1]
	}
	return nil
}

// Scenario returns the observation for a scenario name, or an empty one.
func (r *Run) Scenario(name string) Scenario {
	for _, s := range r.Scenarios {
		if s.Name == name {
			return s
		}
	}
	return Scenario{Name: name}
}
