// Package plan turns a selection (captures, scenarios, pack version, seed) into a Plan:
// the exact list of deliveries a run will send. Pure: no I/O, no clock, no network
// (ADR-010 §3, D-1, D-3).
package plan
