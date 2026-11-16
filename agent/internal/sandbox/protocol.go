// Package sandbox is the engine's network-free service for the app
// (ADR-010 §1, ENG-1, ENG-2): `serve-sandbox` listens on a Unix socket and
// answers `plan`, `verify` and `ping`, nothing else.
//
// Wire format, one call per connection:
//
//	request:  4-byte big-endian length N (1 ≤ N ≤ MaxFrame), then N bytes of
//	          JSON {"op":"plan"|"verify"|"ping","input":{…},"secret":{…}?}
//	reply:    NDJSON, one line per call: {"proto":1,"op":…,"ok":true,…} or
//	          {"proto":1,"op":…,"ok":false,"error":{"code":"…"}}
//
// Every call runs in a fresh child process (`webhooktrap sandbox-call`, the
// server re-executes itself), so nothing survives a call: not a capture,
// not a secret, not a parsing fault. The server enforces the limits itself:
// CallTimeout of wall clock, a memory cap in the child, a fixed concurrency
// cap (excess calls get `busy` at once, never a queue) and MaxFrame. It logs
// call number, op, outcome code and duration only, never inputs, outputs or
// secrets. Error replies carry codes, never text: the app maps them.
package sandbox

import (
	"encoding/binary"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"time"

	"github.com/jiordiviera/webhooktrap/agent/signing"
)

// Limits (ENG-2). Container limits (128–192 MiB, 0.25 CPU, pids 128) are
// the outer bound; these keep one call from reaching them.
const (
	MaxFrame           = 6 << 20 // one request: up to 5 captures of selection metadata and bodies, base64
	MaxReply           = 1 << 20
	CallTimeout        = 5 * time.Second
	DefaultConcurrency = 2
	ChildMemoryLimit   = 96 << 20 // heap watchdog in the child
	childSoftLimit     = 64 << 20 // GC target in the child
)

// Proto is the sandbox protocol version carried in every reply.
const Proto = 1

// Ops the sandbox answers. Nothing else is reachable (ADR-010 §1 rule 2).
const (
	OpPlan   = "plan"
	OpVerify = "verify"
	OpPing   = "ping"
)

// Error codes of a reply.
const (
	CodeBadFrame      = "bad_frame"
	CodeFrameTooLarge = "frame_too_large"
	CodeUnknownOp     = "unknown_op"
	CodeBadInput      = "bad_input"
	CodeNoPack        = "no_pack"
	CodeBusy          = "busy"
	CodeTimeout       = "timeout"
	CodeMemoryLimit   = "memory_limit"
	CodeInternal      = "internal_error"
	CodeBudget        = "bud_2"
)

// Request is one call frame.
type Request struct {
	Op     string          `json:"op"`
	Input  json.RawMessage `json:"input,omitempty"`
	Secret *SecretIn       `json:"secret,omitempty"`
}

// SecretIn is the one instance secret a self-host `verify` call needs. It
// lives in the child's memory for that call only (ADR-010 §1 rule 4).
type SecretIn struct {
	Name        string `json:"name"`
	ValueBase64 string `json:"value_base64"`
}

// Reply is the one NDJSON line of a call.
type Reply struct {
	Proto   int                `json:"proto"`
	Op      string             `json:"op"`
	OK      bool               `json:"ok"`
	Error   *ReplyError        `json:"error,omitempty"`
	Version string             `json:"version,omitempty"` // ping
	Preview *Preview           `json:"preview,omitempty"` // plan
	Verdict *signing.Diagnosis `json:"verdict,omitempty"` // verify
}

// ReplyError is a fixed code; the app owns the words.
type ReplyError struct {
	Code string `json:"code"`
}

func errorReply(op, code string) Reply {
	return Reply{Proto: Proto, Op: op, Error: &ReplyError{Code: code}}
}

// ErrFrameTooLarge is a length prefix above the cap.
var ErrFrameTooLarge = errors.New("frame too large")

// WriteFrame writes a length-prefixed frame.
func WriteFrame(w io.Writer, b []byte) error {
	if len(b) == 0 || len(b) > MaxFrame {
		return ErrFrameTooLarge
	}
	var l [4]byte
	binary.BigEndian.PutUint32(l[:], uint32(len(b)))
	if _, err := w.Write(l[:]); err != nil {
		return err
	}
	_, err := w.Write(b)
	return err
}

// ReadFrameLen reads the length prefix and checks it against max.
func ReadFrameLen(r io.Reader, max int) (int, error) {
	var l [4]byte
	if _, err := io.ReadFull(r, l[:]); err != nil {
		return 0, err
	}
	n := binary.BigEndian.Uint32(l[:])
	if n == 0 {
		return 0, errors.New("empty frame")
	}
	if int64(n) > int64(max) {
		return 0, fmt.Errorf("%w: %d bytes", ErrFrameTooLarge, n)
	}
	return int(n), nil
}

func encodeReply(r Reply) []byte {
	b, err := json.Marshal(r)
	if err != nil {
		b, _ = json.Marshal(errorReply(r.Op, CodeInternal))
	}
	return append(b, '\n')
}
