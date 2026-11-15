# ADR-006 Amendment 1: Server-Side Replay Under Concept B (B′)

**Status:** Accepted (decision 46). Text as of revision 3 (package revision 5; revision 2 after A09's threat model)
**Amends:** ADR-006 Secure Replay Engine (Accepted). The accepted file is not edited.
**Supersedes (accepted, decision 46):** ADR-006's "Amendment (Phase 0, Proposed): self-hosted private targets" (flag `WEBHOOKTRAP_ALLOW_PRIVATE_REPLAY_TARGETS`, all private ranges, `APP_ENV` check). The PO decided to mark it Rejected (PO-3, decision 39); A00 records that.
**Date:** 2026-11-14 · **Author:** A05
**Source:** decision 32 §3, decision 35 (P6, P8), decision 39 (PO-2, PO-6, PO-B, PO-G, HD-5, HD-10), `amendment-evaluation.md` §1 option B′, ADR-010.
**Security review:** A09 `project/security/threat-model.md` §9.5 (ACCEPT WITH CHANGES), §7.1, §7.2.

## Context

ADR-006 was written for a debugger that replays one request from the server. Under concept B, a "replay" is a run: a planned sequence of scenario deliveries, judged. ADR-010 runs the engine on the developer's machine for localhost. Two server-side cases remain: hosted runs to public URLs (P6), and self-hosted runs to private hosts on the operator's own network (B′).

## Decision

### 1. Every server-side replay is a run executed by the engine, in the server runner

A server-side run is a `RunJob` pulled by the **server runner** (`webhooktrap serve-run`, ADR-010 §12): a long-running process on its own VM (hosted) or its own container network (self-host), authenticated by a runner token, holding no database, Redis or Reverb credentials (HD-5, S-11). The same plan, judge, caps (CAP-1, CAP-2) and run document apply. ADR-006's `ReplayAttempt` maps to an immutable **run delivery** row, written once when completed. The lifecycle `Pending → Running → Succeeded / Failed / Timed out` maps to run statuses (Queued, Running, then a final status from RUN-1) and per-delivery results (§7.1 of A01's spec). The `replay` queue keeps only job expiry and metrics.

### 2. Hosted service (P6)

- Run target "From Webhook Trap servers (public URLs only)".
- **Accounts only** at launch (PO-B). Guests use the agent; the run form does not offer the server target to guests and the API refuses it.
- **Kill switch** `server_replay_public` (HD-10), environment-configured on hosted. Off: target hidden, API `422 server_runs_disabled`, runner receives no jobs.
- Guard mode `public` (ADR-010 §7): resolve once, refuse if **any** resolved address is outside public unicast, pin, re-check at dial; no proxy; no redirects; TLS verification on. The refused set is a **tested data table** covering every special-purpose IPv4/IPv6 range, IPv4-embedded IPv6 and transition prefixes, CGNAT, benchmarking, multicast, broadcast and every metadata address and name of the hosting provider.
- **Ports:** 80, 443 and 1024–65535 only.
- **Outbound headers** from the re-send allow-list (ADR-010 §7); never `Authorization` or `Cookie`; an identifying `User-Agent` with a link to an abuse page.
- **Limits:** 2 concurrent runs per principal; a daily server-run cap per principal; a per-destination-host rate limit across all principals; 50 deliveries; 30 s per request; response read cap; 15 minutes per run (CAP-2). Enforced by the app at queue and at job issue; per-request limits by the engine.
- **Egress firewall: required** (A09 §7.1 item 6). The runner VM denies private, link-local, CGNAT, ULA and metadata at the network layer and has a dedicated egress IP separate from the app's (HD-5). It does not share the VPS that hosts the app and 1.x (decision 39).
- The hosted service holds no signing secrets (concept-impact §4). Therefore only scenarios that need no re-signing can run: GitHub and Shopify (PO-6). Stripe and Standard Webhooks show **Needs secret: use the agent**. A01 confirms the scenario list.

### 3. Self-hosted, B′ (off by default; PO-G)

