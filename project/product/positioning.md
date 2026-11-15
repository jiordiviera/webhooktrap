# Positioning

**Status:** PROPOSED for PO review · **Author:** A01 · **Date:** 2026-11-11
Evidence S# → [research.md](research.md#1-sources). The taglines below are working lines for the brand and copy work, not final copy.

## Market frame

| Axis | Crowded end | Open end |
| ---- | ----------- | -------- |
| What the product does | Show what arrived (capture, inspect, replay, tunnel) (S33, S34, S42) | **Judge what your code did** (S39, S40 are early and status-only) |
| Where it runs | In the production path (Hookdeck Event Gateway) | Before production, plus a sidecar beside it |
| Provider coverage | Single provider (Stripe CLI, `gh webhook`) (S5, S17) | Cross-provider, provider-faithful |
| Distribution | Hosted only (webhook.site free tier, Hookdeck) | Open source and self-hosted, offline CI |

---

## Option 1: "The test suite for webhook handlers" ★ recommended

- **Category:** webhook testing and verification (a category still being formed).
- **Statement:** *For developers integrating webhooks, Webhook Trap is the open-source proving ground that replays real events the way providers really deliver them, and tells you whether your handler survived.*
- **Working lines:** "Your webhook handler works. Once." / "Break it before Stripe does." / "Every way a webhook can arrive. Tested."
- **Target:** Integrator, QA/CI owner, Agent-assisted developer. Self-hosters as the distribution channel.
- **Against:**
  - webhook.site and Hookdeck Console: *they show it, we prove it.*
  - Stripe CLI: *every provider, and the unhappy paths.*
  - HookChaos and Carbon Layer: *real traffic and state verdicts, not synthetic payloads judged by status code.*
- **Why recommended:**
  - It covers the highest-scoring problem (#1, score 80), and the gap is real.
  - It doesn't require running a production SLA.
  - It fits open source, self-hosting and offline CI.
  - It gets stronger as AI-written handler code increases (S1, S32).
  - The adjacent tools are early enough to lead the category.
- **Weaknesses:**
  - "Testing" is less urgent than "debugging". People search for "stripe webhook signature failed", not "webhook test harness".
  - Entry content must meet them at the debugging pain and convert them to verification.
- **What would change my mind:**
  1. Interviews show developers don't care about duplicates or ordering until they've been burned, *and* won't adopt a tool to prevent it.
  2. First-run data shows less than 20% of runs produce a finding users call real.
  3. Hookdeck or Stripe ships adversarial scenarios with state verdicts before we launch.
  4. The state probe proves too heavy to set up for most users.

## Option 2: "Production webhook observability for consumers"

- **Category:** webhook monitoring (receiving side).
- **Statement:** *Webhook Trap watches every provider's webhooks into your app and tells you, before your customers do, when they stop, fail or change shape.*
- **Working lines:** "Silence is an incident." / "Know before the warning email."
- **Target:** Owner / on-call.
- **Against:** provider emails (late, one provider at a time, S7, S8); Hookdeck Event Gateway (needs all traffic through it); ShopHooks and FlareCanary (single-purpose, S11, S18).
- **Strengths:** the strongest willingness to pay, a clear and urgent story, strong evidence (S7, S9, S11, S12, S18).
- **Weaknesses:**
  - Trust: someone has to put us in, or beside, production.
  - Running an SLA or provider integrations is heavy for a small team (concepts.md C).
  - Value takes hours to days, because a baseline is needed.
  - Head-on with funded infrastructure.
- **Choose this if:** interviews show Owners would pay *now*, or the PO wants a B2B team product from day one.

## Option 3: "The open-source webhook debugger that explains itself"

- **Category:** webhook debugging (established).
- **Statement:** *Capture any webhook, see why it fails (signature, body, URL, secret), and resend it to localhost correctly signed. Open source, self-hostable.*
- **Working lines:** "Know why it failed." / "Signature mismatch, explained."
- **Target:** Integrator, Self-hoster.
- **Against:** webhook.site (closed core for paid features, public-by-ID data, S34); ngrok (edited replays break signatures, S24); provider CLIs (single provider).
- **Strengths:**
  - The search demand already exists.
  - Fastest time to value.
  - Lowest build risk.
  - Fits the ADRs as written.
- **Weaknesses:**
  - The most crowded category (S42).
  - Differentiation rests on diagnosis quality alone.
  - Weak monetisation.
  - Easy to copy.
- **Choose this if:** the PO wants the smallest credible 2.0 and accepts competing on craft and open source rather than on category.

---

## Recommendation and argument

**Choose Option 1, and borrow Option 3's language for acquisition.**

- **Acquisition layer:** landing pages and docs answer the debugging questions people actually search ("Stripe signature mismatch", "Shopify webhook duplicate", "test webhooks locally"). The free capture and signature diagnosis meets them there.
- **Conversion layer:** the first event shows a single action, *"Prove this handler"*. That is the point where Webhook Trap stops being one more inspector.
- **Retention layer:** fixtures and scenarios in the repo, and a CI check. That is a habit and a reason to come back that capture tools don't have.
- **Expansion:** the same fixtures and baselines lead into Option 2 (Horizon 2). That is where team revenue sits.

This sequence turns the crowded entry point into a funnel instead of a fight. It builds a category position that is defensible through real-traffic packs and community. It postpones the costly production commitments until the user base justifies them.

## Message hierarchy (for brand and landing)

1. **Problem:** "Providers deliver twice, out of order, late, and retry. Your test sent it once."
2. **Proof:** a live demo of one real event delivered twice, with the handler's state diverging.
3. **Promise:** "Replay real events the way providers do. Get a verdict on your state."
4. **Reassurance:** open source, self-hostable, secrets never leave your machine, works with your coding agent.
5. **Path:** "Get a URL. No sign-up." → "Run your first proof in 5 minutes." → "Add to CI."
