# ADR-005: Raw HTTP Preservation

**Status:** Accepted
**Date:** 2026-11-11

## Context

A webhook debugger must preserve the actual incoming request as accurately as
the application server allows. Normalizing payloads before storage could
destroy information needed for debugging.

1.x stores `body_text` / `body_json` and headers as a JSON object. That loses
binary bodies, header order and repeated headers.

## Decision

Store the raw request body separately from parsed representations.

Initial delivery attributes:

- HTTP method.
- Request path.
- Raw query string.
- Headers represented as ordered name/value pairs where available.
- Raw body bytes.
- Source IP.
- Content type.
- Received timestamp.
- Body size.
- Capture metadata.

PostgreSQL is the primary database. Use `BYTEA` for bounded raw request
bodies, with an object-storage abstraction for larger payloads when
introduced.

`JSONB` may store parsed representations and searchable metadata, but must
not replace the original raw body.

Header order note: PHP-FPM and most SAPIs expose headers through `$_SERVER`,
which loses original order and casing for some headers. Capture records what
the SAPI provides and flags it in capture metadata; exact wire order is only
guaranteed behind a runtime that exposes raw headers.

## Consequences

- More faithful debugging.
- Ability to display raw and formatted representations.
- Clear distinction between original data and derived data.
- Additional storage management requirements.
