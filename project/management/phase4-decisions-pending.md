# Phase 4 — Decisions pending from the PO

**Author:** A00 · **Date:** 2026-11-14 · **Status:** DECIDED (decision 39). HD-1 changed to the existing Contabo VPS; the rest approved.

**Sources (all in `.worktrees/p4-architecture/project/`):**
- A05, `architecture/open-questions.md` §4 (PO-1..PO-9);
- A09, `security/threat-model.md` (PO-A..PO-G; details in the private folder);
- A10, `operations/hosting-options.md` (HD-1..HD-10).

A09's verdict: every A05 document is ACCEPT WITH CHANGES; none is rejected.
Where A09 and A05 disagree, A00 recommends A09.

## Architecture (A05, adjusted by A09)

| # | Decision | Recommendation |
| - | -------- | -------------- |
| PO-1 | Accept ADR-010 (Go agent, one engine in three roles, server never judges) | Accept **with A09's changes**: Level 1 verdicts only re-checkable server-side, provenance shown on every run. |
| PO-2 | Accept amendments to ADR-003/004/005/006 | Accept **with A09's changes**; ADR-004 still subject to A08's spike. |
| PO-3 | Mark Phase 0 amendments (self-hosted private targets flag; Redis optional; minimal profile) as Rejected | Yes. |
| PO-4 | Retention: captures 30 days, runs 90 days (accounts) | Yes for the MVP. |
| PO-6 | Hosted server-side runs only for providers without a signed timestamp (GitHub, Shopify) | Yes. |
| PO-7 | `exact` header fidelity out of the MVP | Yes. |
| PO-8 | Share capability = run id | **Superseded by PO-A.** |
| PO-9 | One-paragraph ADR-001 amendment pointing to the domain model | Yes (A00 writes it). |

PO-5 is settled (decision 38).

## Security (A09)

| # | Decision | Recommendation |
| - | -------- | -------------- |
| PO-A | Separate, rotatable share token instead of the run id | Yes. Run ids appear in public CI logs. |
| PO-B | Hosted server-side runs for accounts only at launch, not guests | Yes. |
| PO-C | CI uploads private unless `--share` | Yes. |
| PO-D | Remove the secret's last 4 characters from the server; changes the approved toast copy | Yes. |
| PO-E | `[y/N]` at pairing and local reconfirmation after a claim (one extra terminal step each) | Yes. |
| PO-F | Pairing codes of 10 symbols instead of 6 | Yes. |
| PO-G | B′ (self-hosted private replay) needs a per-user permission | Yes. |

## Hosting (A10)

| # | Decision | Recommendation |
| - | -------- | -------------- |
| HD-1 | Hosting option | A: one EU VPS running the self-host Compose stack, plus a separate runner VPS (€34–53/month). |
| HD-2 | Data location | EU (Germany or Finland). |
| HD-3 | Accounts to create (Hetzner project, backup target, `@webhooktrap` npm scope, email provider) | The PO creates them, at build time only. |
| HD-4 | Backups | 7-day point-in-time recovery, 14-day encrypted off-server copies, disclosed in the privacy notice. |
| HD-5 | Runner as a remote agent-like process on its own VM, no DB/Redis credentials | Yes in principle; A05 and A09 review it when revising. |
| HD-6 | Budget ceiling | €60/month. |
| HD-7 | Domains, with a separate capture host | Yes. |
| HD-8 | Valkey instead of Redis | Yes (drop-in, open licence); the architecture test checks for either. |
| HD-9 | Error tracking | Self-hosted or SaaS: PO's choice; A10 leans self-hosted for privacy. |
| HD-10 | Hosted server-side runs at launch behind a kill switch | Yes, accounts only (PO-B). |

## Next once decided
A05 revises the package with A09's changes and the decisions; A09
re-checks; A08 runs the ADR-004 spike; A10 drafts the Compose profile.
