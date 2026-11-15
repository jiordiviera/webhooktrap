# Vision: Webhook Trap 2.0

**Status:** PROPOSED for PO review · **Author:** A01 · **Date:** 2026-11-11
Based on [problems.md](problems.md) and [concepts.md](concepts.md) (recommended: Concept B, Proving Ground). Evidence S# → [research.md](research.md#1-sources).

## The vision in one sentence

**Every webhook handler should be proven against the way providers really deliver (twice, out of order, late, retried, tampered) before it meets production, and watched by the same tool once it does.**

## The belief behind it

Webhooks fail in **sequences**, not in payloads. A developer tests by sending one event, once, in order, quickly. Providers deliver at least once, out of order, with retries, under tight time limits (S1, S9, S10, S21, S29). The gap between those two worlds is where the money is lost: duplicate charges, lost orders, subscriptions that quietly disappear (S7, S9, S11).

The commodity layer of capture, inspect, replay and tunnel is saturated (S42). Showing bytes is solved. **Judging whether your code did the right thing with them is not.**

## Why now

1. **Handler code is getting cheap.** Provider docs now open with agent skills (S1), and agents debug webhooks over MCP (S32). Faster code with less review makes independent verification more valuable.
2. **Signing is converging.** Standard Webhooks adoption is growing: 8% of providers overall, higher among AI platforms (S25, S27, S28). Provider-faithful re-signing is becoming tractable instead of one implementation per provider.
3. **Demand is visible and unserved.** Two OSS chaos CLIs appeared in 2026 (S39, S40). Both judge results only by HTTP status. The category exists and has no leader.
4. **Provider safety nets are shrinking or short.** Redelivery is limited to 3 days on GitHub (S16), Shopify deletes subscriptions after 4 hours of failures (S9), and sandboxes retry only 3 times (S1). Real events have to be captured when they happen.

## The bet (recommended)

**Lead with Concept B (Proving Ground). Make Concept A's capture and signature diagnosis its first minute. Grow into Concept C (Flight Recorder) once trust and users exist.**

```
 Horizon 1: Prove            Horizon 2: Watch                 Horizon 3: Both sides
 ─────────────────────────   ──────────────────────────────   ─────────────────────────────
 Capture real events         Same fixtures become baselines   Sender Lab: the engine
 Diagnose signatures         Silence / failure-rate alerts    reversed, a conformance
 Adversarial scenarios       Shape-drift detection            grader for producers
 State-aware verdicts        Recovery replay                  (Standard Webhooks)
 Fixtures → CI               Sidecar first, inline optional
 Agent + MCP
```

Horizon 1 alone is a complete, shippable, open-source product. Horizons 2 and 3 reuse its core assets: real captures, provider packs, the scenario engine and the agent.

## Value proposition (independent of any existing feature set)

**For** developers integrating webhooks from Stripe, Shopify, GitHub, Twilio, Slack, AI platforms and others,
**who** ship handlers that pass a single manual test and then break under real delivery,
**Webhook Trap** is an open-source proving ground
**that** captures real events and replays them the way providers actually deliver them (duplicated, reordered, delayed, retried, tampered, correctly signed), then tells you what your code did to your state,
**unlike** capture sites, tunnels and provider CLIs, which show you one payload, once, on the happy path.

Gains by persona:
- **Integrator:** "I know my handler is correct, not just reachable." Fewer late-night incidents. Signature failures explained in one line.
- **QA/CI owner:** real, signed, scrubbed fixtures in the repo. Adversarial scenarios as a CI check. No provider CLI in CI.
- **Agent-assisted developer:** the agent gets structured verdicts it can act on.
- **Self-hoster:** everything runs on your own infrastructure. CI runs fully offline.
- **Owner (Horizon 2):** the fixtures and baselines you already have turn into production silence and drift alerts.

## Product principles

1. **Real traffic over synthetic.** Fixtures come from captured deliveries, scrubbed, never hand-written by default.
2. **Verdicts over views.** Every screen answers "is this right?", not only "what is this?". Raw bytes are always one click away, but never the headline.
3. **Provider-faithful.** Signatures, timeouts, retry behaviour and headers match each provider's documented behaviour. If we can't be faithful, we say so.
4. **Secrets stay on your machine.** Signing secrets live in the agent or the self-hosted instance, never on the hosted service.
5. **Your repo is the source of truth.** Fixtures and scenarios are files you commit. Our storage is a convenience, not a lock-in.
6. **Not in your production path, until you choose to be.** Horizon 1 never touches live traffic.
7. **Open core, honest limits.** The engine, agent and packs are open. Hosted adds collaboration and history, never correctness.
8. **Machine-readable by default.** Every verdict is available as CLI output, JSON and MCP, for humans and agents alike.

## What Webhook Trap 2.0 is not

- Not a webhook gateway or router.
- Not a no-code automation tool.
- Not a load tester.
- Not a provider API mock.
- Not a sending platform (Horizon 3 *tests* senders, it doesn't deliver for them).
- Not "a better webhook.site": capture is the doorway, not the house.

## Success measures (proposed, to be set with the PO)

| Stage | Signal | Proposed target |
| ----- | ------ | --------------- |
| First minute | Anonymous visitor sees a live captured event | < 60 s, no sign-up |
| Aha | First adversarial run against localhost with a verdict | < 5 min from landing |
| Value | Share of first runs that surface at least one real finding (not idempotent, over budget, accepts tampered) | > 40% (validates the bet) |
| Habit | Projects with fixtures committed and `test` in CI | 25% of activated users at 30 days |
| Community | Provider packs contributed by non-maintainers | 5 by end of Horizon 1 |

## Kill or pivot criteria

- If fewer than about 20% of first runs produce a finding users call "real", the bet is wrong. Fall back to positioning option 3, the debugger ([positioning.md](positioning.md)).
- If interviews show Owners would pay well for Horizon 2 now while Integrators shrug at Horizon 1, invert the order and start with the Flight Recorder sidecar.
