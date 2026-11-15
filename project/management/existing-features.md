# Existing Features — Webhook Trap 1.x

> **Historical reference only (decision 15, 2026-11-11).** 2.0 is a new
> product; this inventory is not a scope baseline for it.

**Phase:** 0 · **Author:** A00 · **Date:** 2026-11-11 · **Status:** REVIEW_REQUIRED

Inventory of what 1.x does today, as input for A01's keep / defer / drop
decisions. "2.0 plan" maps each feature to the orchestration brief; it is not
a decision.

Legend — Tests: ✅ covered · ◐ partial · ✗ none

## Capture and inspection

| Feature | 1.x behaviour | Tests | 2.0 plan |
| ------- | ------------- | ----- | -------- |
| Public ingest | `GET/POST/PUT/PATCH/DELETE /i/{id}` (API) and `/hooks/{id}` (web rewrite); always `200 {received:true}`, even for unknown/expired inboxes; exact path only | ◐ | Core (Capture). Keep legacy paths (ADR-009 amendment, Proposed) |
| Body limit | 1 MiB, checked on Content-Length and serialised size; chunked oversize returns 413 | ✗ | Core; limits per access mode (ADR-003) |
| Storage | headers JSON (redacted), query JSON, `body_text`, `body_json`, IP, content type, size | ◐ | Replaced by raw preservation (ADR-005) |
| Header redaction | `authorization`, `cookie`, `set-cookie`, `x-api-key` → `[REDACTED]` | ✗ | Revisit with A09: redact for display vs keep for replay |
| Event list | paginated, search on path/method, method filter, sort; polled every 3 s | ◐ | Live inbox (ADR-004) |
| Event inspector | headers, query, JSON body viewer; non-JSON bodies show "(empty)" | ✗ | Core inspector with raw + formatted views |
| Copy as JSON / cURL | client-side | ✗ | Likely keep |

## Inboxes (endpoints)

| Feature | 1.x behaviour | Tests | 2.0 plan |
| ------- | ------------- | ----- | -------- |
| Anonymous inbox | created without account; 48 h expiry; publicly readable by ID | ✅ | Guest sessions (ADR-003); capability-based access, not public-by-ID |
| Saved inbox | owned by user; never expires | ✅ | Endpoints inside projects/workspaces |
| Rename / delete | owner only | ◐ | Keep |
| Default replay URL | per inbox | ✗ | Replay configuration |
| Expired-inbox purge | `purge:expired-inboxes` command, never scheduled | ✗ | Retention domain, scheduled and idempotent |
| Dashboard stats | 3 cards + chart computed client-side from ≤100 inboxes | ✗ | Monitoring domain |

## Replay

| Feature | 1.x behaviour | Tests | 2.0 plan |
| ------- | ------------- | ----- | -------- |
| Replay | synchronous `fetch`, 30 s timeout, hop-by-hop headers stripped, SSRF guard (see P-04) | ◐ (negative only) | Async jobs, immutable attempts, rebinding-safe (ADR-006) |
| Replay to localhost | opt-in via `ALLOW_INSECURE_REPLAY_TARGETS`, refused in production | ✅ (guard) | ADR-006 amendment (Proposed) + future local agent |
| Replay history | status, duration, headers, body per attempt | ✗ | Keep, plus comparison |
| Errors | BLOCKED_TARGET, TIMEOUT, CONNECTION_REFUSED, REPLAY_FAILED | ◐ | Lifecycle states (ADR-006) |

## Sharing

| Feature | 1.x behaviour | Tests | 2.0 plan |
| ------- | ------------- | ----- | -------- |
| Share link per event | 32-char token, never expires, no revoke; public page shows IP, headers, bodies, replays | ✗ | **Not in brief** — decision needed |

## Identity and account

| Feature | 1.x behaviour | Tests | 2.0 plan |
| ------- | ------------- | ----- | -------- |
| Email + password signup/login | opaque DB tokens, no expiry, stored in `localStorage` | ✗ | Core (authenticated mode) |
| GitHub / Google OAuth | Ally, stateless, auto-link by email, token in redirect URL | ✗ | **Not in brief** — decision needed |
| 2FA (TOTP) | generate, verify, recovery codes, disable, challenge (see P-01) | ✗ | **Not in brief** — decision needed |
| Email verification | 6-digit OTP; not enforced anywhere | ✅ | Decision needed |
| Password reset | OTP → reset token → new password; existing tokens kept | ◐ | Core if passwords are kept |
| Profile | edit full name; avatar | ✅ | Keep (minimal) |
| Personal API tokens | list/create/revoke; list also shows login/OAuth tokens | ✅ | API keys (brief §4 `ApiKey`) |

## Platform

| Feature | 1.x behaviour | Tests | 2.0 plan |
| ------- | ------------- | ----- | -------- |
| Media (R2) | avatar 2 MB, icon 1 MB, attachments 10 MB; magic-byte checks; import from URL (see P-03) | ◐ | **Not in brief** — likely drop beyond avatars |
| Transactional mail | BullMQ → worker → Resend; MJML templates | ✗ | Needed if email auth flows are kept |
| Health | `GET /health` (DB only) | ✗ | Health checks for all services (ADR-008) |
| Error tracking | Sentry (optional) | ✗ | Monitoring decision (A10) |
| Analytics | Vercel Analytics behind cookie consent | ✗ | Decision needed (self-host friendly option) |

## Marketing and docs

| Feature | 1.x behaviour | 2.0 plan |
| ------- | ------------- | -------- |
| Landing page | 13 sections, JSON-LD, OG images; testimonials unattributed; two free pricing tiers | Rewrite after brand approval |
| Legal | privacy page, sponsor page | Keep, review content |
| Docs site | Mintlify at `docs.webhooktrap.dev` (guides, integrations, API reference) | Rewrite for 2.0; location decision (B-03) |

## Not present in 1.x but in the 2.0 brief

Workspaces and projects; workspace members; guest-to-account conversion;
raw-byte capture; live updates over websockets; async replay with attempt
comparison; retention policies; audit log; Docker self-host distribution with
workers and Reverb; OpenAPI specification.
