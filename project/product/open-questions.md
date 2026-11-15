# Open Questions and ADR Challenges

**Status:** PROPOSED for PO review · **Author:** A01 · **Date:** 2026-11-11
Nothing here is decided. Each item lists the options and A01's leaning, with the reason.

## Part 1: Decisions for the PO

| # | Question | Options | A01 leaning | Why it matters |
| - | -------- | ------- | ----------- | -------------- |
| Q1 | **Which job defines 2.0?** | Prove handlers (B) / watch production (C) / debug deliveries (A) | **B**, with A as the first minute | Every later decision follows from this (positioning.md) |
| Q2 | **Is the local agent / CLI part of the MVP?** | Yes / 2.1 / never | **Yes.** It is the product's hands for localhost, signing and CI | Changes the scope and the ADR-006 timeline |
| Q3 | **Agent language and distribution** | Go single binary / Node via `npx` / PHP phar | Go binary, plus an `npx` shim for web developers | Install friction vs maintainability. It is a second codebase either way |
| Q4 | **Where do signing secrets live?** | Agent only / also on self-hosted (encrypted) / also on hosted | Agent always. Self-hosted optional. **Never hosted** | Trust and security posture. Affects re-signing features |
| Q5 | **How much setup is acceptable for verdicts?** | Transport only / plus optional state probe / require probe | Transport by default, probe opt-in | If transport-only findings feel weak, the bet fails (vision kill criteria) |
| Q6 | **Open-source boundary** | Everything open / open core (hosted adds teams and history) / source-available | Open core: engine, agent, packs and self-host all open | Distribution vs revenue |
| Q7 | **Launch providers** | Stripe only / Stripe + GitHub + Shopify / plus Twilio, Slack, Standard Webhooks | Stripe, GitHub, Shopify, plus generic Standard Webhooks | Each pack carries maintenance cost. Standard Webhooks covers AI platforms (S27, S28) |
| Q8 | **Will 2.0 ever sit in the production path?** | Never / opt-in later (C1) / sidecar only (C2) | Sidecar first in Horizon 2. Inline only if users ask | Determines the uptime commitment and architecture |
| Q9 | **Guest scope** | Capture only / capture plus proving runs / full | Capture plus proving runs, with strict quotas | The aha must be possible without sign-up |
| Q10 | **Producer side (Concept D)** | In 2.0 / Horizon 3 / never | Horizon 3, possibly a free public grader for marketing | Small market, different runtime |
| Q11 | **AI-agent interface at launch** | MCP plus JSON / JSON only / later | JSON from day one, MCP in the first release | Strong "why now" (S1, S32), and cheap if the CLI is designed for it |
| Q12 | **Name and brand fit** | Keep "Webhook Trap" / rename | For the brand work (A02), but note that "Trap" suggests capture more than proof | The name should carry the category |
| Q13 | **Validation before build** | Interviews only / interviews plus a CLI prototype / build | 6–8 Integrator interviews plus a 2-week CLI prototype measuring the finding rate | Cheapest way to test the Q5 risk |

### Interview script focus (for Q13)

1. When did a webhook handler last fail in production? What was the cause? How did you find out?
2. Have duplicates, ordering, or timeouts ever caused a bug? Did you test for them?
3. How do you test webhook handlers today (fixtures, provider CLI, nothing)?
4. Would you add one line of config (a state probe) to get state-level verdicts?
5. Would you let a tool write fixtures into your repo? What must be scrubbed?
6. Who on your team would pay, and for what: history, teams, CI, production alerts?

## Part 2: ADRs these concepts challenge

The ADRs are constraints. These are flags for the PO and the architecture owner, not decisions.

### ADR-006 (Secure Replay Engine): **challenged by A and B**

