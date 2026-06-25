# ADR 001: Media library with Vercel Blob

## Status

Accepted — 2026-06-25

## Context

Hookscope needs a reusable way to attach images and files to domain models (users, inboxes) without coupling storage to PostgreSQL. The product should support CDN-backed public URLs and an API-centric upload flow.

## Decision

Implement a **Spatie Media Library–inspired** polymorphic `media` table in the Adonis API, with **Vercel Blob** as the storage disk and **multipart/form-data** as the v1 upload transport.

### Storage

- Binary files live in Vercel Blob (`BLOB_READ_WRITE_TOKEN` required outside tests).
- Metadata lives in PostgreSQL (`media` table).
- Public URLs are built from `MEDIA_CDN_BASE_URL` (custom domain in production).

### Upload flow

1. Authenticated client sends `POST /api/v1/media` as multipart (`file`, `model_type`, `model_id`, `collection`).
2. API validates ownership, collection rules, mime (magic bytes), and size.
3. API uploads to Blob, persists `media` row, returns JSON.

### Collections (v1)

| Model | Collection | Rules |
|---|---|---|
| `users` | `avatar` | single file, images, 2 MB |
| `inboxes` | `icon` | single file, images, 1 MB |
| `inboxes` | `attachments` | multiple, images + PDF, 10 MB |

### Model integration

- Lucid mixin `withUserMedia` / `withInboxMedia` exposes `getMediaUrl()`, `getFirstMedia()`.
- OAuth login imports remote avatar via `attachFromUrl` when no avatar media exists.

## Consequences

- **Positive:** Polymorphic, extensible, CDN-ready, consistent with Spatie mental model.
- **Positive:** Tests run without Vercel credentials (in-memory blob storage).
- **Negative:** API proxies file bytes (Railway bandwidth); client-direct Blob upload deferred to phase 2.
- **Negative:** Image conversions (thumbnails) not included in v1.

## Alternatives considered

1. **Dedicated columns** (`users.avatar_url`) — rejected; not extensible for inbox attachments.
2. **Client-direct Blob upload** — rejected for v1; API-centric auth and metadata preferred.
3. **@adonisjs/drive + S3** — rejected; Vercel Blob aligns with web hosting on Vercel.