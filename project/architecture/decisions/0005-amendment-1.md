# ADR-005 Amendment 1: Request Target, Public Origin, Header Fidelity and Ingest Invariants

**Status:** Accepted (decision 46). Text as of revision 5 (§4 provider-range buckets, A09 slice 3 review S3-1; rev 4: §9 FrankenPHP deployment, decision 43; A09 re-check rev 3: SV-2 dependency, HSTS rule)
**Amends:** ADR-005 Raw HTTP Preservation (Accepted). The accepted file is not edited.
**Date:** 2026-11-14 · **Author:** A05
**Source:** decision 32 §2 (raw request target, public scheme and host as the provider saw them), concept-impact §3, A11 A-14 and A-15 (owned by A05), decision 39 (PO-2 with A09's changes, PO-4, PO-7, HD-7).
**Security review:** A09 `project/security/threat-model.md` §9.4 (ACCEPT WITH CHANGES).

## Context

Some providers sign the URL, not only the body (Twilio; Slack's legacy scheme). To diagnose their signatures, capture must record the request target byte for byte and the public origin the provider used, which is not the origin PHP sees behind a reverse proxy. A11 also asked for an explicit header-fidelity flag (A-14) and a single response invariant for ingest (A-15).

## Decision

### 1. New capture attributes

| Attribute | Meaning | Source |
| --------- | ------- | ------ |
| `raw_target` | The request target exactly as received (path + `?` + query, percent-encoding and repeated keys preserved) | `REQUEST_URI` as passed by the web server; proxies must be configured not to normalise it (A10 checklist) |
| `path`, `raw_query` | Split of `raw_target` at the first `?`, no decoding | derived |
| `public_origin` | `scheme://host[:port]` the provider used | the **configured** capture origin (`CAPTURE_PUBLIC_ORIGIN`, the separate capture host, HD-7), never request headers |
| `forwarded` | `X-Forwarded-Proto`, `X-Forwarded-Host`, `Forwarded` as received, stored and labelled as observed facts | request headers |
| `public_origin_source` | `configured` · `forwarded_trusted` · `direct` | Which value `public_origin` came from |
| `source_ip` | Client address | **only through the trusted-proxy chain**; a forwarded value from an untrusted peer is ignored (TM-05) |
| `header_fidelity` | `exact` · `sapi` | A-14 |

Rule: the provider-signed URL is reconstructed as `public_origin + raw_target`. `public_origin` comes from configuration because forwarded headers are client-controlled; when the operator sets `CAPTURE_TRUST_FORWARDED_ORIGIN=true` and the request comes from a trusted proxy, the forwarded value is used and the source says so (S-6, accepted by A09).

### 2. Header fidelity (A-14, resolved; PO-7)

- Under PHP-FPM (and FrankenPHP/Octane), header order and original casing are not guaranteed, and repeated headers may be joined. Capture stores headers as an **ordered list of `[name, value]` pairs as the SAPI reports them**, sets `header_fidelity = sapi`, and never invents an order.
- The Raw tab shows, when `sapi`: "Order and casing as reported by the server; may differ from the wire." (A-14 wording; final copy A03.)
- `exact` is reserved for a future ingest runtime that reads raw header bytes. It is **not** in the MVP (PO-7).
- Tests assert the flag and the copy, not wire order (A-14).

### 3. Header redaction at storage (S-7)

`Authorization`, `Proxy-Authorization` and `Cookie` values are stored as **`[redacted · N bytes]`**, and the capture metadata lists the redacted names. No hash fragment of the value is stored: an unkeyed truncated hash of a low-entropy credential would let anyone who sees the capture confirm a guess offline. If A01 later needs equality across captures, it uses a keyed HMAC with a per-project key, decided then. Signature headers are **kept** (needed for SIG-1). Share views additionally apply the SCR-3 header allow-list. Redacted placeholders are never re-sent (ADR-010 §7).

### 4. Ingest response invariant (A-15, resolved)

Every request to `/c/{captureKey}` and `/c/{captureKey}/{anything}` on the capture host receives **the same status and body**:

```
HTTP/1.1 200 OK
Content-Type: application/json
Cache-Control: no-store

{"received":true}
```

This holds for: a valid key; an unknown key; a rotated key; an expired guest session; a guest over 100 stored captures; an account project over its quota (§5); the global guest budget reached; a body over the kept limit; storage-level rate limits; a database outage after the request was read (the request is dropped and counted in a metric). `HEAD` gets the same status and headers with no body.

The only responses that may differ are **network-level** and apply to every capture URL alike, keyed by client address: `429` from the edge rate limiter, and `413` from the reverse proxy above the hard request limit (default 25 MiB). **The edge rate limiter must never key on whether a capture key exists or on its state** (that would leak existence). From documented provider egress ranges, where many tenants share one address, the bucket may be (address, hash of the key as presented), counted the same way for unknown and malformed keys, so it reveals nothing about existence (A09 slice 3 review S3-1). There are no other per-key rate limits. Neither response reveals whether a key exists. The timing difference between stored and dropped requests is an accepted residual (A09 R-3, TM-01).

### 5. Body size and storage quotas (A-15, TM-02)

- **Kept:** bodies up to and including **5 242 880 bytes (5 MiB)**. The stored rule is MiB.
- **Not kept:** 5 242 881 bytes and above, up to the hard limit. Metadata, headers, size and SHA-256 are stored; `body_kept = false`; Prove is disabled for that capture.
- Boundary tests: 5 242 879, 5 242 880, 5 242 881 bytes.
- Self-host admins may change the kept limit (IA `/admin/limits`), within the hard limit.
- **Account quota per project:** captures per day and stored bytes (defaults in instance limits; hosted values in `open-questions.md` §4). Over quota: answered identically, not stored, counted in a metric.
- **Global guest storage budget:** total stored bytes across all guest sessions. Reached: guest captures answered identically, not stored, counted in a metric.
- Ingest workers are sized separately from UI workers; header and body read timeouts and a per-address concurrent connection limit at the edge (TM-03) **[A10]**.

### 6. Storage

Unchanged principle: raw body bytes in `BYTEA`, kept in a separate table from capture metadata (schema.md §4). An object-storage adapter stays out of the MVP. Account retention: captures 30 days by default (PO-4).

### 7. Rendering untrusted capture data (TM-06)

- Bodies, headers, path and query are rendered **as text only**: no HTML, Markdown or rich preview, no `dangerouslySetInnerHTML`.
- The raw download (`/p/{projectKey}/d/{n}/raw`) is served with `Content-Type: application/octet-stream`, `Content-Disposition: attachment`, `X-Content-Type-Options: nosniff` and `Content-Security-Policy: sandbox`.
- App-wide Content Security Policy without `unsafe-inline` scripts.

### 8. Capture host (HD-7, TM-04)

Ingest is served from a **separate host** (for example `in.<domain>`) that serves only `/c/…`; app routes are not served there and app cookies are never set for it. App cookies use the `__Host-` prefix regardless. `Cookie` is redacted if a browser still sends one (§3). CSRF, sessions and cookies are disabled on the ingest route group; no CORS headers.

### 9. Deployment on FrankenPHP (decision 43)

The 2.0 image is the PO's FrankenPHP template behind the host's reverse proxy on `127.0.0.1` (A10 `operations/contabo-deployment-plan.md`). Effects on this amendment:

1. **Header fidelity stays `sapi` (§2).** FrankenPHP receives requests through Go's `net/http`, which canonicalises header-name casing and does not keep the order of distinct header names; PHP then sees `HTTP_*` variables. The stored list is the order PHP reports. Nothing changes in the schema or the Raw-tab note; tests still assert the flag, not wire order.
2. **Raw request target (§1).** `REQUEST_URI` is built by FrankenPHP from Go's `URL.RequestURI()` of the original request. A10's conformance corpus (`contabo-deployment-plan.md` §4.3) is the acceptance test; a failing case blocks go-live.
3. **Invalid request targets.** Go's server answers `400` before PHP runs for a target it cannot parse (for example an invalid percent-escape `%zz`). **Accepted** as a network-level response, alongside the edge `429` and proxy `413` in §4: it is outside RFC 3986, identical for every capture key and for unknown keys, so it reveals nothing about a key. No custom build. Raw non-ASCII bytes re-escaped by Go are recorded as received by PHP; the Raw tab already says the server may differ from the wire.
4. **Headers added by our own proxies.** When the request comes from a trusted proxy, the headers that proxy sets (`CAPTURE_PROXY_HEADERS`, default `X-Forwarded-For`, `X-Forwarded-Proto`, `X-Real-IP`) are removed from the stored header list, because they describe our hop, not the provider's request. Their values feed `forwarded`, `public_origin_source` and `source_ip` as in §1, and the capture metadata lists the removed names. Headers from an untrusted peer are stored as received. **Dependency (SV-2, A09 §12.5, TM-141):** on the shared VPS any local process can reach `127.0.0.1:${APP_PORT}` and so appear to be the trusted proxy. A trusted-proxy address list alone does not distinguish the host proxy from another local process; the design therefore **depends on A10's SV-2**: the host proxy proves itself with a secret header that the in-image Caddyfile checks and strips, and without that proof forwarded headers are **not** trusted (`source_ip` is then the direct peer, per-address limits and scheme come from configuration). Test TM-T141: a direct local request with forged forwarded headers has its forwarded values ignored.
5. **Plain HTTP on the capture host.** `in.` accepts both `https` and `http` (some providers send to `http://`): no redirect (a redirect would break POST deliveries), no HSTS on `in.`. **HSTS rule (A09 §12.3 R-O):** this is safe only while no HSTS header on `app.` or the apex carries `includeSubDomains` or `preload`, since either would force `https` on `in.`; the app and A10's proxy configuration must not set them (a test asserts the header values). The scheme of `public_origin` comes from the trusted `X-Forwarded-Proto` (source `forwarded_trusted`); host and port stay configured.
6. **Size limits.** 25 MiB is enforced by the host proxy, with FrankenPHP's `request_body max_size` and PHP `post_max_size` as the backstop (self-host). The uniform `413` rule of §4 applies to both.
7. **Worker mode.** If FrankenPHP worker mode (Laravel Octane) is enabled, no request-scoped state may live in singletons or static properties: principal, guest session, ingest counters, WebAuthn challenges (domain-model §2.2) and the Clock adapter. A Pest `arch()` test forbids static mutable state in modules; A06 runs the ingest and auth suites in worker mode before enabling it.
8. **App origin for WebAuthn and links.** Behind the host proxy, every absolute URL and the WebAuthn RP ID and origin come from `APP_URL`/`CAPTURE_PUBLIC_ORIGIN` configuration, never from `Host` or forwarded headers.

## Consequences

- A10's proxy configuration must preserve `REQUEST_URI` verbatim, set the trusted-proxy list, route the capture host to ingest only, and size ingest workers separately.
- Ingest must hash the body even when it is not kept, so it reads the body fully (up to the hard limit) before responding.
- The capture row gains seven columns; quota checks add one counter update per stored capture.

## Change log

| Date | Revision | Change | Source |
| ---- | -------- | ------ | ------ |
| 2026-11-14 | 1 | First draft | A05 |
| 2026-11-14 | 2 | §3: placeholder `[redacted · N bytes]`, no unkeyed hash; list confirmed | A09 §9.4-1, S-7 |
| 2026-11-14 | 2 | §1: `source_ip` through the trusted-proxy chain only | §9.4-2, TM-05 |
| 2026-11-14 | 2 | §7 new: text-only rendering, raw download headers, CSP | §9.4-3, TM-06 |
| 2026-11-14 | 2 | §5: account project quotas and global guest budget, dropped identically; ingest sizing | §9.4-4, TM-02, TM-03 |
| 2026-11-14 | 2 | §8 new: separate capture host; `__Host-` cookies | §9.4-5, TM-04; HD-7 |
| 2026-11-14 | 2 | §4: edge limiter never keys on the capture key | §9.4-6 |
| 2026-11-14 | 3 | §9 new: FrankenPHP effects (header fidelity unchanged, `400` for unparsable targets accepted as network-level, own proxy headers removed, plain HTTP on `in.`, size backstop, worker-mode state rule, configured origins) | decision 43; A10 deployment plan |
| 2026-11-14 | 4 | §9 item 4: dependency on SV-2 stated (proxy proves itself; forwarded headers untrusted otherwise); §9 item 5: no HSTS `includeSubDomains`/`preload` on `app.` or the apex | A09 §12.3 R-O, §12.7; SV-2, TM-141 |
| 2026-11-14 | 4 | Status: Accepted (decision 46); no content change. The accepted ADR file and the ADR index are A00's | decision 46 |
| 2026-11-15 | 5 | §4: "never key on the capture key" becomes "never key on whether a capture key exists or on its state"; per-(address, key hash) buckets allowed from documented provider egress ranges only | A09 slice 3 review S3-1 |
