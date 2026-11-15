# Users and Contexts

**Status:** PROPOSED for PO review · **Author:** A01 · **Date:** 2026-11-11
These are contexts grounded in the research (S# → [research.md](research.md#1-sources)), not invented demographics. Each one should be checked in 5–8 interviews before any build decision.

## 1. The Integrator: connecting a provider to their app

- **Who:** a backend or full-stack developer, often solo or on a small team, wiring up Stripe, Shopify, GitHub, Twilio, Slack or an AI platform's webhooks. Increasingly they work with a coding agent.
- **Context:** a laptop, the app on `localhost`, the provider in test mode, a few days to ship.
- **Trigger moments:** the first event never arrives; "signature mismatch"; "works locally, 403 in staging" (S3, S19).
- **Tools today:** a provider CLI (S5, S17), ngrok or a relay (S14, S45), a capture site (S34), curl.
- **What they don't know they need:** duplicate, reordered and late deliveries (S1, S10, S21).
- **Success:** "I'm confident this handler is correct, not just that it returned 200 once."
- **Frequency:** high. Every new integration, every major refactor.

## 2. The Owner: production owner or on-call for a webhook-dependent app

- **Who:** an engineer on a SaaS or e-commerce team whose revenue or data sync depends on 3–10 providers.
- **Context:** production. Failures cost orders, invoices, or customer trust.
- **Trigger moments:** a provider warning email (S7, S8); a missing order reported by a customer (S11); a subscription found deleted (S9); a field silently gone (S12, S18).
- **Tools today:** provider dashboards one at a time, generic APM and uptime checks, home-made reconciliation jobs (S13).
- **Success:** "We hear about it before customers do, and we can recover what was missed."
- **Frequency:** low per person, very high cost per incident.

## 3. The Agent-assisted developer (emerging)

- **Who:** an Integrator whose handler code is largely written by an AI coding agent. Stripe's webhook docs now open with agent skills (S1), and Hookdeck exposes webhooks to agents through MCP (S32).
- **Context:** code arrives fast and is reviewed lightly. The agent needs machine-readable feedback ("this handler double-processes on duplicate delivery") to fix its own output.
- **Success:** the agent can trigger, observe and verify webhook behaviour without the human copying payloads around.
- **Note:** this is an *interface* to the other personas, not a separate market. It still changes what the product must expose: CLI, structured output, MCP.

## 4. The QA / CI owner

- **Who:** an engineer responsible for the test suite or the CI pipeline, often the Integrator wearing another hat.
- **Context:** tests must be deterministic and offline, with no provider CLI and no public URL (S41).
- **Pain:** fixtures are hand-written and unsigned, drift from real payloads (S41, S18), and never cover duplicates or reordering.
- **Success:** "Real captured events, signed correctly, replayed as adversarial sequences in every PR, and a failing check when the handler regresses."

## 5. The Platform engineer: sending webhooks to customers

- **Who:** an engineer at an API company building or maintaining outbound webhooks.
- **Context:** customers point webhooks at arbitrary URLs. The team handles retries, signing, SSRF and versioning (S26, S37, S38).
- **Pain:** "did you send it?" tickets (S35, S36); no way to rehearse misbehaving receivers (slow, 5xx, redirects, TLS errors) before release.
- **Tools today:** Svix, Hookdeck Outpost, Convoy, or something home-made.
- **Success:** "Our sender passes a conformance run (retry schedule, signatures, timeouts, SSRF refusal) before every release."
- **Market size:** smaller and more B2B. Well served on infrastructure.

## 6. The Self-hoster / privacy-bound team

- **Who:** teams in regulated or privacy-sensitive settings, or open-source enthusiasts who won't send production payloads to a third party (S34, S43).
- **Context:** Docker on a VPS or inside a private network. Sometimes receivers are behind a firewall (S30).
- **Success:** "Same capabilities, data never leaves our network, one `docker compose up`."
- **Note:** a **distribution context** that overlaps every persona above. It is a strong fit for the open-source and self-hosted constraint (ADR-008).

## Contexts deliberately out of focus

- **No-code automation builders** (Zapier-style routing and transformations): a different job, served by webhook.site's paid workflows and automation platforms.
- **High-volume event routing infrastructure** (queues, fan-out, transformations at scale): served by Hookdeck Event Gateway and cloud event buses. Expensive to compete with.
- **Agencies managing many client stores:** plausible (Shopify volumes in S13), but the research found no direct evidence of a distinct workflow. Treat as an Owner variant until interviews say otherwise.

## Persona-to-problem map

| Problem (rank) | Integrator | Owner | Agent-assisted | QA/CI | Platform | Self-hoster |
| -------------- | :--------: | :---: | :------------: | :---: | :------: | :---------: |
| 1 Delivery semantics | ●● | ● | ●● | ●● | | ● |
| 2 Silent production failure | | ●● | | | ● | ● |
| 3 Signature diagnosis | ●● | ● | ●● | ● | ● | ● |
| 4 Contract drift | | ●● | | ● | | |
| 5 Reproduce real event | ● | ●● | ● | ● | | ● |
| 6 CI fixtures | ● | | ● | ●● | | |
| 7 Payload privacy | | ● | | | | ●● |
| 9 Sender testing | | | | | ●● | |
| 10 Localhost | ●● | | ●● | | | ● |

●● primary pain · ● secondary
