# ADR-003: Hybrid Anonymous and Authenticated Access

**Status:** Accepted
**Date:** 2026-11-11

## Context

Developers should be able to test a webhook without creating an account,
while persistent projects require identity and ownership.

## Decision

Provide two access modes.

### Anonymous mode

- Create temporary webhook endpoints.
- Capture and inspect requests.
- View a live inbox.
- Replay within restricted limits.
- No permanent workspace.
- Short retention period.
- Strict resource quotas.

### Authenticated mode

- Create persistent workspaces.
- Manage projects and endpoints.
- Configure retention.
- Access longer delivery history.
- Manage API keys.
- Invite collaborators in a future release.

### Identity model

Introduce a `GuestSession` entity.

A workspace must belong to exactly one principal:

- an authenticated user, or
- a guest session.

The database must enforce this ownership invariant (a `CHECK` constraint that
exactly one of `user_id` / `guest_session_id` is set).

Anonymous sessions use cryptographically random tokens. Store only their
hashes where practical.

### Guest-to-account conversion

When a guest registers:

1. Authenticate the new account.
2. Validate ownership of the guest session.
3. Transfer eligible resources in a transaction.
4. Apply authenticated retention and quota rules.
5. Revoke the old guest capability.
6. Invalidate old guest sessions.

### Security restrictions

- Anonymous endpoints cannot be globally enumerated.
- Anonymous access is protected by rate limits.
- Guest resources have strict size and retention limits.
- Endpoint access requires an unguessable capability.
- Guest and authenticated resources are isolated.
- Anonymous users cannot access workspace administration.

### Amendment (Phase 0): guests as an auth guard

Laravel's broadcast authorization and policies both expect an authenticated
principal; a private channel request with no resolved user is rejected
before the channel callback runs. The guest session is therefore modelled as
a first-class auth guard (`guest`) whose user provider resolves a
`GuestSession` model implementing `Authenticatable`. Channel callbacks and
policies receive either a `User` or a `GuestSession` and check ownership
against whichever it is. See ADR-004 for the spike that validated this.
