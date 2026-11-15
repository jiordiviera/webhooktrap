// Package guard decides which targets a run may reach, per role:
// `local` (loopback and declared host:port), `public` (hosted runner) or
// `allowlist` (self-host). Every outbound dial goes through it (ADR-010 §7,
// ADR-006 amendment 1).
package guard
