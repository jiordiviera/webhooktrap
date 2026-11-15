// Package judge turns a Plan and its Observations into a Verdict with findings,
// reading provider packs only. Pure: no I/O, no clock, no provider name literals
// (ADR-010 §3, D-1, D-8). The server never re-implements it.
package judge
