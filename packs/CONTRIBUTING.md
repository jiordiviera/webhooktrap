# Contributing a provider pack

A pack tells Webhook Trap how one provider sends webhooks. The server uses it to recognise and group
captures, and the engine uses it to sign, replay and judge them. A wrong pack produces wrong verdicts,
so a small, correct pack is worth more than a large one. Every claim in a pack needs a source.

## What a pack is

- `packs/<id>/<version>.json`, validated by `packs/_schema/pack.schema.json`.
- An entry in `packs/manifest.json`. The order of `packs` is the detection order, and Standard Webhooks
  stays last because its headers are generic.
- A signing scheme in `agent/signing` (`Sign`, `Verify`, `Headers`), listed in `agent/pack.Schemes` and
  in the schema's `signing.scheme` enum. Reuse an existing scheme when the provider follows one
  (Standard Webhooks, for example).
- At least two synthetic fixtures in `agent/testdata/fixtures/<id>/`, made up for the test, with no
  real data.
- A guide in `docs/guides/<id>.mdx`, listed in `docs/docs.json`.

## Before you write it: the facts and their sources

Collect these from the provider's own documentation. Blog posts, SDK source and observed traffic are
not enough on their own.

| Fact                                                 | Pack key              | If the provider doesn't document it                                                   |
| ---------------------------------------------------- | --------------------- | ------------------------------------------------------------------------------------- |
| Signature header, signed string, algorithm, encoding | `signing`             | No pack. Use `none` only for providers that really sign nothing.                      |
| Timestamp tolerance                                  | `signing.tolerance_s` | Say where the number comes from (SDK default, recommendation) in the guide.           |
| Response-time limit                                  | `budget`              | `"source": "default"`, `"documented": false`, label "Webhook Trap default".           |
| Duplicates can happen                                | `duplicate`           | `"verified": false`: Twice is n/a ("not verified for …").                             |
| Automatic retries                                    | `retry`               | `"verified": false`; `"supported": false` only with a source saying it never retries. |
| Event type, event id, API version                    | `event`               | Leave the key out.                                                                    |
| Objects to group by                                  | `grouping`            | `"fallback": "data_id"` or `null`.                                                    |

Rules the tests enforce (`agent/internal/packdata/claims_test.go`):

- A `verified` duplicate or retry rule has an `https://` citation.
- A documented budget names `"<Provider> docs"`, and a default budget says `"default"`.
- Every citation has a label and an `https://` URL.
- A signed pack added from issue #19 on has a citation for `accepts_tampered`.

Write what the provider guarantees as theirs ("Slack retries up to three times") and what we chose as
ours ("Webhook Trap default"). Never turn a default into a provider promise.

## Tests

1. **Signature vectors.** Add a test in `agent/signing/signing_test.go` with the provider's documented
   example if there is one. Otherwise use a synthetic secret, and add the scheme to
   `TestRoundTripAndFailureModes`. That test covers a valid delivery, a tampered body, a missing header
   and, for signed timestamps, a late one.
2. **Fixtures.** `go test ./plan/` builds a plan twice from your fixtures, checks it is deterministic,
   and checks that every scenario either sends something or gives its n/a reason in words.
3. **Derivation.** Add your provider to the detection dataset and the fixture case in
   `tests/Feature/Capture/DerivationTest.php`.

Then run:

```bash
cd agent && mise exec go@1.27.4 -- go generate ./internal/packdata   # copies packs/ into the engine
cd agent && mise exec go@1.27.4 -- go test ./...                     # schema, claims, signing, plans
php artisan test tests/Feature/Capture/DerivationTest.php
```

CI runs the same tests. `TestInSync` fails when you forget `go generate`, and `TestPacksMatchSchema`
fails on a key the schema doesn't know.

## Versions

A shipped pack file never changes. Fixing or extending a pack means a new file (`1.0.1.json`,
`1.1.0.json`) and a new version in the manifest. Runs record the pack version they used, so old
verdicts stay explainable.

## Next providers

Chosen from user demand, and from what can be verified:

| Provider           | Status         | Why                                                                                                                                           |
| ------------------ | -------------- | --------------------------------------------------------------------------------------------------------------------------------------------- |
| Slack (Events API) | shipped, 1.0.0 | Documented signature with a test vector, a 3 s limit and retries.                                                                             |
| Paddle Billing     | shipped, 1.0.0 | Documented signature, a 5 s limit, retries, duplicates and no ordering guarantee. Webhook Trap bills with it (decision 78).                   |
| Twilio             | coming         | Signs the full request URL plus sorted form fields with HMAC-SHA1. The engine has to sign for the target URL, which is a new input to `Sign`. |
| Adyen              | coming         | The HMAC is inside each notification item of the body, not in a header. It needs a body-signing scheme and its own tamper rule.               |
