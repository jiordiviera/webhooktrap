# Security Policy

## Overview

Webhook Trap stores webhook payloads and allows replaying them to test integrations. We take security seriously.

## Security Features

### Payload Isolation
- **Per-inbox isolation**: Each inbox has a unique URL. Users can only view/replay events from inboxes they own.
- **Authentication**: Authenticated users own inboxes. Share tokens provide read-only access without auth.
- **Expiration**: Inboxes can be set to expire, auto-deleting old events after X days.

### Header Sanitization
- **Sensitive headers redacted**: Authorization, Cookie, Set-Cookie, X-API-Key are stored as `[REDACTED]` immediately upon ingest.
- **Why**: Prevents accidental exposure of tokens, API keys, or session cookies stored in the database.
- **Verified**: See `apps/api/app/support/sanitize_headers.ts`

### Replay Security
- **SSRF Protection**: Replay targets are validated to prevent internal network access:
  - Blocks localhost, 127.x, ::1
  - Blocks private ranges (10.x, 192.168.x, 172.16-31.x)
  - Blocks cloud metadata (169.254.169.254)
  - Blocks IPv6 link-local, unique-local, and multicast
  - Validates against DNS rebinding attacks
- **Header Filtering**: Request headers that would break HTTP semantics are removed (connection, transfer-encoding, host, etc.)
- **Timeout**: Replays timeout after 30 seconds
- **Verified**: See `apps/api/app/support/ssrf_guard.ts`

### Input Validation
- **Body size**: Max 1MB per webhook payload
- **Content**: JSON or raw text, safely parsed
- **URLs**: Must be http/https only

---

## Reporting Security Issues

**Do NOT open a public issue for security vulnerabilities.**

Email `security@jiordiviera.me` with:
- Description of the vulnerability
- Steps to reproduce
- Potential impact
- Suggested fix (if any)

We aim to respond within 48 hours and will work with you to fix the issue before public disclosure.

---

## Known Limitations (Not Vulnerabilities)

1. **Anonymous Inboxes**: Users can create inboxes without authentication (by design — quick start). 
   - Mitigation: Inboxes expire after N days; use email verification on production deployments.

2. **Replay Rate Limiting**: Not rate-limited per user/event (current MVP scope).
   - Mitigation: Easy to add if abuse is observed; see `apps/api/start/routes.ts` for endpoint config.

3. **No TLS Client Certificates**: Replay always uses standard HTTPS. Client cert auth for API backends not supported yet.

---

## Compliance & Standards

- **Data Retention**: Inboxes and events are deleted when expired (configurable per inbox, default 7 days)
- **Encryption in Transit**: HTTPS enforced on production
- **Encryption at Rest**: Depends on your hosting (Vercel, Railway, Fly.io, or self-hosted VPS)
  - Self-hosted: Use full-disk encryption or managed Postgres (Neon, AWS RDS)

---

## For Self-Hosted Deployments

If self-hosting Webhook Trap, ensure:
- **Database**: Backed up regularly; use managed Postgres if possible (Neon, AWS RDS)
- **Secrets**: Never commit `.env` with real credentials
- **TLS**: Use valid certificate (Caddy auto-renews via Let's Encrypt)
- **Network**: Firewall ports 3333 (API) and 7777 (web) as needed
- **Monitoring**: Log suspicious activity; consider Sentry for error tracking

---

## Changelog

- **v1.0** (2026): Initial release. Security audit passed ✓
