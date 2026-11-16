package agentapi

import "encoding/json"

// Proto is the protocol major this build speaks (ADR-010 §8).
const Proto = 1

// PairRequest is POST /agent/v1/pair (contracts §5.1).
type PairRequest struct {
	Proto     int    `json:"proto"`
	Code      string `json:"code"`
	PublicKey string `json:"public_key"`
	Name      string `json:"name"`
	Version   string `json:"version"`
	OS        string `json:"os"`
	Arch      string `json:"arch"`
	ProtoMin  int    `json:"proto_min"`
	ProtoMax  int    `json:"proto_max"`
}

// Workspace is the workspace object of pair and hello responses.
type Workspace struct {
	Label string `json:"label"`
	Guest bool   `json:"guest"`
}

// ServerInfo is pair's informational server object.
type ServerInfo struct {
	Origin          string `json:"origin"`
	Name            string `json:"name"`
	Kind            string `json:"kind"`
	MinAgentVersion string `json:"min_agent_version"`
}

// Replaces names the agent a pairing code replaces (AC-3): the same shape as
// the pairing.completed broadcast's replaces object.
type Replaces struct {
	AgentID        string `json:"agent_id"`
	Name           string `json:"name"`
	KeyFingerprint string `json:"key_fingerprint"`
}

// PairResponse is the 201 body of pair. Name is the name the server kept
// (suffixed on a collision, or the replaced agent's name; AC-3). Replaces is
// optional: set when the code was issued to replace an agent.
type PairResponse struct {
	AgentID            string     `json:"agent_id"`
	Name               string     `json:"name"`
	Replaces           *Replaces  `json:"replaces"`
	Workspace          Workspace  `json:"workspace"`
	Server             ServerInfo `json:"server"`
	ProbeDisplayPolicy string     `json:"probe_display_policy"`
	Guest              bool       `json:"guest"`
	NeedsReconfirm     bool       `json:"needs_reconfirm"`
}

type challengeRequest struct {
	Proto   int    `json:"proto"`
	AgentID string `json:"agent_id"`
}

type challengeResponse struct {
	Nonce     string `json:"nonce"`
	ExpiresAt string `json:"expires_at"`
}

type tokenRequest struct {
	Proto     int    `json:"proto"`
	AgentID   string `json:"agent_id"`
	Nonce     string `json:"nonce"`
	Signature string `json:"signature"`
}

// Broadcast is the optional realtime nudge channel (not used by this build).
type Broadcast struct {
	Host         string `json:"host"`
	Key          string `json:"key"`
	AuthEndpoint string `json:"auth_endpoint"`
}

type tokenResponse struct {
	AccessToken string    `json:"access_token"`
	ExpiresAt   string    `json:"expires_at"`
	Channel     string    `json:"channel"`
	Broadcast   Broadcast `json:"broadcast"`
}

type protoOnly struct {
	Proto int `json:"proto"`
}

// SecretName is a hello mirror entry: a name, never a value (S-8, PO-D).
type SecretName struct {
	Provider string `json:"provider"`
	EnvName  string `json:"env_name"`
}

// TargetMirror is a declared target as reported in hello.
type TargetMirror struct {
	Scheme   string `json:"scheme"`
	Host     string `json:"host"`
	Port     int    `json:"port"`
	Kind     string `json:"kind"` // local | allowed
	Declared bool   `json:"declared"`
	Approved bool   `json:"approved"`
}

// Hello is POST /agent/v1/hello. The object is closed on the server; this
// struct is its whole shape.
type Hello struct {
	Proto           int            `json:"proto"`
	Version         string         `json:"version"`
	ProtoMin        int            `json:"proto_min"`
	ProtoMax        int            `json:"proto_max"`
	Secrets         []SecretName   `json:"secrets"`
	Targets         []TargetMirror `json:"targets"`
	ProbeConfigHash *string        `json:"probe_config_hash"`
	BusyRunID       *string        `json:"busy_run_id"`
}

// ServerKindHosted is the server kind of the hosted service (pair's
// server.kind): a plain http:// origin is refused for it.
const ServerKindHosted = "hosted"

// HelloServer is hello's optional server object. Only kind is read: the
// same field as pair's server.kind ("hosted" | "selfhost"), absent from
// servers that don't send it yet.
type HelloServer struct {
	Kind string `json:"kind"`
}

// HelloResponse is hello's 200 body.
type HelloResponse struct {
	Server                 *HelloServer `json:"server"`
	Guest                  bool         `json:"guest"`
	NeedsReconfirm         bool         `json:"needs_reconfirm"`
	Workspace              Workspace    `json:"workspace"`
	ProbeDisplayPolicy     string       `json:"probe_display_policy"`
	MinAgentVersion        string       `json:"min_agent_version"`
	Deprecation            *string      `json:"deprecation"`
	PendingJobs            int          `json:"pending_jobs"`
	SignatureChecksPending int          `json:"signature_checks_pending"`
}

// Requester names who started a remote run.
type Requester struct {
	WorkspaceLabel string `json:"workspace_label"`
}

