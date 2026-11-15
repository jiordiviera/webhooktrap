# Realistic Data: Webhook Trap 2.0 design round

**Status:** PROPOSED · **Author:** A03 (UX) · **Date:** 2026-11-11, regenerated 2026-11-14 (decision 35)
**Machine-readable twin:** [`realistic-data.json`](realistic-data.json) holds exactly the same content (generated from one source, so values match byte for byte).

Every territory (Timetable, Crash Lab, Notation) must use **this** dataset on every screen, so the PO compares design, not content. Don't invent other ids, numbers or copy; if a screen needs something missing here, ask A03 to add it.

> Company, people, ids, hashes and signatures are fictional. Signatures are correctly formatted but derived from sha256 of fixed seeds, not from any secret. Emails use example.com / .example domains.
> 
> All times are UTC in data. The UI renders in the viewer's time zone; screens in this round use UTC to stay comparable. Domain webhooktrap.dev, the npm package @webhooktrap/agent and the command webhooktrap are provisional until Q12 (brand) settles (C-1, decision 35). Headers are Webhooktrap-*, the config file webhooktrap.yml.
> 
> Regenerated 2026-11-14 for decision 35 (A01 scenarios-and-judge §16 changes 1 to 14; C-4: Run #3 has 16 deliveries), then for decisions 38 and 39 (PV-1 to PV-14; PO-A, PO-C to PO-F). The change table is at the end of this file.

## 1. The world

| | |
| - | - |
| Company | **Brindle Coffee Co.**: A small specialty coffee roaster: Shopify storefront, Stripe for monthly bean subscriptions, Parcelbird (fictional, Standard Webhooks) for shipping labels, code on GitHub. |
| User | Maya Lindqvist (`maya-l`, maya@brindlecoffee.example): Solo backend developer, uses a coding agent |
| Project | **Brindle Coffee** · `prj_01M6E8V2NMYST3J47AT3XH5NSA` · capture URL `https://webhooktrap.dev/c/Br7nDLk2Qm9x` |
| Guest variant | capture URL `https://webhooktrap.dev/c/k7Qm2xVb9TfL` · expires in 23 h 49 m · 13/100 deliveries · 1/10 runs. Guest limits APPROVED (A-13, decision 35): 24 h from URL creation, 100 stored deliveries, 10 runs per session (a run counts once it gets past Once), 1 agent at a time (pairing a second replaces the first). |
| Agent | **`maya-mbp`** · v0.9.2 · macOS 15.4 (arm64) · connected since 13:55:53.212 · target `http://localhost:3000/webhooks/stripe` · repo `~/code/brindle-app` |
| Pairing | code `7KQ2M-XR9TD` (read as "7 K Q 2 M, X R 9 T D") · `npx @webhooktrap/agent@1 pair 7KQ2M-XR9TD` · terminal: `$ npx @webhooktrap/agent@1 pair 7KQ2M-XR9TD` / `Server     https://webhooktrap.dev` / `Workspace  Guest session · expires 2026-11-12 13:55 UTC` / `Join? Only continue if you created this code in your own browser just now. [y/N] y` / `Joined as maya-mbp. Leave this terminal open while you run.` · on N: `Join? Only continue if you created this code in your own browser just now. [y/N] n` / `Not joined. This agent is not paired, and the code is used up.` · after a claim: `This agent's workspace changed. Check it before it accepts jobs.` / `Server     https://webhooktrap.dev` / `Workspace  Maya Lindqvist (maya@brindlecoffee.example)` / `Continue with this workspace? [y/N] y` / `Confirmed. Target approvals were cleared; the first run to each target asks again.` |
| Examples | public target for server-side replay `https://staging.brindlecoffee.example/webhooks/shopify` · refused private target `http://10.0.4.12:3000/webhooks/shopify` · CI upload token `github-actions` (shown by name only) · claimed guest project 'Stripe project' |
| Secrets on the agent (names only, never values) | Stripe: `STRIPE_WEBHOOK_SECRET` (set) · GitHub: `GITHUB_WEBHOOK_SECRET` (set) · Shopify: `SHOPIFY_API_SECRET` (set) · Parcelbird: not set |

**Provider budgets used by the UI** (response-time budget drawn as the budget line/band/ruler/clock; every number carries its source tag):

| Provider | Signature scheme | Budget | Source | UI label | Tolerance | Pack | Note | Deliveries in stream |
| -------- | ---------------- | ------ | ------ | -------- | --------- | ---- | ---- | -------------------- |
| Stripe | Stripe-Signature v1 | 10 000 ms | default | 10 s budget · Webhook Trap default | 300 s | mvp | Stripe publishes no timeout. Webhook Trap default budget. | 5 |
| Shopify | X-Shopify-Hmac-Sha256 | 5 000 ms | Shopify docs | 5 s · Shopify limit | none | mvp | Documented: 5 s. Retry policy: 8 attempts in 4 h. | 3 |
| GitHub | X-Hub-Signature-256 | 10 000 ms | GitHub docs | 10 s · GitHub limit | none | mvp | Documented: 10 s. No automatic retries. | 2 |
| Parcelbird (Standard Webhooks) | webhook-signature v1 | 10 000 ms | default | 10 s budget · Webhook Trap default | 300 s | mvp (scheme) | Fictional provider on the Standard Webhooks scheme pack. The spec's 15–30 s is guidance for senders, not a consumer limit (BUD-1). | 2 |
| Unknown | — | 10 000 ms | default | 10 s budget · Webhook Trap default | none | none | Capture and replay only: sent as captured; Late, Tampered and Unsigned n/a (SIGN-2). | 1 |

Coming (shown as names only, no budget anywhere in the product until their pack ships, A-18): Twilio, Slack.

## 2. Capture stream: 13 deliveries in 7 groups

Newest first is how the stream shows them; the table below is in arrival order. Times are `received_at` (UTC). Sizes are the exact byte length of the bodies in §3.

| # | Received | Provider | Event type | Group | Size | Signature | Shape |
| - | -------- | -------- | ---------- | ----- | ---- | --------- | ----- |
| #01 | 13:58:04.212 | Stripe | `invoice.paid` | sub_1QH…4drI | 2 294 B | Signature valid | Shape matches baseline |
| #02 | 13:58:04.395 | Stripe | `invoice.payment_succeeded` | sub_1QH…4drI | 2 307 B | Signature valid | Shape matches baseline |
| #03 | 13:58:04.823 | Stripe | `payment_intent.succeeded` | sub_1QH…4drI | 915 B | Signature valid | Shape matches baseline |
| #04 | 13:58:05.416 | Stripe | `customer.subscription.updated` | sub_1QH…4drI | 1 496 B | Signature valid | Shape matches baseline |
| #05 | 13:59:12.614 | Shopify | `orders/create` | Order #1042 | 1 064 B | Signature valid | New shape (first capture) |
| #06 | 13:59:13.229 | Shopify | `orders/paid` | Order #1042 | 1 064 B | Signature valid | New shape (first capture) |
| #07 | 14:00:16.089 | GitHub | `push` | brindle/brindle-app | 1 287 B | Signature valid | Shape matches baseline |
| #08 | 14:01:11.442 | Parcelbird | `shipment.label_created` | shp_1c263…ztsP | 356 B | No secret | New shape (first capture) |
| #09 | 14:02:08.731 | GitHub | `pull_request` | brindle/brindle-app | 686 B | Signature invalid | Shape matches baseline |
| #10 | 14:03:05.876 | Shopify | `orders/create` | Order #1043 | 939 B | Signature valid | Shape matches baseline |
| #11 | 14:03:22.302 | Stripe | `customer.subscription.updated` | sub_1QG…qS1C | 1 418 B | Signature valid | Shape differs from baseline |
| #12 | 14:04:46.723 | Parcelbird | `shipment.in_transit` | shp_1c263…ztsP | 281 B | No secret | New shape (first capture) |
| #13 | 14:06:39.219 | Unknown | `(generic POST)` | Ungrouped | 52 B | Unsigned | No baseline |

### Groups (related object)

| Group | Provider | Subtitle | Deliveries | Spread | Note |
| ----- | -------- | -------- | ---------- | ------ | ---- |
| **sub_1QH…4drI** (`Subscription sub_1QH3UF1HXb5oPQT3wfdt4drI`) | Stripe | Jonah Reyes · Monthly whole bean · renewal | #01, #02, #03, #04 | 1.2 s | Suggested for Prove |
| **Order #1042** (`Order #1042 (5873201946743)`) | Shopify | Ana Mbeki · $37.00 · paid | #05, #06 | 0.6 s |  |
| **shp_1c263…ztsP** (`Shipment shp_1c263SsdQymuOjhjztsP`) | Parcelbird | Order ref #1042 · label created, in transit | #08, #12 | 3 min 35 s | No pack for Parcelbird: grouped on data.shipment_id (GRP-4). Not linked to Order #1042: cross-provider groups are post-MVP (GRP-5). |
| **brindle/brindle-app** (`Repository brindle/brindle-app`) | GitHub | push · pull_request | #07, #09 | 1 min 53 s |  |
| **Order #1043** (`Order #1043 (5873204390011)`) | Shopify | Lee Okonkwo · $24.00 | #10 | — |  |
| **sub_1QG…qS1C** (`Subscription sub_1QGutsMKKaWLefy3yMsaqS1C`) | Stripe | Cancellation scheduled | #11 | — |  |
| **Ungrouped** (`Ungrouped`) | Unknown | Requests with no shared object | #13 | — |  |

### The signature-invalid delivery (#09)

- **Label:** Signature invalid · **Reason:** Signed with a different secret
- **Detail:** X-Hub-Signature-256 did not match. Checked 1 secret: GITHUB_WEBHOOK_SECRET on agent maya-mbp. The body is intact (7 of 7 checks: Content-Length matches, valid UTF-8, no BOM, no re-encoding detected, no signed timestamp (GitHub), header well-formed, scheme known), so the secret in this repository's webhook settings differs from yours.
- **Fix:** Copy the secret from GitHub › brindle/brindle-app › Settings › Webhooks › 512839104 into GITHUB_WEBHOOK_SECRET on your machine, then press Re-check.

Other signature states in the stream: **No secret** (#08, #12 Parcelbird: This delivery is signed (webhook-signature v1) but no secret is set for Parcelbird on agent maya-mbp, so it can't be checked.) and **Unsigned** (#13 curl: No signature header found and no known provider fingerprint (User-Agent curl/8.7.1). Treated as a generic request.). *Unknown scheme* and *Not checked (no agent)* appear only as state copy in §6.

## 3. Every delivery in full

Headers are listed **in received order**, exactly as they should appear on the Raw tab. Bodies are the exact bytes (Stripe sends pretty-printed JSON; the others send compact JSON). Bodies are real-shaped but trimmed to the fields that matter.

### #01 · Stripe · `invoice.paid`

- **Delivery id:** `dlv_01M75EDHNMT8Z3WPK3CGQ054WW` · **received** `2026-11-11T13:58:04.212Z` · **ingest** 56 ms (provider POST → on screen)
- **Request line:** `POST /c/Br7nDLk2Qm9x` · **raw query:** (none)
- **Provider event id:** `evt_1QK9BpyOmOFtoVkdOlN4pjPU`
- **Signature:** Signature valid · Stripe v1 (HMAC-SHA256): v1 matched with secret STRIPE_WEBHOOK_SECRET on agent maya-mbp. Timestamp 0.2 s old, tolerance 300 s.
- **Shape:** Shape matches baseline: invoice.paid · API 2024-06-20 · 41 fields, same as 6 earlier captures
- **Decoded summary:** Invoice BRNDL-0042 paid: $24.00 USD · Customer cus_… Jonah Reyes (jonah.reyes@example.com) · Subscription sub_1QH3UF1H… · billing_reason subscription_cycle · Period Oct 11 → Nov 11, 2026

```http
POST /c/Br7nDLk2Qm9x HTTP/1.1
Host: webhooktrap.dev
User-Agent: Stripe/1.0 (+https://stripe.com/docs/webhooks)
Content-Length: 2294
Accept: */*; q=0.5, application/xml
Cache-Control: no-cache
Content-Type: application/json; charset=utf-8
Stripe-Signature: t=1794405484,v1=e8bc163c82eee18733288c7d4ac636db3a6deb013ef2d37b68322be20edc45cc
Accept-Encoding: gzip

{
  "id": "evt_1QK9BpyOmOFtoVkdOlN4pjPU",
  "object": "event",
  "api_version": "2024-06-20",
  "created": 1794405483,
  "data": {
    "object": {
      "id": "in_1QKImRcZJ8HR9BZYostEV4ui",
      "object": "invoice",
      "account_country": "US",
      "account_name": "Brindle Coffee Co.",
      "amount_due": 2400,
      "amount_paid": 2400,
      "amount_remaining": 0,
      "attempt_count": 1,
      "billing_reason": "subscription_cycle",
      "charge": "ch_3QKQSfbDEoGWoz5h9BH30pt1",
      "collection_method": "charge_automatically",
      "created": 1794405482,
      "currency": "usd",
      "customer": "cus_RLwECeeQJ3SXHr",
      "customer_email": "jonah.reyes@example.com",
      "customer_name": "Jonah Reyes",
      "hosted_invoice_url": "https://invoice.stripe.com/i/acct_1Pzc49RWKdUzp4lH/test_5XWMojZxxCsr36dv66c0R3qVrgDFk6VN9FgoVMQu",
      "lines": {
        "object": "list",
        "data": [
          {
            "id": "il_1QKSKkpwf4w9hEY1TqQPBmyg",
            "object": "line_item",
            "amount": 2400,
            "currency": "usd",
            "description": "1 \u00d7 Brindle Monthly, Whole Bean (at $24.00 / month)",
            "period": {
              "end": 1796997451,
              "start": 1794405451
            },
            "price": {
              "id": "price_1PZ9vRSRGb251gkl9ZzLRVR1",
              "object": "price",
              "unit_amount": 2400,
              "recurring": {
                "interval": "month",
                "interval_count": 1
              }
            },
            "quantity": 1,
            "subscription": "sub_1QH3UF1HXb5oPQT3wfdt4drI",
            "type": "subscription"
          }
        ],
        "has_more": false,
        "total_count": 1,
        "url": "/v1/invoices/in_1QKImRcZJ8HR9BZYostEV4ui/lines"
      },
      "livemode": false,
      "number": "BRNDL-0042",
      "paid": true,
      "payment_intent": "pi_3QK4RAqNlKszZLwoglztTHYj",
      "period_end": 1794405451,
      "period_start": 1791727051,
      "status": "paid",
      "subscription": "sub_1QH3UF1HXb5oPQT3wfdt4drI",
      "subtotal": 2400,
      "total": 2400
    }
  },
  "livemode": false,
  "pending_webhooks": 1,
  "request": {
    "id": null,
    "idempotency_key": "in_auto_RTmsWZwV3q"
  },
  "type": "invoice.paid"
}
```

### #02 · Stripe · `invoice.payment_succeeded`

- **Delivery id:** `dlv_01M75EDHVBW8KXW2KYRV2N9Y3Z` · **received** `2026-11-11T13:58:04.395Z` · **ingest** 69 ms (provider POST → on screen)
- **Request line:** `POST /c/Br7nDLk2Qm9x` · **raw query:** (none)
- **Provider event id:** `evt_1QKhBkXKPjZJLH96U5fOLvMc`
- **Signature:** Signature valid · Stripe v1 (HMAC-SHA256): v1 matched with secret STRIPE_WEBHOOK_SECRET on agent maya-mbp. Timestamp 0.2 s old, tolerance 300 s.
- **Shape:** Shape matches baseline: invoice.payment_succeeded · 41 fields
- **Decoded summary:** Invoice BRNDL-0042 payment succeeded: $24.00 USD · Same invoice as #01

```http
POST /c/Br7nDLk2Qm9x HTTP/1.1
Host: webhooktrap.dev
User-Agent: Stripe/1.0 (+https://stripe.com/docs/webhooks)
Content-Length: 2307
Accept: */*; q=0.5, application/xml
Cache-Control: no-cache
Content-Type: application/json; charset=utf-8
Stripe-Signature: t=1794405484,v1=ad328846aa18b32a335816374511cac1063c704b8c57999e51da9f908290a7a4
Accept-Encoding: gzip

{
  "id": "evt_1QKhBkXKPjZJLH96U5fOLvMc",
  "object": "event",
  "api_version": "2024-06-20",
  "created": 1794405483,
  "data": {
    "object": {
      "id": "in_1QKImRcZJ8HR9BZYostEV4ui",
      "object": "invoice",
      "account_country": "US",
      "account_name": "Brindle Coffee Co.",
      "amount_due": 2400,
      "amount_paid": 2400,
      "amount_remaining": 0,
      "attempt_count": 1,
      "billing_reason": "subscription_cycle",
      "charge": "ch_3QKQSfbDEoGWoz5h9BH30pt1",
      "collection_method": "charge_automatically",
      "created": 1794405482,
      "currency": "usd",
      "customer": "cus_RLwECeeQJ3SXHr",
      "customer_email": "jonah.reyes@example.com",
      "customer_name": "Jonah Reyes",
      "hosted_invoice_url": "https://invoice.stripe.com/i/acct_1Pzc49RWKdUzp4lH/test_5XWMojZxxCsr36dv66c0R3qVrgDFk6VN9FgoVMQu",
      "lines": {
        "object": "list",
        "data": [
          {
            "id": "il_1QKSKkpwf4w9hEY1TqQPBmyg",
            "object": "line_item",
            "amount": 2400,
            "currency": "usd",
            "description": "1 \u00d7 Brindle Monthly, Whole Bean (at $24.00 / month)",
            "period": {
              "end": 1796997451,
              "start": 1794405451
            },
            "price": {
              "id": "price_1PZ9vRSRGb251gkl9ZzLRVR1",
              "object": "price",
              "unit_amount": 2400,
              "recurring": {
                "interval": "month",
                "interval_count": 1
              }
            },
            "quantity": 1,
            "subscription": "sub_1QH3UF1HXb5oPQT3wfdt4drI",
            "type": "subscription"
          }
        ],
        "has_more": false,
        "total_count": 1,
        "url": "/v1/invoices/in_1QKImRcZJ8HR9BZYostEV4ui/lines"
      },
      "livemode": false,
      "number": "BRNDL-0042",
      "paid": true,
      "payment_intent": "pi_3QK4RAqNlKszZLwoglztTHYj",
      "period_end": 1794405451,
      "period_start": 1791727051,
      "status": "paid",
      "subscription": "sub_1QH3UF1HXb5oPQT3wfdt4drI",
      "subtotal": 2400,
      "total": 2400
    }
  },
  "livemode": false,
  "pending_webhooks": 1,
  "request": {
    "id": null,
    "idempotency_key": "in_auto_f4SmJftN9k"
  },
  "type": "invoice.payment_succeeded"
}
```

### #03 · Stripe · `payment_intent.succeeded`

- **Delivery id:** `dlv_01M75EDJ8Q12B8NYR1S74TV0S0` · **received** `2026-11-11T13:58:04.823Z` · **ingest** 108 ms (provider POST → on screen)
- **Request line:** `POST /c/Br7nDLk2Qm9x` · **raw query:** (none)
- **Provider event id:** `evt_1QKJfXfXlhXK6gvKcqAYSoq2`
- **Signature:** Signature valid · Stripe v1 (HMAC-SHA256): v1 matched with secret STRIPE_WEBHOOK_SECRET on agent maya-mbp. Timestamp 0.2 s old, tolerance 300 s.
- **Shape:** Shape matches baseline: payment_intent.succeeded · 17 fields
- **Decoded summary:** PaymentIntent pi_3QK4RAqNl… succeeded: $24.00 USD · Card · invoice BRNDL-0042

```http
POST /c/Br7nDLk2Qm9x HTTP/1.1
Host: webhooktrap.dev
User-Agent: Stripe/1.0 (+https://stripe.com/docs/webhooks)
Content-Length: 915
Accept: */*; q=0.5, application/xml
Cache-Control: no-cache
Content-Type: application/json; charset=utf-8
Stripe-Signature: t=1794405484,v1=41242b9fae56fad4e6e77dfe33cb18d1c3fc583f988cf25ef9f2d9be0d440bbb
Accept-Encoding: gzip

{
  "id": "evt_1QKJfXfXlhXK6gvKcqAYSoq2",
  "object": "event",
  "api_version": "2024-06-20",
  "created": 1794405482,
  "data": {
    "object": {
      "id": "pi_3QK4RAqNlKszZLwoglztTHYj",
      "object": "payment_intent",
      "amount": 2400,
      "amount_received": 2400,
      "capture_method": "automatic",
      "created": 1794405482,
      "currency": "usd",
      "customer": "cus_RLwECeeQJ3SXHr",
      "description": "Subscription update",
      "invoice": "in_1QKImRcZJ8HR9BZYostEV4ui",
      "latest_charge": "ch_3QKQSfbDEoGWoz5h9BH30pt1",
      "livemode": false,
      "metadata": {},
      "payment_method": "pm_1PZRGz52LdKhQ8E5ocq7L2Yi",
      "payment_method_types": [
        "card"
      ],
      "status": "succeeded"
    }
  },
  "livemode": false,
  "pending_webhooks": 1,
  "request": {
    "id": null,
    "idempotency_key": "in_auto_HN133gxEnt"
  },
  "type": "payment_intent.succeeded"
}
```

### #04 · Stripe · `customer.subscription.updated`

- **Delivery id:** `dlv_01M75EDJV8Q9ET83TJ5ZN48R17` · **received** `2026-11-11T13:58:05.416Z` · **ingest** 140 ms (provider POST → on screen)
- **Request line:** `POST /c/Br7nDLk2Qm9x` · **raw query:** (none)
- **Provider event id:** `evt_1QKc6nBRpPBPPR9iTuwy4C8U`
- **Signature:** Signature valid · Stripe v1 (HMAC-SHA256): v1 matched with secret STRIPE_WEBHOOK_SECRET on agent maya-mbp. Timestamp 0.2 s old, tolerance 300 s.
- **Shape:** Shape matches baseline: customer.subscription.updated · 3 previous_attributes
- **Decoded summary:** Subscription sub_1QH3UF1H… renewed · current_period_end Nov 11 → Dec 11, 2026 · latest_invoice changed to BRNDL-0042

```http
POST /c/Br7nDLk2Qm9x HTTP/1.1
Host: webhooktrap.dev
User-Agent: Stripe/1.0 (+https://stripe.com/docs/webhooks)
Content-Length: 1496
Accept: */*; q=0.5, application/xml
Cache-Control: no-cache
Content-Type: application/json; charset=utf-8
Stripe-Signature: t=1794405485,v1=5b840157e7e86aef3b3fd0fc24f3add34d3e7f210370d429475ed1bcd3e7fca2
Accept-Encoding: gzip

{
  "id": "evt_1QKc6nBRpPBPPR9iTuwy4C8U",
  "object": "event",
  "api_version": "2024-06-20",
  "created": 1794405483,
  "data": {
    "object": {
      "id": "sub_1QH3UF1HXb5oPQT3wfdt4drI",
      "object": "subscription",
      "billing_cycle_anchor": 1789135051,
      "cancel_at_period_end": false,
      "collection_method": "charge_automatically",
      "currency": "usd",
      "current_period_end": 1796997451,
      "current_period_start": 1794405451,
      "customer": "cus_RLwECeeQJ3SXHr",
      "items": {
        "object": "list",
        "data": [
          {
            "id": "si_R1PuhlPNU75CXi",
            "object": "subscription_item",
            "price": {
              "id": "price_1PZ9vRSRGb251gkl9ZzLRVR1",
              "unit_amount": 2400,
              "recurring": {
                "interval": "month"
              }
            },
            "quantity": 1
          }
        ],
        "has_more": false,
        "total_count": 1
      },
      "latest_invoice": "in_1QKImRcZJ8HR9BZYostEV4ui",
      "livemode": false,
      "metadata": {
        "plan": "monthly-whole-bean"
      },
      "status": "active"
    },
    "previous_attributes": {
      "current_period_end": 1794405451,
      "current_period_start": 1791727051,
      "latest_invoice": "in_1Q9ypNfMcWfaq1SrOB1zPWie"
    }
  },
  "livemode": false,
  "pending_webhooks": 1,
  "request": {
    "id": null,
    "idempotency_key": "in_auto_tvto8RFf1t"
  },
  "type": "customer.subscription.updated"
}
```

### #05 · Shopify · `orders/create`

- **Delivery id:** `dlv_01M75EFMF6KJKFRCJDRRR6750T` · **received** `2026-11-11T13:59:12.614Z` · **ingest** 57 ms (provider POST → on screen)
- **Request line:** `POST /c/Br7nDLk2Qm9x` · **raw query:** (none)
- **Signature:** Signature valid · Shopify HMAC-SHA256 (base64): X-Shopify-Hmac-Sha256 matched with secret SHOPIFY_API_SECRET on agent maya-mbp.
- **Shape:** New shape (first capture): orders/create · API 2025-10 · baseline created from this delivery
- **Decoded summary:** Order #1042 · $37.00 USD · paid · 2 × Brindle House Blend 340 g, 1 × Sticker pack · Customer Ana Mbeki (ana.mbeki@example.com)

```http
POST /c/Br7nDLk2Qm9x HTTP/1.1
Host: webhooktrap.dev
User-Agent: Shopify-Captain-Hook
Content-Length: 1064
Accept: */*
Accept-Encoding: gzip;q=1.0,deflate;q=0.6,identity;q=0.3
Content-Type: application/json
X-Shopify-Api-Version: 2025-10
X-Shopify-Event-Id: 811b7ad9-c854-4d2f-ae64-84c4fc38d2d3
X-Shopify-Hmac-Sha256: 1r5/uJo5L+NCAz4+zP+crfxKWKGTFuFi4HnWYnYs6Lg=
X-Shopify-Shop-Domain: brindle-coffee.myshopify.com
X-Shopify-Topic: orders/create
X-Shopify-Triggered-At: 2026-11-11T13:59:12.003000Z
X-Shopify-Webhook-Id: ed0245a2-cbbb-478f-a0bd-241c1d85e8ac

{"id":5873201946743,"admin_graphql_api_id":"gid://shopify/Order/5873201946743","confirmation_number":"RIQ12NYMJ","contact_email":"ana.mbeki@example.com","created_at":"2026-11-11T09:59:11-05:00","currency":"USD","current_total_price":"37.00","email":"ana.mbeki@example.com","financial_status":"paid","fulfillment_status":null,"name":"#1042","order_number":1042,"processed_at":"2026-11-11T09:59:11-05:00","subtotal_price":"32.00","total_price":"37.00","total_shipping_price_set":{"shop_money":{"amount":"5.00","currency_code":"USD"}},"total_tax":"0.00","customer":{"id":7421093380215,"email":"ana.mbeki@example.com","first_name":"Ana","last_name":"Mbeki"},"line_items":[{"id":14820017561719,"price":"16.00","product_id":8840219623543,"quantity":2,"sku":"BHB-340","title":"Brindle House Blend, 340 g","variant_title":"Whole bean"},{"id":14820017594487,"price":"0.00","product_id":8840219656311,"quantity":1,"sku":"STICKER-01","title":"Sticker pack","variant_title":null}],"shipping_lines":[{"code":"USPS Ground Advantage","price":"5.00","title":"Standard shipping"}]}
```

### #06 · Shopify · `orders/paid`

- **Delivery id:** `dlv_01M75EFN2DC62AW2ZH0W1TJ1G0` · **received** `2026-11-11T13:59:13.229Z` · **ingest** 56 ms (provider POST → on screen)
- **Request line:** `POST /c/Br7nDLk2Qm9x` · **raw query:** (none)
- **Signature:** Signature valid · Shopify HMAC-SHA256 (base64): X-Shopify-Hmac-Sha256 matched with secret SHOPIFY_API_SECRET on agent maya-mbp.
- **Shape:** New shape (first capture): orders/paid · API 2025-10
- **Decoded summary:** Order #1042 paid · $37.00 USD

```http
POST /c/Br7nDLk2Qm9x HTTP/1.1
Host: webhooktrap.dev
User-Agent: Shopify-Captain-Hook
Content-Length: 1064
Accept: */*
Accept-Encoding: gzip;q=1.0,deflate;q=0.6,identity;q=0.3
Content-Type: application/json
X-Shopify-Api-Version: 2025-10
X-Shopify-Event-Id: f78c5cc7-5547-4335-a9a8-79d92cd7332e
X-Shopify-Hmac-Sha256: HFZBbhji/hLlXLjeirO7VN7eyUyUJSBAPM0ujcp7+NU=
X-Shopify-Shop-Domain: brindle-coffee.myshopify.com
X-Shopify-Topic: orders/paid
X-Shopify-Triggered-At: 2026-11-11T13:59:12.618000Z
X-Shopify-Webhook-Id: 1bc9d02c-a713-4ee6-ad24-0146f38575b2

{"id":5873201946743,"admin_graphql_api_id":"gid://shopify/Order/5873201946743","confirmation_number":"SCIX0B53Z","contact_email":"ana.mbeki@example.com","created_at":"2026-11-11T09:59:11-05:00","currency":"USD","current_total_price":"37.00","email":"ana.mbeki@example.com","financial_status":"paid","fulfillment_status":null,"name":"#1042","order_number":1042,"processed_at":"2026-11-11T09:59:11-05:00","subtotal_price":"32.00","total_price":"37.00","total_shipping_price_set":{"shop_money":{"amount":"5.00","currency_code":"USD"}},"total_tax":"0.00","customer":{"id":7421093380215,"email":"ana.mbeki@example.com","first_name":"Ana","last_name":"Mbeki"},"line_items":[{"id":14820017561719,"price":"16.00","product_id":8840219623543,"quantity":2,"sku":"BHB-340","title":"Brindle House Blend, 340 g","variant_title":"Whole bean"},{"id":14820017594487,"price":"0.00","product_id":8840219656311,"quantity":1,"sku":"STICKER-01","title":"Sticker pack","variant_title":null}],"shipping_lines":[{"code":"USPS Ground Advantage","price":"5.00","title":"Standard shipping"}]}
```

### #07 · GitHub · `push`

- **Delivery id:** `dlv_01M75EHJESMRKHCR1K4QQ7A13P` · **received** `2026-11-11T14:00:16.089Z` · **ingest** 93 ms (provider POST → on screen)
- **Request line:** `POST /c/Br7nDLk2Qm9x` · **raw query:** (none)
- **Signature:** Signature valid · GitHub X-Hub-Signature-256: sha256 matched with secret GITHUB_WEBHOOK_SECRET on agent maya-mbp.
- **Shape:** Shape matches baseline: push · 12 earlier captures
- **Decoded summary:** Push to brindle/brindle-app main by maya-l · 1 commit: feat(shipping): create Parcelbird label on invoice.paid · 6db7d80 → f395923

```http
POST /c/Br7nDLk2Qm9x HTTP/1.1
Host: webhooktrap.dev
User-Agent: GitHub-Hookshot/8a3f2c1
Content-Length: 1287
Accept: */*
Content-Type: application/json
X-GitHub-Delivery: c3581c41-6588-44bc-ab89-053c62094f2b
X-GitHub-Event: push
X-GitHub-Hook-ID: 512839104
X-GitHub-Hook-Installation-Target-ID: 873402155
X-GitHub-Hook-Installation-Target-Type: repository
X-Hub-Signature: sha1=1d5b1d0bc460ae40dd3383c602b3dac7aab32e1f
X-Hub-Signature-256: sha256=711430f6164e93803d93428bc1fab80f41e213bb197689307de8606d437c3038
Accept-Encoding: gzip

{"ref":"refs/heads/main","before":"6db7d803e74f1ffa7d8f5adc0bf95b3e15bf4c83","after":"f39592393ef0859cb196a52693d2cea00fb2df78","repository":{"id":873402155,"node_id":"R_kgDOMkLVqPzNLe","name":"brindle-app","full_name":"brindle/brindle-app","private":true,"html_url":"https://github.com/brindle/brindle-app","default_branch":"main"},"pusher":{"name":"maya-l","email":"maya@brindlecoffee.example"},"sender":{"login":"maya-l","id":51882734,"type":"User"},"created":false,"deleted":false,"forced":false,"compare":"https://github.com/brindle/brindle-app/compare/6db7d803e74f...f39592393ef0","commits":[{"id":"f39592393ef0859cb196a52693d2cea00fb2df78","tree_id":"dc9c5edb8b2d479e697b4b0b8ab874f32b325138","distinct":true,"message":"feat(shipping): create Parcelbird label on invoice.paid","timestamp":"2026-11-11T08:59:41-05:00","author":{"name":"Maya Lindqvist","email":"maya@brindlecoffee.example","username":"maya-l"},"added":["src/shipping/parcelbird.ts"],"removed":[],"modified":["src/webhooks/stripe.ts","package.json"]}],"head_commit":{"id":"f39592393ef0859cb196a52693d2cea00fb2df78","message":"feat(shipping): create Parcelbird label on invoice.paid","timestamp":"2026-11-11T08:59:41-05:00","author":{"name":"Maya Lindqvist","email":"maya@brindlecoffee.example","username":"maya-l"}}}
```

### #08 · Parcelbird · `shipment.label_created`

- **Delivery id:** `dlv_01M75EK8GJ7WQ8FXCRG6RBQN7W` · **received** `2026-11-11T14:01:11.442Z` · **ingest** 53 ms (provider POST → on screen)
- **Request line:** `POST /c/Br7nDLk2Qm9x` · **raw query:** (none)
- **Signature:** No secret · Standard Webhooks v1: This delivery is signed (webhook-signature v1) but no secret is set for Parcelbird on agent maya-mbp, so it can't be checked.
- **Shape:** New shape (first capture): shipment.label_created · Standard Webhooks payload
- **Decoded summary:** Label created for order #1042 · USPS Ground Advantage · 9400 1112 0621 5842 7061 31 · Rate $4.85

```http
POST /c/Br7nDLk2Qm9x HTTP/1.1
host: webhooktrap.dev
user-agent: Parcelbird-Webhooks/2.1
content-type: application/json
content-length: 356
webhook-id: msg_2pQxtbVXQpZuzuZFIoKZ2QIU2Lb
webhook-timestamp: 1794405671
webhook-signature: v1,9kVR/NbweCPLh5cc+5FEZCXaGChrOrHvk14MvXpp9oo=
accept-encoding: gzip

{"type":"shipment.label_created","timestamp":"2026-11-11T14:01:11.442Z","data":{"shipment_id":"shp_1c263SsdQymuOjhjztsP","order_ref":"#1042","carrier":"usps","service":"ground_advantage","tracking_number":"9400111206215842706131","label_url":"https://files.parcelbird.example/labels/TSvnqGi4nEMJfldjdWaqmtFM.pdf","rate":{"amount":"4.85","currency":"USD"}}}
```

### #09 · GitHub · `pull_request`

- **Delivery id:** `dlv_01M75EN0EVD5S8E52DNZENB12V` · **received** `2026-11-11T14:02:08.731Z` · **ingest** 109 ms (provider POST → on screen)
- **Request line:** `POST /c/Br7nDLk2Qm9x` · **raw query:** (none)
- **Signature:** Signature invalid · GitHub X-Hub-Signature-256: X-Hub-Signature-256 did not match. Checked 1 secret: GITHUB_WEBHOOK_SECRET on agent maya-mbp. The body is intact (7 of 7 checks: Content-Length matches, valid UTF-8, no BOM, no re-encoding detected, no signed timestamp (GitHub), header well-formed, scheme known), so the secret in this repository's webhook settings differs from yours.
- **Shape:** Shape matches baseline: pull_request.opened · 4 earlier captures
- **Decoded summary:** PR #87 opened by dev-tobi: Add subscription pause flow · feat/pause-subscription → main · 3 commits · +214 −18

```http
POST /c/Br7nDLk2Qm9x HTTP/1.1
Host: webhooktrap.dev
User-Agent: GitHub-Hookshot/8a3f2c1
Content-Length: 686
Accept: */*
Content-Type: application/json
X-GitHub-Delivery: 8694c201-ea83-468f-a7bb-02ed35114a26
X-GitHub-Event: pull_request
X-GitHub-Hook-ID: 512839104
X-GitHub-Hook-Installation-Target-ID: 873402155
X-GitHub-Hook-Installation-Target-Type: repository
X-Hub-Signature: sha1=21b76155b51b267626935bbf3af9391641e7e869
X-Hub-Signature-256: sha256=4d2f4b668cfc48e6059d36e91326bf87de39ba819f35450477bc1ce614a63bbd
Accept-Encoding: gzip

{"action":"opened","number":87,"pull_request":{"id":2190844731,"number":87,"state":"open","title":"Add subscription pause flow","user":{"login":"dev-tobi","id":60219455},"body":"Lets customers pause deliveries for up to 2 months.","html_url":"https://github.com/brindle/brindle-app/pull/87","head":{"ref":"feat/pause-subscription","sha":"84abb8a9207c14b2c2465c371e9334496685fd14"},"base":{"ref":"main","sha":"f39592393ef0859cb196a52693d2cea00fb2df78"},"draft":false,"commits":3,"additions":214,"deletions":18,"changed_files":7},"repository":{"id":873402155,"name":"brindle-app","full_name":"brindle/brindle-app","private":true},"sender":{"login":"dev-tobi","id":60219455,"type":"User"}}
```

### #10 · Shopify · `orders/create`

- **Delivery id:** `dlv_01M75EPR8MMCVCC1584KEVEKC0` · **received** `2026-11-11T14:03:05.876Z` · **ingest** 127 ms (provider POST → on screen)
- **Request line:** `POST /c/Br7nDLk2Qm9x` · **raw query:** (none)
- **Signature:** Signature valid · Shopify HMAC-SHA256 (base64): X-Shopify-Hmac-Sha256 matched with secret SHOPIFY_API_SECRET on agent maya-mbp.
- **Shape:** Shape matches baseline: orders/create · matches #05
- **Decoded summary:** Order #1043 · $24.00 USD · paid · 1 × Ethiopia Yirgacheffe 250 g, filter grind · Customer Lee Okonkwo (lee.okonkwo@example.com)

```http
POST /c/Br7nDLk2Qm9x HTTP/1.1
Host: webhooktrap.dev
User-Agent: Shopify-Captain-Hook
Content-Length: 939
Accept: */*
Accept-Encoding: gzip;q=1.0,deflate;q=0.6,identity;q=0.3
Content-Type: application/json
X-Shopify-Api-Version: 2025-10
X-Shopify-Event-Id: c86208c3-26a1-477f-ad35-43ceec609c57
X-Shopify-Hmac-Sha256: yx9ngnm9Ja/6haOyBRO2yUJPvBFFw3rr+yjPQZglbxU=
X-Shopify-Shop-Domain: brindle-coffee.myshopify.com
X-Shopify-Topic: orders/create
X-Shopify-Triggered-At: 2026-11-11T14:03:05.265000Z
X-Shopify-Webhook-Id: ac33d503-e1d7-4b20-ab3f-c5364c59ffb0

{"id":5873204390011,"admin_graphql_api_id":"gid://shopify/Order/5873204390011","confirmation_number":"YCJOVQVAA","contact_email":"lee.okonkwo@example.com","created_at":"2026-11-11T10:03:05-05:00","currency":"USD","current_total_price":"24.00","email":"lee.okonkwo@example.com","financial_status":"paid","fulfillment_status":null,"name":"#1043","order_number":1043,"processed_at":"2026-11-11T10:03:05-05:00","subtotal_price":"19.00","total_price":"24.00","total_shipping_price_set":{"shop_money":{"amount":"5.00","currency_code":"USD"}},"total_tax":"0.00","customer":{"id":7421093381192,"email":"lee.okonkwo@example.com","first_name":"Lee","last_name":"Okonkwo"},"line_items":[{"id":14820019331191,"price":"19.00","product_id":8840220016759,"quantity":1,"sku":"ETH-YIR-250","title":"Ethiopia Yirgacheffe, 250 g","variant_title":"Filter grind"}],"shipping_lines":[{"code":"USPS Ground Advantage","price":"5.00","title":"Standard shipping"}]}
```

### #11 · Stripe · `customer.subscription.updated`

- **Delivery id:** `dlv_01M75EQ89YEC07ZPNPJKEG95XX` · **received** `2026-11-11T14:03:22.302Z` · **ingest** 71 ms (provider POST → on screen)
- **Request line:** `POST /c/Br7nDLk2Qm9x` · **raw query:** (none)
- **Provider event id:** `evt_1QKyNQFjyRKwYy4CrPD9s5Ny`
- **Signature:** Signature valid · Stripe v1 (HMAC-SHA256): v1 matched with secret STRIPE_WEBHOOK_SECRET on agent maya-mbp. Timestamp 0.2 s old, tolerance 300 s.
- **Shape:** Shape differs from baseline: New field cancellation_details.feedback not in the saved fixture for customer.subscription.updated
- **Decoded summary:** Subscription sub_1QGutsMK… set to cancel at period end (Nov 28, 2026) · Feedback: too_expensive

```http
POST /c/Br7nDLk2Qm9x HTTP/1.1
Host: webhooktrap.dev
User-Agent: Stripe/1.0 (+https://stripe.com/docs/webhooks)
Content-Length: 1418
Accept: */*; q=0.5, application/xml
Cache-Control: no-cache
Content-Type: application/json; charset=utf-8
Stripe-Signature: t=1794405802,v1=3b96fc064fa874a80a132bda60bebf54efbc780a358fdcae4fbbd7e12b66b630
Accept-Encoding: gzip

{
  "id": "evt_1QKyNQFjyRKwYy4CrPD9s5Ny",
  "object": "event",
  "api_version": "2024-06-20",
  "created": 1794405801,
  "data": {
    "object": {
      "id": "sub_1QGutsMKKaWLefy3yMsaqS1C",
      "object": "subscription",
      "cancel_at": 1795857125,
      "cancel_at_period_end": true,
      "canceled_at": 1794405784,
      "cancellation_details": {
        "comment": null,
        "feedback": "too_expensive",
        "reason": "cancellation_requested"
      },
      "current_period_end": 1795857125,
      "current_period_start": 1793178725,
      "customer": "cus_RnRjMEoMuFDero",
      "items": {
        "object": "list",
        "data": [
          {
            "id": "si_QCLxOzxahn8vBs",
            "object": "subscription_item",
            "price": {
              "id": "price_1PZ9vRSRGb251gkl9ZzLRVR1",
              "unit_amount": 2400,
              "recurring": {
                "interval": "month"
              }
            },
            "quantity": 1
          }
        ],
        "has_more": false,
        "total_count": 1
      },
      "livemode": false,
      "status": "active"
    },
    "previous_attributes": {
      "cancel_at": null,
      "cancel_at_period_end": false,
      "canceled_at": null
    }
  },
  "livemode": false,
  "pending_webhooks": 1,
  "request": {
    "id": null,
    "idempotency_key": "in_auto_AjJ7B0EY4o"
  },
  "type": "customer.subscription.updated"
}
```

### #12 · Parcelbird · `shipment.in_transit`

- **Delivery id:** `dlv_01M75ESTR3JFQXWQJZ87PMB2D3` · **received** `2026-11-11T14:04:46.723Z` · **ingest** 102 ms (provider POST → on screen)
- **Request line:** `POST /c/Br7nDLk2Qm9x` · **raw query:** (none)
- **Signature:** No secret · Standard Webhooks v1: This delivery is signed (webhook-signature v1) but no secret is set for Parcelbird on agent maya-mbp, so it can't be checked.
- **Shape:** New shape (first capture): shipment.in_transit
- **Decoded summary:** Order #1042 in transit · Portland, OR

```http
POST /c/Br7nDLk2Qm9x HTTP/1.1
host: webhooktrap.dev
user-agent: Parcelbird-Webhooks/2.1
content-type: application/json
content-length: 281
webhook-id: msg_2pR1nO33vkOHW8CGILHjfo5sR1E
webhook-timestamp: 1794405886
webhook-signature: v1,OUbKZP942TymEJCkN8u2s9LKDUiPX5zPMFlgg2iydpM=
accept-encoding: gzip

{"type":"shipment.in_transit","timestamp":"2026-11-11T14:04:46.723Z","data":{"shipment_id":"shp_1c263SsdQymuOjhjztsP","order_ref":"#1042","carrier":"usps","tracking_number":"9400111206215842706131","status":"in_transit","location":{"city":"Portland","region":"OR","country":"US"}}}
```

### #13 · Unknown · `(generic POST)`

- **Delivery id:** `dlv_01M75EX8KK6K3BS3D8JDTZ20C9` · **received** `2026-11-11T14:06:39.219Z` · **ingest** 72 ms (provider POST → on screen)
- **Request line:** `POST /c/Br7nDLk2Qm9x?source=curl&debug=1` · **raw query:** `source=curl&debug=1`
- **Signature:** Unsigned: No signature header found and no known provider fingerprint (User-Agent curl/8.7.1). Treated as a generic request.
- **Shape:** No baseline: Unknown provider
- **Decoded summary:** Generic JSON · 3 fields · No provider detected

```http
POST /c/Br7nDLk2Qm9x?source=curl&debug=1 HTTP/1.1
Host: webhooktrap.dev
User-Agent: curl/8.7.1
Accept: */*
Content-Type: application/json
Content-Length: 52

{"ping":true,"from":"maya","note":"testing the url"}
```

## 4. The Run: Run #3 · Prove invoice.paid · renewal

- **Run id:** `run_01M75F1M56RSH0CVKCTFX4FQWC` · **status:** 3 findings (1 Critical · 1 High · 1 Medium · 1 Info). Info doesn't count in "3 findings" (RUN-3). **Exit code** 1 with the default `--fail-on high`.
- **Target:** `http://localhost:3000/webhooks/stripe` via agent **`maya-mbp`** · framework detected: Express (detected from X-Powered-By)
- **From:** group sub_1QH…4drI (#01 invoice.paid, #03 payment_intent.succeeded, #04 customer.subscription.updated)
- **Started** `2026-11-11T14:09:02.118Z` · **finished** `2026-11-11T14:09:45.312Z` · **duration** 43.2 s · 8 scenarios · 16 deliveries. Unsigned was unchecked in this run's draft (Run #4 adds it).
- **Preflight** (before D1, PRB-8): reset +0 → +610 ms · probe stability check +610 → +1 170 ms.
- **Provider budget:** 10 000 ms · source `default` · UI label `10 s budget · Webhook Trap default`. Stripe publishes no timeout. 10 000 ms is Webhook Trap's default budget (provider-budgets.md, verified 2026-11-14).
- **Verdict levels used:** Level 1 · transport, Level 2 · state probe · **probe** `orders.count` from `webhooktrap.yml` · **reset hook** `npm run db:reset:webhooks` between scenarios
- **Share link:** `https://webhooktrap.dev/s/q7Vf2LmX9pK4wT8nR3bZcD` (token `q7Vf2LmX9pK4wT8nR3bZcD`; after "Create a new link": `https://webhooktrap.dev/s/Hc3mW8tZ1qN5rB7xK2vYpL`) · shared by Maya Lindqvist · owner URL `https://webhooktrap.dev/r/run_01M75F1M56RSH0CVKCTFX4FQWC` (owner only, never printed as a share link)
- **Provenance line:** Run by maya-mbp from the web app
- **Probe entries:** `orders.count` (count) · display: counts up to 9 999 sent as numbers, other values as codes (PV-2)

`webhooktrap.yml` (as the agent read it):

```yaml
target: http://localhost:3000/webhooks/stripe
database: env:DATABASE_URL
probe:
  - as: orders.count
    sql: select count(*) from orders where stripe_invoice_id = '{{invoice.id}}'
reset: npm run db:reset:webhooks
```

### 4.1 Scenario rows (8)

| Scenario | What we did | Deliveries | Result | Strip reading (line 3) | One line | Why providers do this |
| -------- | ----------- | ---------- | ------ | ---------------------- | -------- | --------------------- |
| **Once** | Every selected event once, in capture order. Sets the baseline. | D1, D2, D3 | Pass | `0 → 1` | 200 × 3 · orders.count 0 → 1 | Every provider *(all providers)* |
| **Twice** | The first event again, 500 ms after the first delivery completes: same event id, new signature timestamp | D4, D5 | Finding F2 | `expected 1 · got 2` | 200, 200 · orders.count 1 → 2 on D5, expected 1 | Stripe may deliver the same event more than once. *(Stripe docs › Webhooks › Handle duplicate events)* |
| **Shuffled** | Related events in reverse order | D6, D7, D8 | Pass (+ Info F4) | `○ F4 Info · 0 → 1` | reversed · orders.count 0 → 1 · status not probed | Stripe doesn't guarantee delivery order. *(Stripe docs › Webhooks › Event ordering)* |
| **Late** | Valid signature, timestamp 360 s old (tolerance 300 s) | D9 | Pass | `400 · 41 ms` | 400 in 41 ms · rejected as expected | Replay protection relies on the timestamp tolerance. *(Stripe docs › Webhooks › Prevent replay attacks)* |
| **Retry after error** | Sends the event, then the provider's retry 2 s after it completes. Providers retry when a delivery fails or takes too long; your handler can't tell which. | D10, D11 | Finding F2, F3 | `△ F3 · 11 840 ms` | D10 11 840 ms → retry D11 → orders.count 1 → 2 | Stripe retries failed or timed-out deliveries with backoff for up to 3 days in live mode. *(Stripe docs › Webhooks › Retry behavior)* |
| **Tampered** | amount_paid 2400 → 0 in the body, signed for the original body | D12 | Finding F1 | `expected 4xx · got 200` | 200 in 167 ms · accepted a changed body | A signature only protects you if a failed check stops the request. *(Stripe docs › Webhooks › Verify signatures)* |
| **Burst** | Every selected event, released together (≤ 20 ms apart) | D13, D14, D15 | Pass (+ Info F4) | `○ F4 Info · 0 → 1 (final)` | 200 × 3 · 312–601 ms · orders.count 0 → 1 (final) | Stripe sends related events close together, often concurrently. *(Stripe docs › Webhooks › Event ordering)* |
| **Slow budget** | One more delivery after the others, measured against the budget: 10 s budget · Webhook Trap default | D16 | Finding F3 | `10 420 ms · +420` | 10 420 ms · 420 ms over budget | If Stripe times out waiting, the delivery counts as failed and is retried. Stripe doesn't publish the timeout. *(Stripe docs › Webhooks › Quickly return a 2xx response)* |

Not in this run: **Unsigned** (unchecked in the draft). The strip shows only the run's scenarios, so Run #3 has 8 cells; Run #4 has 9.

### 4.2 Timeline: 16 deliveries

`+ms` is the time the first request byte was written, since run start (the agent accepted the run at `2026-11-11T14:09:02.118Z`). Gaps follow PLAN-3: 150 ms inside Once and Shuffled, 500 ms after Twice's first delivery completes, 2 000 ms after Retry's first attempt completes, and reset + 150 ms between scenarios. A delivery is completed when its response headers and its probe reading are in. Budget is 10 000 ms (`default`) for every row; Late and Tampered are never judged against it (INV-2). Probe digest is `orders.count before → after`; `expected` appears only where §8.5 sets one and the value differs, or on the row that is checked.

| D | +ms | Scenario | Event | Mode | Signing | Status | Latency | Budget | Probe | Verdict |
| - | --- | -------- | ----- | ---- | ------- | ------ | ------- | ------ | ----- | ------- |
| D1 | 1 320 | Once | `invoice.paid` | normal | re-signed | 200 | 184 ms | within | orders.count 0 → 1 | pass |
| D2 | 1 684 | Once | `payment_intent.succeeded` | normal | re-signed | 200 | 121 ms | within | orders.count 1 → 1 | pass |
| D3 | 1 985 | Once | `customer.subscription.updated` | normal | re-signed | 200 | 143 ms | within | orders.count 1 → 1 | pass |
| D4 | 3 978 | Twice | `invoice.paid` | normal | re-signed | 200 | 212 ms | within | orders.count 0 → 1 | pass |
| D5 | 4 720 | Twice | `invoice.paid` | duplicate | re-signed (same event id) | 200 | 196 ms | within | orders.count 1 → 2 · expected 1 | **F2** |
| D6 | 6 766 | Shuffled | `customer.subscription.updated` | normal | re-signed | 200 | 138 ms | within | orders.count 0 → 0 | pass |
| D7 | 7 084 | Shuffled | `payment_intent.succeeded` | normal | re-signed | 200 | 117 ms | within | orders.count 0 → 0 | pass |
| D8 | 7 381 | Shuffled | `invoice.paid` | normal | re-signed | 200 | 199 ms | within | orders.count 0 → 1 | pass |
| D9 | 9 430 | Late | `invoice.paid` | late | re-signed, t − 360 s | 400 | 41 ms | n/a (invalid delivery) | orders.count 0 → 0 | pass |
| D10 | 11 321 | Retry after error | `invoice.paid` | normal | re-signed | 200 | 11 840 ms | **over** | orders.count 0 → 1 | **F3** |
| D11 | 25 191 | Retry after error | `invoice.paid` | retry | re-signed (retry, same event id) | 200 | 205 ms | within | orders.count 1 → 2 · expected 1 | **F2** |
| D12 | 27 246 | Tampered | `invoice.paid` | tampered | signed for the original body, fresh timestamp | 200 | 167 ms | n/a (invalid delivery) | orders.count 0 → 1 · expected 0 | **F1** |
| D13 | 29 263 | Burst | `invoice.paid` | normal | re-signed | 200 | 601 ms | within | — (read once after the burst: 0 → 1 final) | pass |
| D14 | 29 265 | Burst | `payment_intent.succeeded` | normal | re-signed | 200 | 312 ms | within | — (read once after the burst: 0 → 1 final) | pass |
| D15 | 29 266 | Burst | `customer.subscription.updated` | normal | re-signed | 200 | 448 ms | within | — (read once after the burst: 0 → 1 final) | pass |
| D16 | 31 714 | Slow budget | `invoice.paid` | normal | re-signed | 200 | 10 420 ms | **over** | orders.count 0 → 1 | **F3** |

Burst: released from a barrier, dispatch spread 3 ms (≤ 20 ms, BURST-2). The probe isn't read between Burst deliveries; it reads 0 before and 1 after all three, expected 1 (`B_final`).

**The 1 → 2 moment** is **D5** (Twice): same `event.id` as D4, response identical (`200 {"received":true}`), and `orders.count 1 → 2`. D11 repeats it: the retry after D10, which went over the budget.

### 4.3 Request / response side by side (all rows)

**D1** · Once · sent `2026-11-11T14:09:03.438Z` · source capture #01

| Request | Response |
| ------- | -------- |
| `POST http://localhost:3000/webhooks/stripe` · 2 294 B | **200** in 184 ms |
| `Stripe-Signature: t=1794406143,v1=34e9aedefffddf04c3…` | `{"received":true}` |
| Exact bytes of capture #01 | Content-Type: application/json; X-Powered-By: Express |

**D2** · Once · sent `2026-11-11T14:09:03.802Z` · source capture #03

| Request | Response |
| ------- | -------- |
| `POST http://localhost:3000/webhooks/stripe` · 915 B | **200** in 121 ms |
| `Stripe-Signature: t=1794406143,v1=b5761296cb55333e57…` | `{"received":true}` |
| Exact bytes of capture #03 | Content-Type: application/json; X-Powered-By: Express |

**D3** · Once · sent `2026-11-11T14:09:04.103Z` · source capture #04

| Request | Response |
| ------- | -------- |
| `POST http://localhost:3000/webhooks/stripe` · 1 496 B | **200** in 143 ms |
| `Stripe-Signature: t=1794406144,v1=df77df4c3289b689eb…` | `{"received":true}` |
| Exact bytes of capture #04 | Content-Type: application/json; X-Powered-By: Express |

**D4** · Twice · sent `2026-11-11T14:09:06.096Z` · source capture #01

| Request | Response |
| ------- | -------- |
| `POST http://localhost:3000/webhooks/stripe` · 2 294 B | **200** in 212 ms |
| `Stripe-Signature: t=1794406146,v1=ab3bb5f68a9b427f38…` | `{"received":true}` |
| Exact bytes of capture #01 | Content-Type: application/json; X-Powered-By: Express |

**D5** · Twice · sent `2026-11-11T14:09:06.838Z` · source capture #01

| Request | Response |
| ------- | -------- |
| `POST http://localhost:3000/webhooks/stripe` · 2 294 B | **200** in 196 ms |
| `Stripe-Signature: t=1794406146,v1=0f7c26f800b86fc461…` | `{"received":true}` |
| Exact bytes of capture #01 | Content-Type: application/json; X-Powered-By: Express |

**D6** · Shuffled · sent `2026-11-11T14:09:08.884Z` · source capture #04

| Request | Response |
| ------- | -------- |
| `POST http://localhost:3000/webhooks/stripe` · 1 496 B | **200** in 138 ms |
| `Stripe-Signature: t=1794406148,v1=5140c8f36715f76b80…` | `{"received":true}` |
| Exact bytes of capture #04 | Content-Type: application/json; X-Powered-By: Express |

**D7** · Shuffled · sent `2026-11-11T14:09:09.202Z` · source capture #03

| Request | Response |
| ------- | -------- |
| `POST http://localhost:3000/webhooks/stripe` · 915 B | **200** in 117 ms |
| `Stripe-Signature: t=1794406149,v1=20d40f25e9592e3bb8…` | `{"received":true}` |
| Exact bytes of capture #03 | Content-Type: application/json; X-Powered-By: Express |

**D8** · Shuffled · sent `2026-11-11T14:09:09.499Z` · source capture #01

| Request | Response |
| ------- | -------- |
| `POST http://localhost:3000/webhooks/stripe` · 2 294 B | **200** in 199 ms |
| `Stripe-Signature: t=1794406149,v1=3d1840db7a9a158ede…` | `{"received":true}` |
| Exact bytes of capture #01 | Content-Type: application/json; X-Powered-By: Express |

**D9** · Late · sent `2026-11-11T14:09:11.548Z` · source capture #01

| Request | Response |
| ------- | -------- |
| `POST http://localhost:3000/webhooks/stripe` · 2 294 B | **400** in 41 ms |
| `Stripe-Signature: t=1794405791,v1=e7510784ecbdb3f865…` | `{"error":"Timestamp outside the tolerance zone"}` |
| Exact bytes of capture #01 | Content-Type: application/json; X-Powered-By: Express |

**D10** · Retry after error · sent `2026-11-11T14:09:13.439Z` · source capture #01

| Request | Response |
| ------- | -------- |
| `POST http://localhost:3000/webhooks/stripe` · 2 294 B | **200** in 11 840 ms |
| `Stripe-Signature: t=1794406153,v1=4b8947cbd361a5c4ed…` | `{"received":true}` |
| Exact bytes of capture #01 | Content-Type: application/json; X-Powered-By: Express |

**D11** · Retry after error · sent `2026-11-11T14:09:27.309Z` · source capture #01

| Request | Response |
| ------- | -------- |
| `POST http://localhost:3000/webhooks/stripe` · 2 294 B | **200** in 205 ms |
| `Stripe-Signature: t=1794406167,v1=548ec02bc9205892a5…` | `{"received":true}` |
| Exact bytes of capture #01 | Content-Type: application/json; X-Powered-By: Express |

**D12** · Tampered · sent `2026-11-11T14:09:29.364Z` · source capture #01

| Request | Response |
| ------- | -------- |
| `POST http://localhost:3000/webhooks/stripe` · 2 291 B | **200** in 167 ms |
| `Stripe-Signature: t=1794406169,v1=5f9109630166c28184…` | `{"received":true}` |
| amount_paid edited in place 2400 → 0 (no re-serialisation, Content-Length recomputed); Stripe-Signature computed for the original body at a fresh timestamp | Content-Type: application/json; X-Powered-By: Express |

**D13** · Burst · sent `2026-11-11T14:09:31.381Z` · source capture #01

| Request | Response |
| ------- | -------- |
| `POST http://localhost:3000/webhooks/stripe` · 2 294 B | **200** in 601 ms |
| `Stripe-Signature: t=1794406171,v1=b3a88b41c318cae765…` | `{"received":true}` |
| Exact bytes of capture #01 | Content-Type: application/json; X-Powered-By: Express |

**D14** · Burst · sent `2026-11-11T14:09:31.383Z` · source capture #03

| Request | Response |
| ------- | -------- |
| `POST http://localhost:3000/webhooks/stripe` · 915 B | **200** in 312 ms |
| `Stripe-Signature: t=1794406171,v1=380aee9c37f7efa0b5…` | `{"received":true}` |
| Exact bytes of capture #03 | Content-Type: application/json; X-Powered-By: Express |

**D15** · Burst · sent `2026-11-11T14:09:31.384Z` · source capture #04

| Request | Response |
| ------- | -------- |
| `POST http://localhost:3000/webhooks/stripe` · 1 496 B | **200** in 448 ms |
| `Stripe-Signature: t=1794406171,v1=30ba05f78f72d30336…` | `{"received":true}` |
| Exact bytes of capture #04 | Content-Type: application/json; X-Powered-By: Express |

**D16** · Slow budget · sent `2026-11-11T14:09:33.832Z` · source capture #01

| Request | Response |
| ------- | -------- |
| `POST http://localhost:3000/webhooks/stripe` · 2 294 B | **200** in 10 420 ms |
| `Stripe-Signature: t=1794406173,v1=f3c9767e50d56b9813…` | `{"received":true}` |
| Exact bytes of capture #01 | Content-Type: application/json; X-Powered-By: Express |

Every request also carries `Webhooktrap-Run`, `Webhooktrap-Delivery: D<n>`, `Webhooktrap-Scenario` and `Webhooktrap-Dry-Run: 1` (SIGN-3, NAME-3).

### 4.4 Findings (4: 3 that count, 1 Info)

#### F1 · Critical · Accepts tampered payload

- **Type:** `accepts_tampered` · **Level:** Level 1 · transport · **Evidence:** D12 (offending) · **Scenarios:** Tampered
- **Summary:** D12 changed amount_paid from 2400 to 0 and was signed for the original body. Your handler returned 200 in 167 ms and created an order: orders.count 0 → 1, expected a 4xx and no change.
- **Measured:** HTTP 200 in 167 ms · orders.count 0 → 1 · expected: a 4xx and no change
- **Why it matters:** Stripe signs the exact bytes it sends. If a failed check doesn't stop the request, anyone who can reach this URL can create paid orders. *(Stripe docs › Webhooks › Verify signatures)*
- **Likely cause (LC-1):** Late (D9) was rejected, so the timestamp check runs; the signature check probably fails into a log line instead of stopping the request.
- **Fix (Express (detected from X-Powered-By)):** Return 400 as soon as constructEvent throws, before any write. After the fix, D12 should get 400 and leave orders.count at 0.

```js
app.post('/webhooks/stripe', express.raw({ type: 'application/json' }), (req, res) => {
  let event;
  try {
    event = stripe.webhooks.constructEvent(req.body, req.get('stripe-signature'), endpointSecret);
  } catch (err) {
    return res.status(400).send('Invalid signature'); // stop here, don't log and continue
  }
  // ...handle event
});
```

#### F2 · High · Duplicate delivery created a second order

- **Type:** `duplicate_changed_state` · **Level:** Level 2 · judged on maya-mbp · **Evidence:** D4 (reference), D5 (offending), D10 (reference), D11 (offending) · **Scenarios:** Twice, Retry after error
- **Summary:** D5 delivered the same invoice.paid event as D4. Both got 200 {"received":true}, but orders.count went 1 → 2, expected 1. The retry D11 did the same after D10. Only the probe could see this.
- **Measured:** D5: HTTP 200 in 196 ms · orders.count 1 → 2, expected 1 · D11 (retry): HTTP 200 in 205 ms · orders.count 1 → 2, expected 1
- **Why it matters:** Stripe may deliver the same event more than once, and retries failed or timed-out deliveries with backoff for up to 3 days in live mode. Each duplicate creates another order. *(Stripe docs › Webhooks › Handle duplicate events · Retry behavior)*
- **Likely cause (LC-8):** The handler doesn't record the event id before it writes.
- **Fix (Express (detected from X-Powered-By)):** Record event.id before creating the order and return 200 early when it is already recorded. After the fix, D5 and D11 should leave orders.count at 1.

```js
// before creating the order
const { rowCount } = await db.query(
  'INSERT INTO processed_events (id) VALUES ($1) ON CONFLICT DO NOTHING',
  [event.id]
);
if (rowCount === 0) return res.sendStatus(200); // already handled
```

#### F3 · Medium · Over the 10 s budget for Stripe

- **Type:** `over_budget` · **Level:** Level 1 · transport · **Evidence:** D10 (offending), D16 (offending) · **Scenarios:** Retry after error, Slow budget
- **Summary:** 2 of 8 valid invoice.paid deliveries took longer than the 10 s budget (Webhook Trap default): D10 (11 840 ms) and D16 (10 420 ms). Stripe doesn't publish its timeout, so these are over our default budget, not proven failures at Stripe.
- **Measured:** D10 11 840 ms (+1 840 over) · D16 10 420 ms (+420 over) · median of the other 6 valid invoice.paid deliveries 202 ms
- **Why it matters:** If Stripe times out waiting, the delivery counts as failed and is retried. Stripe doesn't publish the timeout. In this run, D10 went over our default budget, and the retry D11 created the duplicate order in F2. *(Stripe docs › Webhooks › Quickly return a 2xx response)*
- **Likely cause (LC-4):** Only some deliveries of invoice.paid were slow, so the slow step is intermittent: usually an external call or a lock wait before the response.
- **Fix (Express (detected from X-Powered-By)):** Acknowledge with 200 first, then queue the slow work. After the fix, D10 and D16 should respond within the budget.

```js
res.sendStatus(200);            // acknowledge first
await queue.add('create-label', { invoiceId: event.data.object.id });
```

#### F4 · Info · Probe doesn't see subscription state

- **Type:** `probe_gap` · **Level:** Level 2 · judged on maya-mbp · **Evidence:** D6 (offending), D15 (offending) · **Scenarios:** Shuffled, Burst
- **Summary:** Shuffled and Burst passed on orders.count. But customer.subscription.updated was delivered first in Shuffled (D6) and inside the burst (D15), and no probe reads subscription state.
- **Measured:** orders.count 0 → 1 after Shuffled and after Burst, expected 1 · subscription state not measured
- **Why it matters:** Out-of-order delivery usually breaks status fields, not counts. *(Stripe docs › Webhooks › Event ordering)*
- **Fix (webhooktrap.yml):** Add subscriptions.status to the probe in webhooktrap.yml. The next run then checks both.

```yaml
probe:
  # keep your existing entries, then add:
  - as: subscriptions.status
    sql: select status from subscriptions where stripe_id = '{{subscription.id}}'
```

### 4.5 Save as fixtures (preview)

State: **Not saved**. Files the agent would write: `webhooktrap/fixtures/stripe/invoice.paid.json`, `webhooktrap/fixtures/stripe/payment_intent.succeeded.json`, `webhooktrap/fixtures/stripe/customer.subscription.updated.json`, `webhooktrap.yml`. Scrub rules shown in the preview: customer_email → customer1@example.test; customer_name → Test Customer 1; hosted_invoice_url → https://invoice.stripe.test/redacted.

### 4.6 Run history (for the runs list and other run states)

| Run | Status | Reason | Exit | Started | Duration | Events | Scenarios | Deliveries | Line |
| --- | ------ | ------ | ---- | ------- | -------- | ------ | --------- | ---------- | ---- |
| #1 `run_01M75CPHT6…` | **Inconclusive** | needs_probe | 0 | 13:28:02.118 | 1.4 s | #01 | 2 | 3 | Responses identical, but we can't see your database. Add a probe to check for duplicate side effects. (One event selected, so Once sends 1 delivery and Twice 2. Exit 1 with --fail-on-inconclusive.) |
| #2 `run_01M75EBN16…` | **Aborted** | agent_lost | 2 | 13:57:02.118 | 2.0 s | #01 #03 #04 | 8 | 2 of 16 | Agent maya-mbp disconnected at 13:57:04.120 during D3 of 16. D1–D2 and their results are kept. |
| #3 `run_01M75F1M56…` | **3 findings** | — | 1 | 14:09:02.118 | 43.2 s | #01 #03 #04 | 8 | 16 | 1 Critical · 1 High · 1 Medium · 1 Info |
| #4 `run_01M75GBQT6…` | **Passed · 1 Info** | — | 0 | 14:32:02.118 | 23.4 s | #01 #03 #04 | 9 | 17 | 9 of 9 scenarios passed at Level 1 + Level 2 (orders.count). Not checked: subscription state, which no probe reads · side effects outside your database, such as emails. (Hypothetical rerun after the fixes for F1 to F3, with Unsigned added (17 deliveries, D13 is Unsigned). Use it for the Passed state and the 9-cell strip.) |

## 5. Scenario catalogue copy (sheet and landing)

Run order is fixed (PLAN-1). All 9 are MVP scenarios (A-7, decision 35) and are checked by default in a new draft. Level 3 and sequence packs are post-MVP and are not drawn.

| Scenario | What it does | Deliveries (n = selected events) | Why providers do this | Citation | Needs secret |
| -------- | ------------ | -------------------------------- | --------------------- | -------- | ------------ |
| Once | Every selected event once, in capture order. Sets the baseline. | n | Every provider | — | no |
| Twice | The first event again, 500 ms after the first delivery completes: same event id, new signature timestamp | 2 | Stripe may deliver the same event more than once. | Stripe docs › Webhooks › Handle duplicate events | yes |
| Shuffled | Related events in reverse order | n, needs 2+ related events | Stripe doesn't guarantee delivery order. | Stripe docs › Webhooks › Event ordering | yes |
| Late | Valid signature, timestamp 360 s old (tolerance 300 s) | 1 | Replay protection relies on the timestamp tolerance. | Stripe docs › Webhooks › Prevent replay attacks | yes |
| Retry after error | Sends the event, then the provider's retry 2 s after it completes. Providers retry when a delivery fails or takes too long; your handler can't tell which. | 2 | Stripe retries failed or timed-out deliveries with backoff for up to 3 days in live mode. | Stripe docs › Webhooks › Retry behavior | yes |
| Tampered | amount_paid 2400 → 0 in the body, signed for the original body | 1 | A signature only protects you if a failed check stops the request. | Stripe docs › Webhooks › Verify signatures | yes |
| Unsigned | The event with every signature header removed | 1 | A handler that checks the signature only when the header is present accepts anything. | Stripe docs › Webhooks › Verify signatures | no |
| Burst | Every selected event, released together (≤ 20 ms apart) | max(n, 2) | Stripe sends related events close together, often concurrently. | Stripe docs › Webhooks › Event ordering | yes |
| Slow budget | One more delivery after the others, measured against the budget: 10 s budget · Webhook Trap default | 1 | If Stripe times out waiting, the delivery counts as failed and is retried. Stripe doesn't publish the timeout. | Stripe docs › Webhooks › Quickly return a 2xx response | no |

Without a Stripe secret no Stripe run can start: every valid delivery must be re-signed (SIGN-2). For GitHub and Shopify, valid deliveries are sent with the original signature header, and Late is n/a.

Citations are written as page paths, not URLs, until A01 supplies verified links.

### 5.1 Shopify server-run draft (FL-9, Order #1042)

FL-9: group Order #1042 (#05, #06), n = 2, server target, no secret. Citations wait for A01 (DUP-1, DUP-2): shown as [pending A01]. On an n/a row the why and citation are not shown.

| Scenario | What | Why | Citation | Deliveries | State |
| -------- | ---- | --- | -------- | ---------- | ----- |
| Once | Every selected event once, in capture order. Sets the baseline. | Every provider | — | 2 | locked |
| Twice | The first event again, 500 ms after the first delivery completes: same body, same X-Shopify-Webhook-Id | Shopify may deliver the same webhook more than once. | [pending A01] | — | n/a · Not verified for Shopify yet |
| Shuffled | Related events in reverse order | Shopify doesn't guarantee that webhooks arrive in the order they were created. | [pending A01] | 2 | checked |
| Late | Valid signature with an old timestamp | — | — | — | n/a · Shopify signs no timestamp |
| Retry after error | The first event again, 2 000 ms after the first attempt completes, as a retry | Shopify retries a delivery that fails or answers too slowly. | [pending A01] | — | n/a · Not verified for Shopify yet |
| Tampered | One field changed in the body (line_items[0].quantity 2 → 0), sent with the original signature header | A signature only protects you if a failed check stops the request. | [pending A01] | 1 | checked |
| Unsigned | The first event with X-Shopify-Hmac-Sha256 removed | A handler that checks the signature only when the header is present accepts anything. | [pending A01] | 1 | checked |
| Burst | Every selected event, released together (≤ 20 ms apart) | Shopify can send related webhooks, such as orders/create and orders/paid, within a second of each other. | [pending A01] | 2 | checked |
| Slow budget | One more delivery after the others, measured against the budget: 5 s · Shopify limit | Shopify counts a response slower than 5 s as failed and retries it. | Shopify docs [page: pending A01] | 1 | checked |

Summary: "6 scenarios selected · 9 deliveries". Tampered mutation: `line_items[0].quantity` 2 → 0 (TAMP-1 rule 2 (no Shopify tamper_paths yet) [pending A01]).

### 5.2 State reading with codes (PV-2 to PV-7)

Illustrative states-sheet example, not part of Run #3: a second probe entry subscriptions.status, judged on D4 of a run that proves customer.subscription.updated. Codes are PV-3 labels.

| Case | Line | Detail / strip |
| ---- | ---- | -------------- |
| Owner, default (`code`) | Expected #a1f3c0 · got #9c0254 | before #a1f3c0 → after #9c0254 · strip `got #9c0254` |
| Owner, `show: value` (`shown (show: value)`) | Expected active · got past_due | before active → after past_due · strip `got past_due` |
| Unchanged | Expected #a1f3c0 · got #a1f3c0 | unchanged, as expected |
| Widened (PV-4) | Expected #a1f3c0d27e45 · got #9c0254b1f08a | PV-4: a collision widened every code of the run to 12 characters |
| Refused by the server (PV-11) | Expected #a1f3c0 · got #invalid | — |

## 6. State copy (plain labels; territories add flavour only in helper text)

### Capture

| State | Label | Helper |
| ----- | ----- | ------ |
| `waiting` | Waiting for the first delivery | Send any webhook to this URL. It appears here within a second. Provider instructions: **Stripe:** Test mode › Developers › Webhooks › Add destination. Paste the URL, pick the events you handle, save, then trigger a test checkout. Stripe sends straight here, no CLI needed. / **GitHub:** Repository › Settings › Webhooks › Add webhook. Payload URL = this URL, Content type = application/json, set a secret. / **Shopify:** Settings › Notifications › Webhooks › Create webhook. Format JSON, URL = this URL, API version 2025-10. / **Standard Webhooks:** Paste the URL as the endpoint in your provider's webhook settings. We detect webhook-id / webhook-signature headers automatically. / **curl:** curl -X POST https://webhooktrap.dev/c/k7Qm2xVb9TfL -H 'Content-Type: application/json' -d '{"hello":"world"}' |
| `loading` | Loading deliveries… |  |
| `receiving` | Live | New deliveries appear at the top. |
| `new_pill` | 3 new deliveries | Press N or click to jump to the newest. |
| `paused` | Catching up | 6 deliveries arrived while this tab was hidden. Showing them now. |
| `stream_lost` | Live updates interrupted | Reconnecting… Deliveries are still being captured on our side and will appear when the connection returns. Last update 14:07:51. |
| `quota` | Guest limit reached: 100 of 100 deliveries | New deliveries are still answered (your provider sees 200) but not stored. Save the ones you need as fixtures, or sign in to keep capturing. |
| `expired` | This guest URL expired | Guest captures are kept for 24 hours. This one ended at 14:02 on Nov 12. Get a new URL, or sign in so URLs don't expire. |
| `body_not_kept` | Body not kept (12.4 MB, limit 5 MB) | Headers and a SHA-256 of the body are stored. The body itself was not. |
| `binary` | Binary body (application/octet-stream, 48 KB) | Shown as hex. Download the exact bytes. |
| `no_results` | No deliveries match “invoice.voided” | Clear the filter to see all 13. |

### Signature

| State | Label | Helper |
| ----- | ----- | ------ |
| `valid` | Signature valid | Checked on maya-mbp with STRIPE_WEBHOOK_SECRET. |
| `invalid` | Signature invalid | Signed with a different secret. See the reason and fix. |
| `no_secret` | No secret | Signed by Parcelbird, but no secret is set on your agent, so we can't check it. |
| `unknown` | Unknown scheme | There is a signature-like header (X-Signature) but we don't recognise the scheme. Define it to check it. |
| `unsigned` | Unsigned | No signature header. Nothing to check. |
| `no_agent` | Not checked | Secrets live on your machine. Connect the agent to check signatures. |

### Agent

| State | Label | Helper |
| ----- | ----- | ------ |
| `not_installed` | No agent connected | The agent runs on your machine, sends deliveries to localhost and keeps your signing secrets there. Command: `npx @webhooktrap/agent@1 pair 7KQ2M-XR9TD` |
| `pairing` | Waiting for your agent… | Run the command in a terminal. Code 7KQ2M-XR9TD expires in 9:41. |
| `awaiting_confirmation` | Confirm in your terminal | maya-mbp is waiting for you to type y in the terminal where you ran the command. Check that it shows https://webhooktrap.dev first. |
| `needs_reconfirm` | Confirm in your terminal | This session moved to your account, so maya-mbp asks you to confirm the new workspace in its terminal. It runs nothing until you do. |
| `pair_declined` | Pairing cancelled | The agent was not paired: someone answered N in the terminal. Get a new code to try again. |
| `offline_finished_run` | maya-mbp is offline | This run is complete and shown as the server stored it. Run again and Save as fixtures need maya-mbp. Last seen 14:31:07. |
| `signatures_checked` | Signatures checked | Signatures checked: 4 of 4 valid with STRIPE_WEBHOOK_SECRET on maya-mbp. |
| `connected` | Connected · maya-mbp | Target localhost:3000/webhooks/stripe · agent 0.9.2 |
| `unreachable_refused` | Target unreachable | maya-mbp got 'connection refused' from localhost:3000. Is your app running? |
| `unreachable_tls` | Target unreachable | TLS error on https://localhost:3443: self-signed certificate. Use http:// for localhost or pass --insecure-localhost. |
| `lost_mid_run` | Agent disconnected | maya-mbp stopped responding at 13:57:04.120 during D3 of 16. D1–D2 and their results are kept. Reconnect to run it again. |
| `too_old` | Agent update needed | maya-mbp runs 0.7.4. This run needs 0.9 or later: npx @webhooktrap/agent@latest |
| `replaced` | Agent replaced | A new agent paired with this guest session, so maya-mbp was disconnected. A guest session has one agent at a time. |

### Run

| State | Label | Helper |
| ----- | ----- | ------ |
| `draft` | Draft | 9 scenarios selected · 17 deliveries · about 50 s |
| `draft_run3` | Draft | 8 scenarios selected · 16 deliveries · about 45 s |
| `draft_over_cap` | Too many deliveries | This run would send 54 deliveries. The limit is 50. |
| `draft_two_providers` | Select events from one provider | A run tests one provider at a time. Remove the Shopify events, or prove them in their own run. |
| `needs_secret` | Needs secret | Stripe signs a timestamp, so nothing can be replayed without STRIPE_WEBHOOK_SECRET on maya-mbp. Command: `webhooktrap secrets set stripe` |
| `side_effects` | This run repeats events | This run sends the same events several times. If your handler sends emails or charges cards, honour the Webhooktrap-Dry-Run header first. |
| `queued` | Queued | Waiting for maya-mbp to accept the run… |
| `queued_confirm` | Queued | Confirm in maya-mbp's terminal: it shows the probe and reset commands from webhooktrap.yml and this target before it runs them. |
| `checks_hosted` | What was checked | Level 1 transport · Level 2 probe orders.count (count) · reset before every scenario, run on maya-mbp |
| `running` | Running · D11 of 16 | Retry after error · 25.3 s elapsed |
| `passed` | Passed · 1 Info | 9 of 9 scenarios passed at Level 1 + Level 2 (orders.count). Not checked: subscription state, which no probe reads · side effects outside your database, such as emails. |
| `findings` | 3 findings | 1 Critical · 1 High · 1 Medium · 1 Info |
| `aborted` | Aborted | Agent lost during D3 of 16. D1–D2 are kept. |
| `aborted_target_lost` | Aborted | 3 deliveries in a row couldn't connect to localhost:3000 (D9 to D11). Completed deliveries and findings are kept. |
| `aborted_reset_failed` | Aborted | The reset before Tampered failed on maya-mbp. D1–D11 and their findings are kept. The command and its output are in the agent's terminal. CLI (local only): The reset before Tampered failed: npm run db:reset:webhooks exited with code 1. D1–D11 and their findings are kept. |
| `aborted_time_limit` | Aborted | The run reached the 15-minute limit during Burst. Completed deliveries and findings are kept. |
| `aborted_user` | Aborted | Stopped with Ctrl-C during D7. D1–D6 and their findings are kept. |
| `inconclusive` | Inconclusive | Responses are identical, but we can't see your database. Add a probe to check for duplicate side effects. |
| `order_dependent` | Order-dependent | No reset ran between scenarios, so results may depend on earlier ones. Add reset: to webhooktrap.yml. |
| `start_failed` | Couldn't start the run | maya-mbp refused the job: target localhost:3000/webhooks/stripe isn't in its allow-list. Run: webhooktrap targets add localhost:3000 Reason code: `target_not_allowed`. |
| `couldnt_start_baseline_rejected` | Couldn't start the run | Your handler rejected a correctly signed delivery (HTTP 401). Check the secret on maya-mbp and the target path. Reason code: `baseline_rejected`. |
| `couldnt_start_target_unreachable` | Couldn't start the run | maya-mbp couldn't connect to localhost:3000 for 5 s (connection refused). Nothing else was sent. Is your app running? Reason code: `target_unreachable`. |
| `couldnt_start_probe_unstable` | Couldn't start the run | Your probe isn't stable: its value changed with no delivery. Remove time-based values such as updated_at or now(). Reason code: `probe_unstable`. |
| `couldnt_start_reset_failed` | Couldn't start the run | The reset failed on maya-mbp before the first delivery. Nothing was sent. The command and its output are in the agent's terminal. Reason code: `reset_failed`. CLI (local only): The reset failed before the first delivery: npm run db:reset:webhooks exited with code 1. Nothing was sent. |
| `couldnt_start_needs_secret` | Couldn't start the run | Stripe signs a timestamp, so nothing can be replayed without STRIPE_WEBHOOK_SECRET on maya-mbp. Reason code: `needs_secret`. |
| `couldnt_start_agent_too_old` | Couldn't start the run | maya-mbp runs 0.7.4. This run needs 0.9 or later: npx @webhooktrap/agent@latest Reason code: `agent_too_old`. |

### Scenario results: inconclusive, unreachable and n/a reasons

| State | Label | Helper |
| ----- | ----- | ------ |
| `needs_probe` | — Inconclusive | Both responses were 2xx. Add a probe to see what the handler did to your state. |
| `needs_reset` | — Inconclusive | Comparing with the baseline needs a reset between scenarios. Add reset: to webhooktrap.yml. |
| `probe_failed` | — Inconclusive | Probe failed 3 times in a row; continuing at Level 1. |
| `not_simultaneous` | — Inconclusive | The deliveries left 34 ms apart, more than the 20 ms a burst allows. Not judged at Level 2. |
| `redirected` | — Inconclusive | The handler answered 302, so we can't tell whether it rejected the delivery. |
| `unsettled` | — Inconclusive | orders.count changed 1 s after the last response. Your handler probably writes in a background job. Set settle: 2s in webhooktrap.yml. |
| `unreachable` | Unreachable | maya-mbp couldn't connect to localhost:3000 for this delivery (connection refused). Not judged. |
| `na_one_event` | n/a | Needs 2+ related events |
| `na_no_timestamp` | n/a | GitHub signs no timestamp |
| `na_no_retries` | n/a | GitHub doesn't retry automatically |
| `na_not_json` | n/a | Body isn't JSON |
| `na_not_verified` | n/a | Not verified for GitHub yet |
| `na_unfaithful` | n/a | Couldn't build a faithful request |
| `not_selected` | Not selected | Not part of this run |

### Info findings (never counted in "N findings")

| State | Label | Helper |
| ----- | ----- | ------ |
| `needs_probe` | Add a probe to see state | Twice got 200 twice, but nothing reads your database, so a duplicate side effect can't be seen. |
| `order_dependent` | Results may depend on order | No reset ran between scenarios. Add reset: to webhooktrap.yml. |
| `probe_gap` | Probe doesn't see subscription state | customer.subscription.updated changes subscription state, which no probe reads. |
| `cold_start` | First delivery was slow | D1 took 12 300 ms, later deliveries ≤ 238 ms. Likely a cold start, such as a dev server compiling the route. Not counted. |
| `async_write` | Handler writes after it responds | orders.count changed 1 s after the last response. Your handler probably writes in a background job. Set settle: 2s in webhooktrap.yml. |

### Guest session (A-13 values)

| State | Label | Helper |
| ----- | ----- | ------ |
| `meter` | Guest session · expires in 23 h 49 m · 13/100 deliveries · 1/10 runs | Sign in to keep |
| `meter_waiting` | Guest session · expires in 23 h 59 m · 0/100 deliveries · 0/10 runs | Sign in to keep |
| `meter_first_four` | Guest session · expires in 23 h 57 m · 4/100 deliveries · 0/10 runs | Sign in to keep |
| `ending_soon` | Ends in 1 h | This session ends in 1 h. Save fixtures or sign in to keep it. |
| `run_quota` | 10 of 10 guest runs used | Sign in to keep running. Runs that couldn't start don't count. |
| `run_too_late` | Session ends in 4 min | A run can't start in the last 5 minutes of a guest session. Sign in to keep this session. |
| `expired_mid_run` | Guest session ended | The session reached 24 hours during the run, so the run stopped and everything was deleted. |
| `ended` | Session ended | Nothing was kept. |
| `claimed` | Guest session moved to your account | Your deliveries, runs and agent are now in the project 'Stripe project'. The URLs didn't change. Share links were turned off; share again to get new ones. Confirm the new workspace in maya-mbp's terminal. |

### Fixtures

| State | Label | Helper |
| ----- | ----- | ------ |
| `not_saved` | Not saved | Save these 3 events as scrubbed fixtures in your repo. |
| `saved` | Saved · in sync | 3 fixtures + webhooktrap.yml in brindle-app/webhooktrap/fixtures, written 14:12. |
| `drifted` | Shape drifted | customer.subscription.updated now has cancellation_details.feedback, which isn't in your fixture. |

### Probe display (PV-2, PV-5, PV-9, PV-10, PV-14)

| State | Label | Helper |
| ----- | ----- | ------ |
| `config_note` | Probe values | Probe values stay on your machine. The web app shows counts up to 9 999 as numbers and other values as short codes such as #a1f3c0, which only show whether a value changed. |
| `tag_count` | count | Shown as a number on the web. |
| `tag_code` | code | Shown as a code on the web, such as #a1f3c0. |
| `tag_shown` | shown (show: value) | Shown on your private run page. Shared links show a code unless you include probe values. |
| `code_tooltip` | #a1f3c0 | Code for a value that stays on your machine. Same code = same value within this run. |
| `invalid` | #invalid | The server refused this value, so it isn't shown. |
| `guest_show_ignored` | show: ignored | show: is ignored for guest sessions; sign in to use it |
| `show_fallback` | Shown as a code | customer.email is marked show: value, but its value looks like personal data, so the web app shows a code. |
| `lint_name` | Probe name | Probe names are visible in the web app and on shared links. |
| `lint_unscoped` | Whole-table probe | This probe reads the whole table. Its value can reveal your volume over time. Scope it with {{invoice.id}}. |

### Share dialog (PO-A, PO-C, PV-7, PV-14)

| State | Label | Helper |
| ----- | ----- | ------ |
| `title` | Share Run #3 | Shared as Maya Lindqvist |
| `private` | Only people in this project | The run page needs a sign-in. |
| `link` | Anyone with the link | People with the link see probe names and counts. Other values show as codes, and signing secrets never leave your machine. |
| `hide_bodies` | Hide request and response bodies | Fields your provider pack marks as personal are always hidden. |
| `include_probe_values` | Include probe values | Also shows the values you marked show: in webhooktrap.yml: subscriptions.status. |
| `guest_note` | Guest link | This link stops working when your guest session ends or when you sign in. |
| `new_link` | Create a new link | The current link stops working at once. |
| `rotated` | New link ready | The old link now shows Not found. |
| `stop` | Stop sharing | The link now shows Not found. |
| `copied` | Copied | Share link copied. Anyone with the link can see this run. |
| `ci_private` | Uploaded privately | Uploaded runs are private. Add --share to get a link anyone can open. |

### Provenance line (TM-17, every run view)

| State | Label | Helper |
| ----- | ----- | ------ |
| `agent_web` | Run by maya-mbp from the web app | Results come from the machine that ran them. Webhook Trap re-checks status codes and timings only. |
| `agent_cli` | Run by maya-mbp from the CLI |  |
| `ci` | Uploaded from CI (token github-actions) |  |
| `server_public` | Run by the Webhook Trap server runner |  |
| `server_allowlist` | Run by the Webhook Trap server runner | Self-host: private destinations allowed for this user (B′). |
| `judged_on` | Level 2 · judged on maya-mbp | Finding header for Level 2 findings (RUN-9); "judged in CI" for CI runs. Never "verified". |

### Errors

| State | Label | Helper |
| ----- | ----- | ------ |
| `server` | Something went wrong on our side | Your deliveries are safe. Retry, or check status.webhooktrap.dev. Ref 7F3K-21QX. |
| `offline` | You're offline | Showing what was loaded at 14:07. Live updates resume when you're back. |
| `not_found` | Delivery not found | It may have expired with its guest session, or the link is wrong. |
| `read_only` | Read-only shared run | Shared by Maya Lindqvist. Sign in to run it again on your own agent. |
| `example` | Example run | A real run against a deliberately buggy handler. Get a URL to prove your own. |
| `not_found_run` | Not found | This run doesn't exist or isn't shared with you. Guest runs are kept for 24 hours. |

## 7. Account security fixtures (decision 44)

Fixtures for the account-security screens (S-02, S-17A/B, S-24 to S-31). Fictional. Recovery codes and the TOTP secret are derived from sha256 seeds; they open nothing.

| Credential | Kind | Sync | Added | Last used | State |
| ---------- | ---- | ---- | ----- | --------- | ----- |
| MacBook Touch ID | Passkey | Synced | 9 Nov 2026 | Today, 13:52 UTC | OK |
| YubiKey 5C NFC | Security key | This device only | 10 Nov 2026 | 10 Nov 2026 | OK |
| Pixel 8 | Passkey | Synced | 7 Nov 2026 | Never used | OK |
| Old YubiKey | Security key | This device only | 3 Nov 2026 | Today, 11:44 UTC | Blocked |
| Spare YubiKey | Security key | This device only | 4 Nov 2026 | Today, 10:57 UTC | Blocked twice (can't unblock) |

Linked: GitHub `maya-l` (since 2 Nov 2026); Google not linked. Authenticator app on since 9 Nov 2026. Recovery codes left: 8 of 10.

Recovery codes (shown once; fake): `x7vc2-y4bnq`, `a9zrm-j7m5w`, `m75yt-vsp27`, `yy02w-hzr2b`, `9knah-a5ytz`, `akwpe-e6wmb`, `fmywd-j7epa`, `r06bq-wkc1h`, `fdn42-2y71y`, `hdkym-sqnp2`. TOTP setup key (fake): `JJUK 4KVX 5LV4 MKW2 YCBR FQQ6 4NEG T2UW`.

Recovery request: requested 11 Nov 2026, 09:12 UTC, confirmed 11 Nov 2026, 09:20 UTC, reminder 12 Nov 2026, 09:20 UTC, completes 14 Nov 2026, 09:20 UTC. Extended (RC-2, password reset 8 Nov 2026, 18:40 UTC): completes 18 Nov 2026, 09:20 UTC. Cool-down after a cancel: until 11 Dec 2026. Lock (RC-3) until 21 Nov 2026.

Sessions ("Where you're signed in", A05 `SessionRow`; this browser first):

| Browser | Network | Signed in | Last seen | How |
| ------- | ------- | --------- | --------- | --- |
| Safari 18 on macOS (this browser) | `198.51.100.0/24` | 11 Nov 2026, 13:52 UTC | Active now | Passkey |
| Firefox 133 on Windows | `203.0.113.0/24` | 10 Nov 2026, 08:52 UTC | 10 Nov 2026, 17:31 UTC | Password, then authenticator app |
| Safari 18 on iPhone | `2001:db8:4a1c::/48` | 4 Nov 2026, 19:05 UTC | 9 Nov 2026, 07:40 UTC | GitHub |

Networks are stored truncated (/24 IPv4, /48 IPv6; A05 domain §2.7). Addresses are documentation ranges (RFC 5737, RFC 3849).

Security log (S-17A, last 30 days; codes are A03 proposals, [pending A05 exact codes]):

| When | What | Browser · network |
| ---- | ---- | ----------------- |
| 11 Nov 2026, 13:52 UTC | Signed in with a passkey (MacBook Touch ID) | Safari 18 on macOS · 198.51.100.0/24 |
| 10 Nov 2026, 08:58 UTC | Security key added: YubiKey 5C NFC | Firefox 133 on Windows · 203.0.113.0/24 |
| 10 Nov 2026, 08:52 UTC | Signed in with password, then authenticator app | Firefox 133 on Windows · 203.0.113.0/24 |
| 9 Nov 2026, 11:02 UTC | Authenticator app turned on | Safari 18 on macOS · 198.51.100.0/24 |
| 9 Nov 2026, 10:58 UTC | Passkey added: MacBook Touch ID | Safari 18 on macOS · 198.51.100.0/24 |
| 4 Nov 2026, 19:05 UTC | Signed in with GitHub | Safari 18 on iPhone · 2001:db8:4a1c::/48 |
| 2 Nov 2026, 14:05 UTC | Password set | Safari 18 on macOS · 198.51.100.0/24 |
| 2 Nov 2026, 13:58 UTC | GitHub linked (maya-l) | Safari 18 on macOS · 198.51.100.0/24 |

Email change (pending): to `maya@brindleroasters.example`, shown masked as `m***@b***.example`, requested 11 Nov 2026, 13:56 UTC, link works until 14:56 UTC. Taken-address example: `tomas@ferrymarket.example`. Sign-in throttle example: try again after 14:58 UTC (Illustrative: A05 names the sign-in throttle (per email + address, per address) without numbers; the screen prints the time from Retry-After. Example uses the 1-hour hold length (domain §2.1) [pending A05 value].) Password: 12 to 128 characters (72 bytes on a bcrypt fallback). Link lifetimes: verify 60 minutes, email change 60 minutes, reset 60 minutes, recovery request 60 minutes, recovery finish 24 hours, self host password set 24 hours.

Post-recovery review (S-30, A05 `RecoveryReview`): completed 14 Nov 2026, 09:20 UTC, lock until 21 Nov 2026, 09:20 UTC; finish link works until 15 Nov 2026, 09:20 UTC. Agents: maya-mbp. CI tokens: github-actions · Brindle Coffee · 10 Nov 2026; github-actions · Stripe project · Never used.

| When | What | Browser · network |
| ---- | ---- | ----------------- |
| 14 Nov 2026, 09:34 UTC | Signed in with the recovery link | Firefox 133 on Windows · 203.0.113.0/24 |
| 14 Nov 2026, 09:34 UTC | Password set | Firefox 133 on Windows · 203.0.113.0/24 |
| 14 Nov 2026, 09:20 UTC | Account recovered: passkeys, security keys, authenticator app and recovery codes removed; every browser and personal token signed out | — |
| 11 Nov 2026, 09:20 UTC | Recovery link confirmed; 72-hour wait started | Firefox 133 on Windows · 203.0.113.0/24 |
| 11 Nov 2026, 09:12 UTC | Account recovery requested | Firefox 133 on Windows · 203.0.113.0/24 |

Account deletion (A05 has no route yet (U-19); A03 UX drawn as [pending A05]): 2 projects (Brindle Coffee, Stripe project), 4 runs, 1 agent (maya-mbp), 2 CI tokens and 1 personal token.

| 1.x stand-in | Email | 1.x since | Activation |
| ------------ | ----- | --------- | ---------- |
| Tomás Okafor | tomas@ferrymarket.example | 2 Mar 2025 | reset |
| Aiko Brandt | aiko.brandt@example.com | 19 Jun 2025 | github |
| Lena Vos | lena@vosworks.example | 11 Jan 2025 | not yet |

Three fictional stand-ins for the 3 imported 1.x accounts (decision 41). The real names and emails stay in the PO's export and never enter design files.

## 8. Changes (2026-11-14, decision 35)

| # | Source | Change |
| - | ------ | ------ |
| 1 | A01 §16.1, BUD-1 | Parcelbird (Standard Webhooks) budget 15 000 ms → 10 000 ms `default`. Provider table gains UI label, tolerance and pack columns; Unknown gets the generic 10 000 ms default. |
| 2 | A01 §16.2, C-4 | Run #3 regenerated under PLAN-1: Once sends #01 #03 #04, so 16 deliveries (Once D1–D3, Twice D4–D5, Shuffled D6–D8, Late D9, Retry D10–D11, Tampered D12, Burst D13–D15, Slow budget D16). F1 = D12, F2 = D4 D5 · D10 D11, F3 = D10 · D16, F4 = D6–D8 (corrected to D6 · D15 in change 26). Drafts and Run #2 say "of 16". |
| 3 | A01 §16.3, PLAN-3 | `+ms` recomputed from a timing model: gaps after completion, reset and async check between scenarios, preflight before D1. Duration 41.0 s → 43.2 s. |
| 4 | A01 §16.4, TAMP-2 | Tampered is "signed for the original body" with a fresh timestamp; the body diff is recorded as `tamper {path, from, to}`. |
| 5 | A01 §16.5, BURST-4 | Burst rows show state `—`; the scenario reading is `0 → 1 (final)`; dispatch spread 3 ms recorded. |
| 6 | A01 §16.6, RUN-3 | "4 findings" → "3 findings" with the counts line "1 Critical · 1 High · 1 Medium · 1 Info". Exit codes added to every run. |
| 7 | A01 §16.7, LC-4, INV-2 | F3 likely cause from LC-4 (the commit-message cause is removed); measured line counts only the 8 valid `invoice.paid` deliveries. F3 title "Over the 10 s budget for Stripe" (A01 §16.22). F1 cause from LC-1, F2 gains LC-8. |
| 8 | A01 §16.8, SIG-2, SIG-3 | #09: "Hook ID 512839104 was edited at 13:31 today" removed; the 7 checks listed in SIG-2 order. |
| 9 | A01 §16.9, GRP-4, GRP-5 | Order #1042 is Shopify only (#05 #06). Parcelbird #08 #12 form group `shp_1c263…ztsP`. 7 groups. |
| 10 | A01 §16.10, RUN-1 | Run copy adds every Couldn't start reason and the Aborted reasons `target_lost`, `reset_failed`, `time_limit`, `user_abort`. |
| 11 | A01 §16.11, SIGN-2 | `needs_secret` no longer offers "run only Once and Slow budget". |
| 12 | A01 §16.12, NAME-4 | Side-effects copy applies to every run beyond Once. |
| 13 | A01 §16.13, NAME-1 to 3 | `trap` → `webhooktrap`, `trap.yml` → `webhooktrap.yml`, `X-Trap-*` → `Webhooktrap-*` (adds `Webhooktrap-Scenario`), fixtures under `webhooktrap/fixtures/`. |
| 14 | A01 §16.14, A-7 | Catalogue copy per CE changes (Once every event, Twice after completion, Burst released together, Retry RETRY-6) and **Unsigned** added (9 scenarios). Run #4 adds Unsigned: 17 deliveries, "Passed · 1 Info" per RUN-7. |
| 15 | A-13, A-18 | Guest quota 3 → 10 runs, counting rule in the note. Twilio and Slack listed as coming, with no budget. |
| 16 | A03 (consistency) | Run #2's disconnect time 13:57:40.912 → 13:57:04.120: under the timing model D3 of Run #2 is sent about 2 s after start, not 38 s. Durations added to the run history (Run #1 1.4 s, Run #2 2.0 s, Run #4 23.4 s, estimated with the same model). |
| 17 | A03 | New copy groups for screens: scenario reasons (inconclusive, unreachable, n/a), Info findings, guest session states, draft refusals (cap, two providers), example run and run not found. |
| 18 | A03 | `examples`: a public staging target for server-side replay (P6), a refused private target, a CI token name and the claimed guest project name. |
| 19 | Decision 39, PO-D | Every signing-secret last-4 hint removed (`…3f9a`, `…7c4d`, `…b21e`): signature details, `copy.signature.valid`, agent secrets (now `state: set / not set`). CI token last 4 stays (it is a token we issue, not a secret you hold). |
| 20 | Decision 39, PO-E, PO-F | Pairing code `HXR-4K7` → `7KQ2M-XR9TD` (10 symbols); command pins `@webhooktrap/agent@1`; terminal transcripts for pairing (`[y/N]`, origin first), decline and reconfirmation after a claim; agent states `awaiting_confirmation`, `needs_reconfirm`, `pair_declined`. |
| 21 | Decision 39, PO-A, PO-C | Share link `/r/{runId}` → `/s/q7Vf2LmX9pK4wT8nR3bZcD` (a separate token; a reshare rotates it to `/s/Hc3mW8tZ1qN5rB7xK2vYpL`). `/r/{runId}` is owner-only. New `copy.share` group; CI uploads private unless `--share`. |
| 22 | Decision 38, PV-2 to PV-14 | Probe display: Run #3's `orders.count` is a count (PV-2). New `copy.probe_display` group with the PV-14 wording, and `examples.code_reading` for code-valued readings (owner default, owner with `show: value`, unchanged, widened, invalid). |
| 23 | Decision 38, PV-8 | Hosted copy never names the reset command or shows your probe definitions: `aborted_reset_failed` and `couldnt_start_reset_failed` keep the command only in a `cli` variant; F4's fix is the pack template; new `run.checks_hosted`. |
| 24 | Decision 39, TM-17 | Run provenance line (`run.provenance`, `copy.provenance`) for agent, CLI, CI and server-runner runs. |
| 25 | QA v3 M4, M7; owner offline | `examples.shopify_server_draft` (Shopify why, citation and mutation copy; citations [pending A01]); guest meter values for the waiting and first-four moments; `copy.agent.offline_finished_run`; claim toast says share links were turned off. |
| 26 | A01 rev. 2 §16 rows 32–38 | #09 detail drops "Expected sha256=… received sha256=…" (SIG-3). F4 evidence D6 · D15, scenarios Shuffled and Burst (PRB-12a); Burst strip reading `○ F4 Info · 0 → 1 (final)`. Level 2 findings read "Level 2 · judged on maya-mbp" (RUN-9). Provenance copy from RUN-8. CI token by name only (no `…9f2c`). |
| 27 | Decision 44; A05 rev. 4 domain §2.1–2.4; A09 §12 | New `account_security` block: Maya's passkey and security key (plus a never-used passkey and a blocked key for the states sheet), GitHub link, authenticator app, 10 recovery codes of 50 bits, a recovery request with its 72-hour and RC-2 dates, the post-recovery security log, and three fictional stand-ins for the imported 1.x accounts. New §7; Changes renumbered §8. |
| 28 | A05 revision 5 (U-10 to U-19); domain §2.1, §2.2, §2.5–2.9; contracts §3.4 | `account_security` additions only (every earlier value unchanged): Maya's three sign-in sessions with truncated networks, the 30-day security log with action codes and display copy, a pending email change (masked), the sign-in throttle example time, password policy 12–128 and link lifetimes, Old YubiKey flag count and user-handle note, a new Spare YubiKey blocked twice (can't unblock), the post-recovery review (completion, lock end, agents, CI tokens across two projects, review log, decline page) and the account-deletion summary [pending A05]. |

