// Package scrub removes secrets and probe values before anything leaves the
// machine or is shown in a share (ADR-010 §11, PV rules). Secrets never reach
// logs, argv or the run file.
package scrub