// SelectionRef is one capture of a run job, addressed by id and content.
type SelectionRef struct {
	CaptureID  string `json:"capture_id"`
	Seq        int    `json:"seq"`
	BodySHA256 string `json:"body_sha256"`
}

// PackRef names a pack version.
type PackRef struct {
	ID      string `json:"id"`
	Version string `json:"version"`
}

// Job is a jobs/next body (contracts §5.4): kind run or verify.
type Job struct {
	JobID         string         `json:"job_id"`
	Kind          string         `json:"kind"`
	RunID         string         `json:"run_id"`
	RunNumber     int            `json:"run_number"`
	Remote        bool           `json:"remote"`
	Requester     Requester      `json:"requester"`
	ProjectKey    string         `json:"project_key"`
	Selection     []SelectionRef `json:"selection"`
	Scenarios     []string       `json:"scenarios"`
	TargetRef     string         `json:"target_ref"`
	Pack          PackRef        `json:"pack"`
	FailOn        string         `json:"fail_on"`
	Seed          string         `json:"seed"`
	DeliveryCount *int           `json:"delivery_count"` // null until the server can plan
	ExpiresAt     string         `json:"expires_at"`
	Captures      []SelectionRef `json:"captures"` // verify jobs

	// Runner jobs only (contracts §6): the requested guard mode, which can
	// only narrow the runner's local ceiling (RN-3), its allow-list entries
	// for B′, and the per-request limits.
	Guard     string           `json:"guard,omitempty"`
	Allowlist []AllowlistEntry `json:"allowlist,omitempty"`
	Limits    *JobLimits       `json:"limits,omitempty"`
}

// AllowlistEntry is one B′ allow-list entry of a runner job: a host name or
// a CIDR, and an inclusive port range.
type AllowlistEntry struct {
	Host     string `json:"host,omitempty"`
	CIDR     string `json:"cidr,omitempty"`
	PortFrom int    `json:"port_from"`
	PortTo   int    `json:"port_to"`
}

// JobLimits are a runner job's per-request limits; the engine's own limits
// (30 s, 1 MiB) are the ceiling.
type JobLimits struct {
	RequestTimeoutMS int   `json:"request_timeout_ms"`
	ResponseCapBytes int64 `json:"response_cap_bytes"`
}

// RunnerHello is POST /runner/v1/hello (contracts §6): the runner's
// version, which must equal the app's.
type RunnerHello struct {
	Proto    int    `json:"proto"`
	Version  string `json:"version"`
	ProtoMin int    `json:"proto_min"`
	ProtoMax int    `json:"proto_max"`
}

// CaptureRequest is the capture fetch body: the headers in received order
// and the raw body, base64-encoded.
type CaptureRequest struct {
	CaptureID       string      `json:"capture_id"`
	Seq             int         `json:"seq"`
	Headers         [][2]string `json:"headers"`
	RedactedHeaders []string    `json:"redacted_headers"`
	ContentType     *string     `json:"content_type"`
	BodyBytes       int         `json:"body_bytes"`
	BodyBase64      string      `json:"body_base64"`
	BodySHA256      string      `json:"body_sha256"`
}

// Decline reasons (contracts §5.4).
const (
	DeclineTargetNotAllowed  = "target_not_allowed"
	DeclineNeedsConfirmation = "needs_confirmation"
	DeclineNeedsReconfirm    = "needs_reconfirm"
	DeclineBusy              = "busy"
	DeclineAgentTooOld       = "agent_too_old"
)

// Abort reasons (abort.request.schema.json).
const (
	AbortCaptureVerificationFailed = "capture_verification_failed"
	AbortTargetUnreachable         = "target_unreachable"
	AbortInterrupted               = "interrupted"
	AbortInternalError             = "internal_error"
	// Runner only (contracts §6, RN-3): the job asks for more than the
	// runner's local ceiling allows.
	AbortGuardExceedsCeiling = "guard_exceeds_ceiling"
	AbortTargetNotAllowed    = "target_not_allowed"
)

// declineRequest is the body of decline and abort: proto and a reason.
type declineRequest struct {
	Proto  int    `json:"proto"`
	Reason string `json:"reason"`
}

// Observations is one POST /agent/v1/runs/{runId}/observations batch (§5.5).
// The plan fields are set on seq 1 only.
type Observations struct {
	Proto               int               `json:"proto"`
	Seq                 int               `json:"seq"`
	Plan                json.RawMessage   `json:"plan,omitempty"`
	PlanHash            string            `json:"plan_hash,omitempty"`
	DeliveryCount       *int              `json:"delivery_count,omitempty"`
	ProbeEntries        json.RawMessage   `json:"probe_entries,omitempty"`
	LabelBits           int               `json:"label_bits,omitempty"`
	ProbeDisplayPolicy  string            `json:"probe_display_policy,omitempty"`
	Deliveries          []json.RawMessage `json:"deliveries"`
	Scenarios           []json.RawMessage `json:"scenarios"`
	FindingsProvisional []json.RawMessage `json:"findings_provisional"`
}