- **Tension 1, timeline:** the ADR places localhost replay through a local agent in 2.1 and allows public destinations only on the hosted MVP. For Concepts A and B, the agent is the core of the product, not a later add-on. Without it, the aha (M6 in core-experience.md) cannot happen on the hosted service.
- **Tension 2, where replay runs:** the ADR models replay as server-side queued jobs. Proving runs need millisecond timing (two deliveries 50 ms apart, bursts), offline CI, and local re-signing. That means **the scenario engine runs in the agent**. The server orchestrates and stores results. Server-side replay stays for public destinations.
- **Tension 3, secrets:** re-signing needs the provider signing secret. The ADR forbids forwarding *captured* auth secrets, which is compatible. A new rule is needed: **signing secrets are configured only in the agent or the self-hosted instance**.
- **Ask:** a new ADR, "Local agent and client-side scenario engine", with an A09 threat model. It covers agent authentication and pairing (including guests), job integrity, restricting the agent to the targets the user configured, and protocol versioning.

### ADR-002 (Laravel + Inertia + React): **challenged by B and D**

- B needs a distributable agent and CLI. That is a second runtime (Go or Node), not PHP. The decision "one primary application deployment" still holds for the server, but the product is now **server plus agent**.
- D needs a receiver that holds slow connections for 30 s or more. Under PHP-FPM that exhausts workers. It needs a non-blocking runtime (Go/Node, or Octane/FrankenPHP).
- **Ask:** confirm that a non-PHP agent is acceptable. Defer D's runtime question to Horizon 3.

### ADR-004 (Inertia Live Props + Reverb): **clarification needed for B**

- The capture stream fits "latest N" live props. The **run timeline** produces many fine-grained updates (rows, latency, digests) in a few seconds. One partial reload per viewer per event (the ADR-004 amendment) could cause a storm.
- **Ask:** allow run timelines to use direct Echo broadcast events with small, non-sensitive payloads (run ID, row index, status), and keep Live Props for lists. Spike this in A08's Live Props work.

### ADR-005 (Raw HTTP Preservation): **raises the bar for A and B**

- Signature diagnosis needs the exact raw body (covered), the **raw request target including the query string as received**, and, for URL-signing providers, the public URL as the provider saw it (scheme and host behind proxies) (S19, S20).
- Header order matters less for signatures, but header *presence and case* can matter for diagnosis. The ADR's note that PHP-FPM may lose order and casing is acceptable if it is flagged in the UI.
- **Ask:** add "public URL as seen" and "raw request target" to the capture attributes. Decide whether ingest should run on a runtime that exposes raw headers. That is an architecture call, not a product one.

### ADR-003 (Hybrid Anonymous and Authenticated Access): **compatible, with additions**

- Guests must be able to pair an agent (a one-time code bound to the guest session) and run limited proving runs (Q9).
- Short guest retention matters less in B, because fixtures persist in the user's repo.
- Concept C would need teams and roles in the MVP (the ADR defers collaborators).

### ADR-001 (Modular Monolith): **domain list update**

- B adds domains: **Scenario** (catalogue, packs), **Run/Verdict**, **Agent** (pairing, sessions), **Fixture** (scrub rules, shape baselines).
- "Monitoring" and "Delivery" (in the forwarding sense) belong to Horizon 2. Keep them as names only until C is chosen.

### ADR-007 / ADR-008 (Queues, Redis, self-hosting): **mostly unaffected by B, strained by C**

- B moves heavy replay work to the agent, which lowers server queue load. The minimal self-host profile stays realistic.
- C1 (inline relay) would need guaranteed forwarding (the outbox the ADR defers), high-availability ingest and long retention. That strains the minimal profile and makes Redis mandatory.

## Part 3: Things I could not verify

- Market size and willingness to pay for any concept (no reliable public data).
- Whether level-1 (transport-only) findings are frequent enough to create the aha. This needs the Q13 prototype.
- How well the 2026 OSS chaos tools (S39, S40) are being adopted. Neither post states adoption, and repository stars were not checked.
- Exact provider policies flagged in [research.md § 6](research.md#6-unverified-or-conflicting): Shopify retry count, Stripe auto-disable timing, webhook.site free request cap.