- Instance setting **Server replay → Private destinations**: off by default (IA `/admin/server-replay`).
- **Per-user permission.** B′ is usable only by users an instance admin has granted the B′ permission, in addition to being authenticated. Guests never.
- **B′ and open sign-up are mutually exclusive** (hard rule): turning B′ on while sign-up is `open` is refused, and opening sign-up while B′ is on is refused.
- When on, the operator lists **explicit allow-list entries**: host or CIDR **plus a required port or port range**. CIDR breadth is capped (IPv4 /16, IPv6 /48). Entries overlapping the instance's own services (app, Postgres, Redis, Reverb, proxy), metadata or link-local are refused at save. Only those entries are added to the `public` guard; the guard mode becomes `allowlist`. There is no "all private ranges" switch.
- Metadata endpoints and the rest of link-local stay blocked whatever the allow-list says.
- The `runner` container sits on a network with no route to the backend services, so a mistaken entry cannot reach them **[A10]**.
- **Instance signing secrets** (Q4, IA `/admin/secrets`, write-only, encrypted at rest) are usable only for runs initiated by users holding the B′ permission, optionally bound to specific allow-list entries. The runner fetches them per job over the runner API (ADR-010 §12.4); they are never in argv, environment, queue payloads or logs, never shown after save and never sent to any agent. Every use writes an audit row (TM-73, TM-75). For `verify`, the one secret a call needs is sent to the network-less `engine` service over its Unix socket and dropped after the call (ADR-010 §1 rule 4).
- Every change to the setting, the allow-list or a user's B′ permission is written to the admin audit log; every run using an allow-listed private target records the entry it matched.
- A warning banner on `/admin` while private destinations are on.
- No `APP_ENV` coupling (the Phase 0 amendment's weakness).
- **Instance admin bootstrap** only from the host (console command or a one-time setup token printed to the server log); never "first sign-up is admin" (TM-19).

### 4. Localhost on the hosted service

Only through the agent (ADR-010). The hosted server never connects to private addresses.

## Consequences

- The engine runs server-side in the runner image, not in a PHP worker; ADR-007's `replay` queue keeps expiry and metrics only.
- Hosted server runs of Stripe events can't do much without secrets; the agent stays the main path, which matches the product story.
- One guard implementation with three modes removes the risk of a PHP SSRF guard drifting from the engine's.
- Hosted operates a second small VM (HD-5); abuse complaints and IP blocks hit the runner, not ingest.

## Open items

- ~~Egress firewall as defence in depth~~ **Required** (A09 §7.1 item 6); A10 implements.
- ~~Per-user permission for B′~~ Yes (PO-G).
- ~~Rate and concurrency limits~~ Answered in §2; proposed values in `open-questions.md` §4.

## Change log

| Date | Revision | Change | Source |
| ---- | -------- | ------ | ------ |
| 2026-11-14 | 1 | First draft | A05 |
| 2026-11-14 | 2 | §2: refused-set data table, ports, header allow-list, User-Agent and abuse page | A09 §9.5-1, §7.1, TM-70 |
| 2026-11-14 | 2 | §2: limits (concurrency, daily, per-destination) | §9.5-2, TM-71, TM-76 |
| 2026-11-14 | 2 | §2: accounts only; kill switch | §9.5-3; PO-B, HD-10 |
| 2026-11-14 | 2 | §3: per-user permission; exclusive with open sign-up; port required; CIDR caps; overlap refusal; admin bootstrap from host | §9.5-4, §7.2, TM-19, TM-72, TM-92; PO-G |
| 2026-11-14 | 2 | §3: instance secrets restricted to B′ users, per-job fetch / stdin, audited per use | §9.5-5, TM-73, TM-75 |
| 2026-11-14 | 2 | §1, §2: runner as a remote process on its own VM; egress firewall required | §9.5-6; HD-5, decision 39 |
| 2026-11-14 | 2 | §2: GitHub and Shopify only on hosted | PO-6 |
| 2026-11-14 | 3 (package rev 5) | §3: `verify` secrets go to the `engine` service over its socket (was: stdin in the app container) | A09 §12.9 ENG-1/ENG-2 |
| 2026-11-14 | 3 (package rev 6) | Status: Accepted (decision 46); no content change. The accepted ADR file and the ADR index are A00's | decision 46 |
