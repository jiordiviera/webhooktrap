# Provider response budgets: verified sources

Owner: A01 (Product Strategist) · Level C3 (shown on screen as fact) · Checked: 2026-11-14

This file replaces the three `VERIFY` budgets in `realistic-data.md` ("Provider budgets used by the UI"). Every number below is either **official** (in the provider's own docs or support centre), **secondary** (third-party, clearly labelled), or **undocumented**. The UI may only present an official number as the provider's limit. Anything else must be labelled as a Webhook Trap default.

Quoted text is limited to a short search phrase per row so a reviewer can find the sentence on the page. Everything else is paraphrased.

## 1. Response-time limits

| Provider | Timeout as documented | Confidence | Source URL | Search phrase on page | Product budget (Webhook Trap) |
| --- | --- | --- | --- | --- | --- |
| Stripe | **No number published.** Docs say to return a 2xx quickly, before any slow work. The "Timed out" status means the server took too long. Stripe's support article on "Timed out" also gives no number. | Undocumented | https://docs.stripe.com/webhooks (section "Quickly return a 2xx response"); https://docs.stripe.com/events/manage-webhook-endpoints ("Fix HTTP status codes" table); https://support.stripe.com/questions/webhooks-how-to-investigate-and-fix-timed-out | "quickly return a successful status code" | **10 000 ms, product default** |
| Shopify | **5 s.** A delivery fails if the app doesn't respond within five seconds. | Official | https://shopify.dev/docs/apps/build/webhooks/troubleshooting-webhooks | "respond within five seconds" | **5 000 ms, from Shopify docs** |
| GitHub | **10 s.** GitHub closes the connection and counts the delivery as failed. | Official | https://docs.github.com/en/webhooks/using-webhooks/best-practices-for-using-webhooks (section "Respond within 10 seconds") | "within 10 seconds of receiving" | **10 000 ms, from GitHub docs** |
| Twilio | **15 s total by default.** Defaults: connect timeout 5 000 ms, read timeout 15 000 ms, total time 15 000 ms (covers all timeouts and retries). Each can be overridden per URL up to 15 000 ms (connect up to 10 000 ms). Voice call requests have a hard 15 s cap. | Official | https://www.twilio.com/docs/usage/webhooks/webhooks-connection-overrides | "hard upper timeout of 15 seconds" | **15 000 ms, from Twilio docs** (label as "Twilio default") |
| Slack (Events API) | **3 s.** Slower responses count as failed attempts, and retries carry the reason `http_timeout`. | Official | https://docs.slack.dev/apis/events-api/ (section "Responding to events") | "HTTP 2xx within three seconds" | **3 000 ms, from Slack docs** |
| Standard Webhooks (spec) | **Guidance for senders, not a consumer limit:** recommends a request timeout between 15 and 30 s. | Official (spec) | https://github.com/standard-webhooks/standard-webhooks/blob/main/spec/standard-webhooks.md (section "Request timeouts"). The site https://www.standardwebhooks.com/ states no number and links to the spec. | "somewhere between 15 and 30s" | Not a budget. Use it only to justify the generic default below. |
| Unknown / generic | Not applicable | Not applicable | Not applicable | Not applicable | **10 000 ms, product default** |

### Stripe: secondary figures (do NOT display as Stripe's)

Third-party sources disagree, and none cites Stripe:
- Svix ("Webhook timeout best practices") also says Stripe publishes no number: https://www.svix.com/resources/webhook-university/reliability/webhook-timeout-best-practices/
- Search results surfaced blog and forum posts claiming 10–20 s, 20 s or 30 s (for example a 2016 Q&A noting a test timed out after about 30 s). These are secondary and inconsistent. Not used.

Why 10 s for Stripe: it's the strictest of the commonly reported values, it matches GitHub's documented limit, and it flags slow handlers before any plausible real cutoff. It's a Webhook Trap choice, not Stripe's limit.

## 2. Retry and auto-disable behaviour

| Provider | Retries | Auto-disable | Confidence | Source |
| --- | --- | --- | --- | --- |
| Stripe | Live mode: retries with exponential backoff **for up to three days**. Sandbox: **three retries over a few hours**. A manual resend doesn't cancel automatic retries. A disabled or deleted endpoint gets no further retries. Search phrase: "for up to three days". | Stripe **disables an endpoint after several days** of errors or no response (no exact day count). The page doesn't mention warning emails. Forum posts describe warning emails, but that's secondary and unconfirmed. Search phrase: "for several days". | Retries: official. Disable: official but vague. Emails: secondary. | https://docs.stripe.com/webhooks#automatic-retries ; https://support.stripe.com/questions/why-is-stripe-trying-to-reach-my-webhook-endpoints |
| Shopify | **Up to 8 retries within four hours.** After 8 failed attempts, Shopify stops trying. Search phrase: "eight times in a four-hour period". | If failures continue, **the subscription is removed** and must be recreated (no exact threshold given). The page doesn't mention warning emails. | Official | https://shopify.dev/docs/apps/build/webhooks/troubleshooting-webhooks |
| GitHub | **No automatic retries.** Failed deliveries can be redelivered manually (or by your own script) **for 3 days**. Search phrase: "does not automatically redeliver". | No auto-disable found in the docs. | Official (no auto-disable: undocumented) | https://docs.github.com/en/webhooks/using-webhooks/handling-failed-webhook-deliveries ; https://docs.github.com/en/webhooks/testing-and-troubleshooting-webhooks/redelivering-webhooks |
| Twilio | Default **1 retry**, on connect failure only (`rc=1`, `rp=ct`). Configurable from 0 to 5 retries on 4xx, 5xx, connect or read failures. | Not documented on this page. | Official | https://www.twilio.com/docs/usage/webhooks/webhooks-connection-overrides |
| Slack | **3 retries:** almost immediately, then after 1 minute, then after 5 minutes. Headers: `x-slack-retry-num`, `x-slack-retry-reason`. | Event subscriptions are temporarily disabled when **over 95% of attempts fail within 60 minutes**. Apps under **1,000 events per hour** are exempt. The app owner gets an email. | Official | https://docs.slack.dev/apis/events-api/ |
| Standard Webhooks | Recommends exponential backoff with jitter over several days. Example schedule: immediately, 5 s, 5 min, 30 min, 2 h, 5 h, 10 h, 14 h, 20 h, 24 h (about 75.6 h in total). | Recommends notifying the consumer and disabling after long-running failure. A `410 Gone` response should disable the endpoint right away. | Official (spec, recommendations) | spec URL above, section "Deliverability and reliability" |

### Resolved items from `research.md` §6

- **Shopify retries:** official is **8 retries in 4 hours**. The vendor figure (19 attempts over about 48 h) matches Shopify's earlier policy, now replaced. The Shopify changelog linked from the docs (https://shopify.dev/changelog/updates-to-webhook-retry-mechanism) returned 404 on 2026-11-14, so the date of the change isn't confirmed. Use 8 / 4 h.
- **Stripe auto-disable timing:** still no exact schedule. Stripe's support centre confirms disabling after "several days". Don't show a day count.

## 3. What the UI may state (exact safe wording)

| Provider | Budget label in UI | Safe sentence for tooltips and findings | Do NOT say |
| --- | --- | --- | --- |
| Stripe | `10 s budget · Webhook Trap default` | "Stripe expects a quick 2xx and doesn't publish its timeout. Webhook Trap applies a 10 s default budget." | "Stripe's 10 s limit", "Exceeded Stripe's response budget", "Stripe would have timed out" |
| Stripe retries | — | "Stripe retries failed deliveries with exponential backoff for up to three days in live mode (three times over a few hours in a sandbox)." | "retries N times" (no count is published for live mode) |
| Stripe disable | — | "If an endpoint keeps failing for several days, Stripe disables it." | Any specific day count. "Stripe emails you" (not in official docs) |
| Shopify | `5 s · Shopify limit` | "Shopify fails a delivery that gets no response within 5 seconds, retries up to 8 times over 4 hours, then removes the subscription if failures persist." | "19 retries over 48 hours" |
| GitHub | `10 s · GitHub limit` | "GitHub drops the connection after 10 seconds and marks the delivery failed. It doesn't retry automatically. You can redeliver from the last 3 days." | "GitHub will retry" |
| Twilio | `15 s · Twilio default` | "By default Twilio allows 15 seconds in total and retries once if it can't connect. Both are configurable per URL." | "Twilio's limit is 15 s" without "default" (it's overridable for non-voice requests) |
| Slack | `3 s · Slack limit` | "Slack counts a response slower than 3 seconds as failed and retries 3 times (almost immediately, then after 1 min, then after 5 min)." | — |
| Generic | `10 s budget · Webhook Trap default` | "This provider's timeout is unknown. Webhook Trap applies a 10 s default budget. The Standard Webhooks spec recommends senders wait 15–30 s." | — |

## 4. Required changes to `realistic-data.md` (for the owner of that file)

1. Provider budget table: Stripe → `10 000 ms · product default (Stripe publishes no number)`. Shopify → `5 000 ms · official`. GitHub → `10 000 ms · official`. Remove all `VERIFY` marks.
2. F3 title "Exceeded Stripe's response budget" → "Over the 10 s budget for Stripe". Summary "longer than Stripe's 10 000 ms budget" → "longer than Webhook Trap's 10 s budget for Stripe".
3. "Slow budget" rows (around lines 713 and 949): "Slow responses count as failures and trigger retries" is only safe as "If Stripe times out waiting, the delivery counts as failed and is retried. Stripe doesn't publish the timeout." Keep the citation "Stripe docs › Webhooks › Quickly return a 2xx response".
4. "Retry after error" rows (around lines 710 and 946): add "in live mode". The rest is supported by the docs.
5. Don't claim D8 (11 840 ms) or D14 (10 420 ms) would have failed at Stripe. They're over *our* budget only.
