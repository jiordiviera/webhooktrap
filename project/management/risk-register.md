# Risk Register — Webhook Trap 2.0

Last updated: 2026-11-11 (hotfix extension)

Likelihood / impact: L (low) · M (medium) · H (high). Severity is the
product of both, with **Critical** reserved for issues exploitable today.

## A. Live production (1.x) — outside the rewrite, but blocking trust

1.x runs in production today: `webhooktrap.dev` (Vercel),
`api.webhooktrap.dev` (VPS, PM2, Caddy, behind Cloudflare),
`docs.webhooktrap.dev` (Mintlify), PostgreSQL on Neon. All three answered
`200` on 2026-11-11. The rebuild will take several phases, so 1.x stays
exposed meanwhile.

Ten security findings were confirmed in the 1.x code by A00 (not exploited
against production). **Details, code locations and impact are kept in a
private report outside this repository** (reference `SEC-2026-11`), per PO
decision of 2026-11-11. This table only tracks them.

| ID   | Area | Severity | Owner | Response | Status |
| ---- | ---- | -------- | ----- | -------- | ------ |
| P-01 | Authentication | Critical | A09 | 1.x hotfix (local branch) | REVIEW_REQUIRED — verified by A11 |
| P-02 | Authentication (OAuth) | Critical | A09 | 1.x hotfix (local branch) | REVIEW_REQUIRED — verified by A11 |
| P-03 | Outbound requests | Critical | A09 | 1.x hotfix (local branch) | REVIEW_REQUIRED — verified with issues (P-11) |
| P-04 | Outbound requests (replay) | Critical | A09 | 1.x hotfix (local branch) | REVIEW_REQUIRED — verified with issues (P-11) |
| P-11 | Outbound requests (address validation) | High | A09 | Hotfix extension (decision 20) | REVIEW_REQUIRED — verified by A11 (incl. DNS rebinding) |
| P-12 | Authentication (abuse) | Medium | A09 | Hotfix extension (decision 20) | REVIEW_REQUIRED — verified with issues (P-16) |
| P-16 | Rate limiting behind proxy | Low | A09/A10 | Deferred: audit Caddy → Next → API trust chain, one fix for both 2FA routes (decision 21) | NOT_STARTED |
| P-17 | Outbound requests (over-blocking) | Low | A00 | Hotfix (decision 21) | REVIEW_REQUIRED — verified by A11 |
| P-13 – P-15 | Error handling / authentication hygiene | Low | A09 | Later | NOT_STARTED |
| P-05 | Authentication (OAuth) | High | A09 | Later hotfix | NOT_STARTED |
| P-06 | Authentication (OTP / reset) | High | A09 | Later hotfix | NOT_STARTED |
| P-07 | Abuse prevention | High | A09/A10 | Later hotfix or edge rule | NOT_STARTED |
| P-08 | Sharing | Medium | A09 | Later hotfix | NOT_STARTED |
| P-09 | Data ownership | Medium | A06 | Later hotfix | NOT_STARTED |
| P-10 | Session storage | Medium | A09 | 2.0 design; 1.x later | NOT_STARTED |

Responsible disclosure: none of these may be described in public GitHub
issues, PRs or commit messages while unpatched; the repository is public.
2.0 must ship with a regression test for each class of issue (A09/A11).

## B. Rebuild risks

| ID   | Risk | L | I | Severity | Owner | Mitigation |
| ---- | ---- | - | - | -------- | ----- | ---------- |
| R-01 | Existing provider integrations break because 2.0 changes ingest URLs or ID formats. | H | H | High | A05 | ADR-009 amendment: keep `/i/{id}` and `/hooks/{id}` with legacy 12-char IDs (Proposed). |
| R-02 | Production data (users, inboxes, events) lost or orphaned at cut-over. | M | H | High | A10 | No destructive operation without backup + PO approval; migration decision in ADR-009 open questions. |
| R-03 | Inertia Live Props was released 2026-11-02 (9 days old); bugs or API churn. | M | M | Medium | A08 | Pin exact versions; Phase 4 spike before relying on it; keep a raw-Echo fallback for the inbox list. |
| R-04 | Live Props partial reloads multiply under webhook bursts (one reload per viewer per event). | M | M | Medium | A08 | Throttled live props, bounded "latest N" list, load test in Phase 6. |
| R-05 | Guest sessions cannot authorise private channels without a resolved principal. | H | M | Medium | A08/A09 | Guest auth guard (ADR-003 amendment), validated in the Phase 4 spike. |
| R-06 | PHP SAPIs do not expose original header order/casing, limiting ADR-005 fidelity. | H | L | Low | A05 | Record SAPI limitation in capture metadata; evaluate a raw-header runtime later. |
| R-07 | Removing loopback replay regresses the headline "replay to localhost" use case. | H | M | Medium | PO | ADR-006 amendment (Proposed). |
| R-08 | Self-hosting footprint grows from 3 to 6 services; contributor and self-host friction. | M | M | Medium | A10 | ADR-007/008 minimal profile (Proposed). |
| R-09 | `docs/` is the published Mintlify site (`docs.webhooktrap.dev`); internal docs placed there could be published. | — | — | Closed | A00 | Closed 2026-11-11: internal docs live in `project/`. |
| R-10 | Scope creep: 1.x features (OAuth, 2FA, share links, media, mail) silently reappear or silently vanish. | M | M | Medium | A01 | Keep/defer/drop decision per feature in Phase 1 requirements. |
| R-11 | Rewrite branch diverges for months while `main` keeps receiving 1.x fixes. | H | L | Low | A00 | 1.x hotfixes land on `main`; `rewrite/v2` never merges 1.x code; regular rebase of docs only. |
| R-12 | Agents editing the same files concurrently. | M | M | Medium | A00 | One writer per file set; isolated worktrees for writing agents; A00 integrates. |
| R-13 | Local `.env` holds production-capable settings; untracked but present in the working tree. | L | H | Medium | A10 | Never read into agent prompts; never copy into 2.0; `.gitignore` already excludes it. |
| R-14 | Twice logo resembles an existing rail-operator or dev-tool mark (resemblance checks partly from memory) | L | M | Medium | PO | Visual + legal check before any public use (decision 26) |
| R-15 | Concept B built without field validation (decision 31): developers may not see findings as real, or the run may be hard to read | M | H | High | A01 / A00 | Proxy validation on open-source handlers; build A/B-common capture first; 10-second test in Phase 2; revisit at Gate 2 |

## C. Closed

None yet.
