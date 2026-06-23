# Product

## Register

product

## Users

**Primary:** Backend developers integrating webhooks (Stripe, GitHub, Shopify, etc.), often solo or on a small team.

**Context:** Mid-integration or mid-debug session. They need an ingest URL fast, want to read the raw payload as it arrived, replay it to localhost or staging, and see status, headers, body, and latency in one place. They are focused, sometimes under time pressure (shipping tonight), and skeptical of tools that add ceremony before value.

**Secondary:** Teammates receiving a read-only share link to inspect an event without creating an account.

**Job to be done:** Complete receive → inspect → replay → see response without ngrok maze, manual JSON copy-paste, or re-firing the provider.

## Product Purpose

Hookscope is a developer-first webhook debugger: capture any HTTP webhook, inspect it faithfully, replay it to a destination URL, and surface the app's response.

**Wedge:** The Stripe CLI for every webhook provider, not just payload viewing but replay plus response visibility.

**Success (MVP):** An external developer completes a Stripe webhook flow without help in under 15 minutes.

**What it is not:** Production webhook infrastructure (retries, SLA), email delivery platform, or no-code workflow builder.

## Brand Personality

**Three words:** Warm · Precise · Trustworthy

**Voice:** Direct, technical, calm. Short sentences. No hype. Copy earns its place (Inspect. Replay. See the response.).

**Emotional goal (product surfaces):** Confidence that nothing was lost in translation (headers, body, method) and control over retry without guesswork.

**Landing vs app:** Landing may be more editorial and premium (serif, storytelling, brand warmth). In-app surfaces stay utilitarian and fast (system UI, list density, task-first). Same personality, different register execution.

## Anti-references

- Observability dark-blue dashboards (Datadog / Grafana default aesthetic)
- Bloated webhook admin UIs (Hookdeck / RequestBin clutter, nested panels, dashboard maze)
- Email marketing product chrome (Resend / SendGrid campaign vibes)
- Generic SaaS purple Inter templates (hero metrics, identical icon-card grids)
- Crypto neon, glassmorphism-as-decoration, gradient text, side-stripe accent borders

## Design Principles

1. **Payload first.** Every screen serves receive, inspect, replay, or response. Decoration that does not clarify the webhook is noise.
2. **Minutes to value.** Anonymous inbox before signup. Persistence and dashboard after account. Never block the first capture.
3. **Earned familiarity.** Product UI should feel legible to someone fluent in Stripe and Linear: predictable nav, list/table density, standard controls. Surprise is reserved for moments, not every screen.
4. **Warm brand, sharp tool.** Marketing can be serif and editorial; the debugger stays precise, readable, and fast.
5. **Show the response.** Replay is not complete until status, latency, and response body are visible. This is the differentiator; design around it.

## Accessibility & Inclusion

**Posture:** Pragmatic dev-tool standard.

- Keyboard paths and focus-visible states on critical flows (auth, inbox list, event detail, replay, copy actions).
- Screen reader labels on interactive controls and status changes (capture, replay result, errors).
- Respect `prefers-reduced-motion` for marketing animation and non-essential motion.
- Sufficient contrast on code, JSON, and HTTP status indicators; no color-only critical state.
- No formal WCAG certification target at MVP; treat regressions on core flows as bugs.

## References (product UI)

- **Stripe Dashboard:** clarity, restrained density, trust through structure
- **Linear:** speed, clean lists, minimal friction between tasks

## Strategic notes

- **Tagline:** Inspect. Replay. See the response.
- **Core URL pattern:** `https://hookscope.dev/i/:id`
- **Personas:** solo backend dev (primary), small team sharing events, debugger in incident/replay mode