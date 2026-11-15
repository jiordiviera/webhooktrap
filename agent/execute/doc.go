// Package execute sends a Plan through the Transport port, waits through the
// Scheduler port and records Observations (ADR-010 §3, D-4, D-6). The only stage
// that talks to the network, always behind guard.
package execute
