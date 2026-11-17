# Contributing to Webhook Trap 2.0

Thank you for considering a contribution to Webhook Trap. This guide defines the
required workflow for the active 2.0 codebase.

## Non-negotiable repository rule

**All changes must be submitted through a Pull Request. Never push directly to
`v2`. Never commit on `v2`.**

- Treat `v2` as a protected integration branch.
- Create a short-lived branch from the latest `v2` for every change.
- Commit and push only to your feature branch (or your fork).
- Open a Pull Request targeting `v2`.
- Do not merge your own PR unless a repository policy explicitly grants that
  permission.
- A maintainer reviews the diff, checks CI and approves the merge.
- If a change is not ready, keep the PR in draft.

This rule applies to maintainers and external contributors alike. There are no
routine direct-push exceptions.

## Before starting

1. Search existing issues and open PRs to avoid duplicate work.
2. For a bug, feature, or architectural change, open or update an issue first.
3. Agree on the expected behavior and acceptance criteria before implementation.
4. Check the priority assigned to the issue:
    - **P0, Critical:** security vulnerability, data loss, or production-wide
      outage. Stop normal work and contact the maintainers privately for security
      issues.
    - **P1, High:** core workflow broken, severe regression, or release blocker.
    - **P2, Normal:** important bug or planned product improvement.
    - **P3, Low:** polish, minor UX, cleanup, or non-blocking documentation.
5. Do not start a large architectural change without maintainer agreement.

## Branch naming

Create one branch per issue or focused change. Use a descriptive prefix:

- `feat/<short-description>`
- `fix/<short-description>`
- `docs/<short-description>`
- `test/<short-description>`
- `refactor/<short-description>`
- `chore/<short-description>`

Examples:

```bash
git fetch origin
git switch v2
git pull --ff-only origin v2
git switch -c fix/replay-signature-validation
```

The commands above are for creating a branch from `v2`. **Do not commit while
checked out on `v2` and do not push `v2`.** Make all edits and commits on the
new branch.

## Development environment

Webhook Trap 2.0 is a Laravel and Go application. The current stack is:

- PHP 8.5 and Laravel 13
- Inertia v3, React 19, TypeScript and Tailwind CSS 4
- PostgreSQL 18
- Valkey or Redis
- Go engine and agent in `agent/`
- FrankenPHP and Docker Compose

Install dependencies and configure local services according to the root README
and the relevant documentation. Do not copy setup instructions from the legacy
`v1` branch.

## Validation

Run the checks relevant to your change. Before requesting review, run the full
available suite when practical:

```bash
php artisan test
vendor/bin/pint
vendor/bin/phpstan analyse
pnpm run build
pnpm run check
pnpm run types:check
cd agent && go test ./...
```

If a check cannot be run, state exactly which one and why in the PR. Never claim
a check passed if it was not run. Add or update tests for behavior changes,
regressions and new functionality.

## Pull Request requirements

Every contribution must arrive through a PR targeting `v2`.

A PR must include:

- A concise title describing the change.
- The problem and motivation.
- The implementation approach and notable trade-offs.
- The related issue (for example, `Closes #123`).
- Tests and commands actually executed, with their results.
- Screenshots, logs or reproduction steps when relevant.
- Migration, configuration, compatibility and security impact when relevant.

Keep PRs focused and reasonably small. Split unrelated work into separate PRs.
Respond to review feedback with follow-up commits on the same feature branch. Do
not rewrite shared history after review has started unless a maintainer asks.

## Review and merge policy

- CI must pass before merge.
- Required review approvals and status checks are determined by repository
  branch rules.
- Resolve review conversations before merge.
- A maintainer merges the PR into `v2`.
- Prefer rebase merging to preserve individual commits while keeping the
  integration history linear.
- The maintainer may merge their own pull request with the admin bypass once CI
  passes. Nobody pushes to `v2` directly.

## Commit messages

Use short, imperative messages that explain the change:

```
Fix signature verification for replayed deliveries
Add provider fixture export
Document local agent troubleshooting
```

Do not include secrets, credentials, customer payloads or private production
data in commits, issues or PRs.

## Security reports

Do not disclose exploitable vulnerabilities in public issues or PRs. Follow the
private reporting instructions in [SECURITY.md](./SECURITY.md).

## Scope and architecture

Read the architecture and decision records in `project/` and the technical
documentation in `docs/` before changing module boundaries, persistence, agent
protocols, signing behavior or public APIs. Discuss architectural changes in an
issue before implementation.

Thank you for helping improve Webhook Trap.
