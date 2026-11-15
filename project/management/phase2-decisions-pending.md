# Phase 2 — Decisions pending from the PO

**Author:** A00 · **Date:** 2026-11-14 · **Status:** APPROVED by the PO (decision 35).

**Sources:**
- A01, `p1b-product/project/product/spec/scenarios-and-judge.md` §14, §15
  and §17.
- A03, `p1b-design/project/design/phase2/information-architecture.md` §10.

## Product scope (A01)

| # | Decision | Recommendation |
| - | -------- | -------------- |
| A-7 | MVP scenarios and levels | The 8 scenarios plus Unsigned (9 cells). Level 1 and Level 2 (probe) in the MVP; Level 3 and sequence packs after the MVP. |
| A-13 | Guest quotas | 24 h from URL creation, 100 stored deliveries, 10 runs per session. A run counts only once it gets past Once. A second agent replaces the first. |
| A-18 | MVP providers | Stripe, GitHub, Shopify, Standard Webhooks. Twilio and Slack shown as "coming". |
| C-1 | Command name (`trap` is a shell builtin) | `webhooktrap` until Q12 (brand) settles. |
| C-2 | Exit code when a run is both incomplete and has findings | 2 (incomplete) wins over 1 (findings), as in A01's table. |
| C-3 | Probe values on the hosted server | Send only numbers; other values are replaced by short labels. Needs an A09 review before the build. |
| C-4 | Run #3 grows to 16 deliveries (Once sends every event) | Accept. A03 renumbers the data and golden files. |

## Information architecture (A03)

| # | Decision | Recommendation |
| - | -------- | -------------- |
| P1 | App URLs separate from the capture URL | Yes: `/p/{projectKey}` for the app, `/c/{captureKey}` only receives webhooks. |
| P2 | Run visibility | Private by default. Share = anyone with the link. Bodies are shown only for fields not matched by scrub rules, with a "bodies hidden" switch. |
| P3 | The word "delivery" for both a capture (`#01`) and a run delivery (`D3`) | Keep "delivery". |
| P4 | Agent scope | The workspace: one agent serves every project. |
| P5 | Breadcrumb | Shows location; where the run came from moves to a "From" line. |
| P6 | Hosted replay to public URLs as an MVP run target | Yes, limited to scenarios that need no re-signing. |
| P7 | Example run `/r/example` for guests | Yes. |
| P8 | Self-host admin in the MVP | Overview, Access, Limits and Server replay; minimal Users page. The rest later. |
| P9 | Capture URLs per project | One in the MVP. |
| P10 | Pairing started from the CLI | After the A05/A09 pairing model; the MVP keeps the code shown in the browser. |
| P11 | Claiming a guest session on sign-in | Automatic; empty sessions are discarded. |

## Proxy validation protocol (decision 31)

A01 §15 is to be pre-registered before any download.
- **Selection:** fixed GitHub queries, hash-ordered with a salt, 15 main
  repositories (Stripe 6, GitHub 4, Shopify 3, Standard Webhooks 2).
- **Running:** each repository runs in a disposable container. Findings
  are classified blind by A01 and A09.
- **Thresholds:** ≥40 % go, 20–39 % run 10 more repositories, <20 % fall
  back to A. If false positives exceed 35 %, the judge is reviewed first.
- **Before the first run:**
  - the PO approves the protocol;
  - A00 fills in the snapshot date and the salt (done, decision 35);
  - the PO approves the repository list it produces.
