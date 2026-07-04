# Contributing to Webhook Trap

Thanks for your interest! We welcome contributions of all kinds.

## What to Contribute

**Yes, please:**
- Bug fixes (broken replay, UI crashes, UX friction)
- Performance improvements (API latency, bundle size)
- Documentation (missing steps, unclear config)
- UX improvements (better errors, clearer workflows)
- Feature requests in issues first before implementing

**Not at this stage:**
- Major architectural changes (design first in an issue)
- SAML/SSO (out of MVP scope)
- Async replay with queues (post-MVP)
- Webhook retries (post-MVP)

## Setup

```bash
git clone https://github.com/jiordiviera/webhooktrap.git
cd webhooktrap

pnpm install
pnpm dev              # Starts API on :3333 + web on :7777
```

See [CLAUDE.md](./CLAUDE.md) for project conventions.

## Code Style

- **TypeScript strict mode** — no `any`
- **ESLint + Prettier** — `pnpm format` before committing
- **Meaningful names** — prefer `calculateHashForEvent` over `calc`
- **No comments** — code should explain itself; only comment *why*, not *what*

## Testing

```bash
pnpm test              # Run all tests (API only currently)
pnpm test --watch     # Watch mode
```

For new features: write tests first (TDD), then implement.

## Branching & Commits

- Create feature branches: `git checkout -b feature/webhook-replay-timeout`
- Commit messages are concise: `Add replay timeout to prevent hangs`
- One feature per PR

## Pull Request Process

1. **Fork and branch**
2. **Push to your fork**
3. **Open PR with:**
   - Clear title (e.g. "Fix: Prevent webhook timeout crashes")
   - Description of *what* changed and *why*
   - Link related issues
4. **Wait for CI** (ESLint, TypeScript, tests)
5. **Address feedback** — maintainers will review within 48h

## Running Tests Locally

### API Tests (Japa)

```bash
cd apps/api
pnpm test              # All tests
pnpm test --files "events.spec.ts"  # Single file
pnpm test unit         # Unit tests only
pnpm test functional   # Integration tests
```

### Web Tests (Vitest)

```bash
cd apps/web
pnpm test
```

## Security

If you find a vulnerability, **do not open a public issue**. Email `security@jiordiviera.me` instead.

## Questions?

- Check [PRODUCT.md](./PRODUCT.md) for product direction
- Read [docs/](./docs/) for architecture
- Ask in GitHub discussions

Thanks! 🎉
