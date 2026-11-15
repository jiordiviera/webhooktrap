# Proxy validation: candidate repository list (A01, search only)

Protocol: `project/product/spec/scenarios-and-judge.md` section 15 (pre-registered by decision 35).
Status: **proposal for PO approval. Nothing was cloned, downloaded, installed or run.** Only GitHub API metadata, code-search results and individual file contents (read via `gh api`, to check eligibility) were read.

## 1. Pre-registered inputs

- Snapshot date: **2026-11-14** (searches run on 2026-11-14, UTC timestamps below).
- Salt: **`a0e7bf395ede5cdf52a3a8ddbd7a0dbf`** (decision 35).
- Hash input (exact concatenation): `owner/repo` + salt, no separator, UTF-8, repository name in the casing returned by GitHub, e.g. `lukevella/rallly` + `a0e7bf395ede5cdf52a3a8ddbd7a0dbf`. Ordering: SHA-256 hex digest, ascending lexicographic.
- 18-month window: default-branch last commit on or after 2025-05-14.

## 2. Fixed queries (section 15.2 rule 1), commands, counts

Each query was run once with `gh api -X GET search/code -f q=<query> -f per_page=100 -f page=N` (read-only; GitHub code search caps at 1000 results per query, so 10 pages). No qualifiers were added. Raw pages: `project/validation/raw/search_<provider>_<qN>_p<N>.json`; full log with every page and timestamp: `raw/search-log.tsv`.

| Provider | Command (query as given in section 15) | First page timestamp (UTC) | total_count reported by GitHub | Items retrieved |
| --- | --- | --- | --- | --- |
| stripe | gh api -X GET search/code -f q='constructEvent' -f per_page=100 -f page=N (N = 1..10) | 2026-11-14T14:08:11Z | 134144 | 1000 |
| stripe | gh api -X GET search/code -f q='construct_event' -f per_page=100 -f page=N (N = 1..10) | 2026-11-14T14:09:38Z | 37504 | 1000 |
| stripe | gh api -X GET search/code -f q='Webhook::constructEvent' -f per_page=100 -f page=N (N = 1..10) | 2026-11-14T14:11:08Z | 6144 | 1000 |
| github | gh api -X GET search/code -f q='X-Hub-Signature-256' -f per_page=100 -f page=N (N = 1..10) | 2026-11-14T14:12:31Z | 131072 | 1000 |
| github | gh api -X GET search/code -f q='@octokit/webhooks' -f per_page=100 -f page=N (N = 1..10) | 2026-11-14T14:13:58Z | 23424 | 1000 |
| shopify | gh api -X GET search/code -f q='X-Shopify-Hmac-Sha256' -f per_page=100 -f page=N (N = 1..10) | 2026-11-14T14:15:19Z | 9600 | 1000 |
| standardwebhooks | gh api -X GET search/code -f q='standardwebhooks' -f per_page=100 -f page=N (N = 1..10) | 2026-11-14T14:16:44Z | 112128 | 1000 |
| standardwebhooks | gh api -X GET search/code -f q='svix verify' -f per_page=100 -f page=N (N = 1..10) | 2026-11-14T14:18:12Z | 62464 | 1000 |

Unique repositories after de-duplication (union of a provider's queries): Stripe 2062, GitHub 1622, Shopify 659, Standard Webhooks 1371. 70 repositories appear under more than one provider. Union file: `raw/candidates-by-provider.json`.

Additional read-only API calls used for eligibility (logged): GraphQL repository metadata in batches of 40 (`raw/graphql/`, `raw/graphql-log.tsv`); an early REST metadata pass for the first ~100-150 repos per provider (`raw/meta/`, superseded by GraphQL); per-repository code searches `repo:<owner/repo> <term>` for handler location (`raw/scoped/`, `raw/scoped-log.tsv`); single-file content reads for handler verification (snippets in `raw/handlers/`). A first attempt at scoped searches used `OR`/parentheses, failed with a query parse error, and was discarded (`raw/_failed/`).

## 3. Eligibility filters (rule 2) and exclusions

Automatic filters applied to **all** unique repositories (GraphQL metadata). A repository can fail several rules.

| Rule | Test used |
| --- | --- |
| E1 | public |
| E2 | licence SPDX id in an OSI-approved list (MIT, Apache-2.0, BSD-2/3, ISC, GPL-2/3, LGPL, AGPL-3, MPL-2.0, 0BSD, EPL, Zlib, BSL-1.0, MIT-0, Artistic-2.0, EUPL-1.2, CDDL-1.0); no licence or NOASSERTION counts as failing |
| E3 | not a fork |
| E4 | not archived |
| E5 | last commit on the default branch on or after 2025-05-14 |
| E6 | at least 50 stars |

Counts of repositories failing each rule (not exclusive):

| Provider | Unique repos | E0 unavailable | E2 licence | E5 no commit in 18 months | E6 under 50 stars | E1/E3/E4 | Pass E1-E6 |
| --- | --- | --- | --- | --- | --- | --- | --- |
| stripe | 2062 | 1 | 1516 | 568 | 1977 | 54 |
| github | 1622 | 0 | 838 | 236 | 1498 | 90 |
| shopify | 659 | 0 | 449 | 79 | 630 | 18 |
| standardwebhooks | 1371 | 0 | 1029 | 134 | 1295 | 54 |

Rule 2 "handler is part of an application, not a library" is not automatable. It was applied by hand, **in hash order**, to repositories that passed E1-E6, by running a repo-scoped code search for the provider terms and reading the handler file. Checking stopped once quotas and reserve were filled (all four providers verified through at least the hash cutoff of the reserve). Manual exclusion codes:

- E7: no source-file hit in a repo-scoped search (documentation hits only, or none).
- E8: library, SDK or framework, not an application.
- E9: the term matched something unrelated to the provider (false positive, another provider's use of the same header, outbound signing only).
- E10: not an application (skill or prompt collection, evaluation or experiment output).
- E11: handler present only as commented-out code.
- E12: hits only in test files.
- S: sample stratum (reported separately, does not count).

The full per-repository exclusion table, all 5635 unique repositories with every rule that applies, is in **`project/validation/raw/exclusions-all.csv`** (columns: repo, providers, sha256 prefix, status, rules). Repositories that passed E1-E6 but were never reached by the manual check are marked "handler/application check not run (beyond selection cutoff)". Manual exclusions among the repositories reached, in hash order per provider:

| Repository | Manual rule |
| --- | --- |
| Anil-matcha/open-agent-email | E8-library-or-SDK-not-application |
| Aurealibe/claude-config | E10-not-an-application(skills/prompts/evals/experiment output) |
| DocHub-ULB/DocHub | E7-no-source-file-hit-in-repo-scoped-search(docs/none) |
| Goldziher/ai-rulez | E10-not-an-application(skills/prompts/evals/experiment output) |
| Hookflo/tern | E8-library-or-SDK-not-application |
| Jwuthri/Tracely-ai | E7-no-source-file-hit-in-repo-scoped-search(docs/none) |
| Larens94/codedna | E10-not-an-application(skills/prompts/evals/experiment output) |
| OSS-Docs-Tools/code-owner-self-merge | E7-no-source-file-hit-in-repo-scoped-search(docs/none) |
| Rails-Designer/fuik | E8-library-or-SDK-not-application |
| SaladTechnologies/comfyui-api | E9-term-hit-unrelated-to-provider(false positive / other provider) |
| Shopify/shopify-api-ruby | E8-library-or-SDK-not-application |
| The-DevOps-Daily/devops-daily | E9-term-hit-unrelated-to-provider(false positive / other provider) |
| agentscope-ai/agentscope-java | E8-library-or-SDK-not-application |
| aimeos/pagible | E8-library-or-SDK-not-application |
| aj-geddes/useful-ai-prompts | E10-not-an-application(skills/prompts/evals/experiment output) |
| ancoleman/ai-design-components | E10-not-an-application(skills/prompts/evals/experiment output) |
| anolilab/lunora | E9-term-hit-unrelated-to-provider(false positive / other provider) |
| arlyon/async-stripe | E8-library-or-SDK-not-application |
| arul28/ADE | E9-term-hit-unrelated-to-provider(false positive / other provider) |
| aws-samples/anthropic-on-aws | S-sample-stratum(not main) |
| binary-cats/laravel-mailgun-webhooks | E8-library-or-SDK-not-application |
| boxlite-ai/boxlite | E9-term-hit-unrelated-to-provider(false positive / other provider) |
| burrunan/gradle-cache-action | E8-library-or-SDK-not-application |
| buzzkit-dev/buzzkit | E12-tests-only-in-repo-scope |
| chen86860/easy-complete | E9-term-hit-unrelated-to-provider(false positive / other provider) |
| doublewordai/control-layer | E9-term-hit-unrelated-to-provider(false positive / other provider) |
| ericdude4/shopifex | E8-library-or-SDK-not-application |
| finic-ai/kairos | E12-tests-only-in-repo-scope |
| finsilabs/awesome-ecommerce-skills | E10-not-an-application(skills/prompts/evals/experiment output) |
| fknMega/Aether | E7-no-source-file-hit-in-repo-scoped-search(docs/none) |
| gardener/dashboard | E12-tests-only-in-repo-scope |
| guardana/guardana | E9-term-hit-unrelated-to-provider(false positive / other provider) |
| hiteshchoudhary/theoven | E8-library-or-SDK-not-application |
| inline0/blockstudio | E7-no-source-file-hit-in-repo-scoped-search(docs/none) |
| int128/datadog-actions-metrics | E9-term-hit-unrelated-to-provider(false positive / other provider) |
| jcberquist/stripe-cfml | E8-library-or-SDK-not-application |
| jeremylongshore/tons-of-skills-marketplace | E10-not-an-application(skills/prompts/evals/experiment output) |
| kendryte/k230_sdk | E9-term-hit-unrelated-to-provider(false positive / other provider) |
| luizomf/vps_deploy_template | E7-no-source-file-hit-in-repo-scoped-search(docs/none) |
| mindspore-ai/mindspore | E9-term-hit-unrelated-to-provider(false positive / other provider) |
| netdata/netdata | E9-term-hit-unrelated-to-provider(false positive / other provider) |
| nextcloud/twofactor_gateway | E9-term-hit-unrelated-to-provider(false positive / other provider) |
| nfraxlab/svc-infra | E8-library-or-SDK-not-application |
| nitrosend/typescript-sdk | E8-library-or-SDK-not-application |
| no-session/pstack | E10-not-an-application(skills/prompts/evals/experiment output) |
| nuke-on-rails/nuke-on-rails | E10-not-an-application(skills/prompts/evals/experiment output) |
| obsei/obsei | E9-term-hit-unrelated-to-provider(false positive / other provider) |
| oceanbase/oceanbase | E9-term-hit-unrelated-to-provider(false positive / other provider) |
| octokit/app.js | E8-library-or-SDK-not-application |
| octokit/webhooks.js | E8-library-or-SDK-not-application |
| openobserve/openobserve | E9-term-hit-unrelated-to-provider(false positive / other provider) |
| opf/openproject | E7-no-source-file-hit-in-repo-scoped-search(docs/none) |
| peakoss/anti-slop | E9-term-hit-unrelated-to-provider(false positive / other provider) |
| pgraug/dhyve-src | E9-term-hit-unrelated-to-provider(false positive / other provider) |
| piusalfred/whatsapp | E9-term-hit-unrelated-to-provider(false positive / other provider) |
| pneumaticapp/pneumaticworkflow | E11-handler-commented-out |
| rails/ai-evals | E10-not-an-application(skills/prompts/evals/experiment output) |
| reshaprio/reshapr | E7-no-source-file-hit-in-repo-scoped-search(docs/none) |
| rush86999/atom-shopify-only | E12-tests-only-in-repo-scope |
| scalacenter/sbt-dependency-submission | E9-term-hit-unrelated-to-provider(false positive / other provider) |
| scitix/siclaw | E7-no-source-file-hit-in-repo-scoped-search(docs/none) |
| shehryarsaroya/agenttransfer | E7-no-source-file-hit-in-repo-scoped-search(docs/none) |
| shopperlabs/shopper | E8-library-or-SDK-not-application |
| sickn33/agentic-awesome-skills | E10-not-an-application(skills/prompts/evals/experiment output) |
| siddhi-io/siddhi | E9-term-hit-unrelated-to-provider(false positive / other provider) |
| simota/agent-skills | E10-not-an-application(skills/prompts/evals/experiment output) |
| solomon2773/nora | E9-term-hit-unrelated-to-provider(false positive / other provider) |
| thomaspoignant/go-feature-flag | E9-term-hit-unrelated-to-provider(false positive / other provider) |
| toddcooke/namechecker | E7-no-source-file-hit-in-repo-scoped-search(docs/none) |
| vercel/chat | E8-library-or-SDK-not-application |
| verikod/hector | E9-term-hit-unrelated-to-provider(false positive / other provider) |
| vuetifyjs/mcp | E7-no-source-file-hit-in-repo-scoped-search(docs/none) |
| w-winter/dot314 | E7-no-source-file-hit-in-repo-scoped-search(docs/none) |
| workos/workos-go | E8-library-or-SDK-not-application |

## 4. Main list (ordered by hash within provider; quota Stripe 6, GitHub 4, Shopify 3, Standard Webhooks 2 = 15)

Columns: repo, URL, stars, last push (default-branch last commit), licence, default branch, handler file(s) located, hash prefix (first 12 hex of SHA-256).

### Stripe (6)

| Repository | URL | Stars | Last push | Licence | Default branch | Handler file(s) | Hash prefix |
| --- | --- | --- | --- | --- | --- | --- | --- |
| liberusoftware/project-crm-laravel-filament | https://github.com/liberusoftware/project-crm-laravel-filament | 130 | 2026-11-13 (default-branch commit 2026-11-13) | MIT | main | modules/crm-saas-packaging/src/Legacy/Http/Controllers/BillingController.php (Webhook::constructEvent) | 050220a8f4a2 |
| lukevella/rallly | https://github.com/lukevella/rallly | 5358 | 2026-11-14 (default-branch commit 2026-11-13) | AGPL-3.0 | main | apps/web/src/app/api/stripe/webhook/route.ts (constructEvent) | 4208e6c1f84c |
| firetix/vibe-coding-penetration-tester | https://github.com/firetix/vibe-coding-penetration-tester | 180 | 2026-10-05 (default-branch commit 2026-06-04) | Apache-2.0 | main | web_api/routes/billing.py (stripe.Webhook.construct_event) | 436b7a52e916 |
| open-v2ai/build-ai-template | https://github.com/open-v2ai/build-ai-template | 71 | 2026-10-26 (default-branch commit 2026-10-26) | Apache-2.0 | main | api/app/utils/pay.py (stripe.Webhook.construct_event) | 46f71546ea58 |
| pararell/eshop_mean | https://github.com/pararell/eshop_mean | 114 | 2026-11-11 (default-branch commit 2026-10-29) | MIT | master | server/src/payments/payments.service.ts (webhooks.constructEvent) | 50057be888ff |
| speedpy/speedpy | https://github.com/speedpy/speedpy | 82 | 2026-11-13 (default-branch commit 2026-11-13) | MIT | master | mainapp/billing/stripe.py (stripe.Webhook.construct_event) | 5383c0c3be8d |

### GitHub (4)

| Repository | URL | Stars | Last push | Licence | Default branch | Handler file(s) | Hash prefix |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Platform-Collective/platform | https://github.com/Platform-Collective/platform | 158 | 2026-11-13 (default-branch commit 2026-11-13) | EPL-2.0 | develop | services/github/pod-github/src/server.ts (@octokit/webhooks createNodeMiddleware) | 01a0e2b43953 |
| classmoji/classmoji | https://github.com/classmoji/classmoji | 91 | 2026-11-14 (default-branch commit 2026-11-14) | AGPL-3.0 | main | apps/hook-station/src/routes/github.ts (x-hub-signature-256, @octokit/webhooks) | 056fece4147e |
| KryptosAI/mcp-observatory | https://github.com/KryptosAI/mcp-observatory | 121 | 2026-11-12 (default-branch commit 2026-11-12) | MIT | main | github-app/src/server.ts (@octokit/webhooks) | 2004f31f9e1a |
| hivecommons/hive | https://github.com/hivecommons/hive | 85 | 2026-11-14 (default-branch commit 2026-11-14) | Apache-2.0 | v5 | src/pkg/github/webhook_secret.go, src/pkg/hub/webhook.go (X-Hub-Signature-256 on /api/webhook/github) | 266decee14a5 |

### Shopify (3)

| Repository | URL | Stars | Last push | Licence | Default branch | Handler file(s) | Hash prefix |
| --- | --- | --- | --- | --- | --- | --- | --- |
| openshiporg/openship | https://github.com/openshiporg/openship | 1673 | 2026-10-24 (default-branch commit 2026-10-24) | AGPL-3.0 | main | features/integrations/lib/webhook-verification.ts (X-Shopify-Hmac-Sha256) | 20048c991993 |
| vercel/shop | https://github.com/vercel/shop | 136 | 2026-11-08 (default-branch commit 2026-11-08) | MIT | main | app/api/webhooks/shopify/route.ts (HMAC check) | 530c457bde92 |
| chattermate/chattermate.chat | https://github.com/chattermate/chattermate.chat | 168 | 2026-11-14 (default-branch commit 2026-11-14) | Apache-2.0 | main | backend/app/services/shopify_helper_service.py (verify_shopify_webhook) | 6b5c06085ded |

### Standard Webhooks (2)

| Repository | URL | Stars | Last push | Licence | Default branch | Handler file(s) | Hash prefix |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Asymmetric-al/core | https://github.com/Asymmetric-al/core | 370 | 2026-11-14 (default-branch commit 2026-11-13) | AGPL-3.0 | develop | packages/email/resend/webhook.ts (svix-* headers verify) | 012a80bfa42a |
| seldonframe/seldonframe | https://github.com/seldonframe/seldonframe | 70 | 2026-10-09 (default-branch commit 2026-08-24) | AGPL-3.0 | main | packages/crm/src/lib/agents/voice/openai-webhook-verify.ts (Standard Webhooks, hand-rolled) | 045d33a34675 |

No provider ran short of candidates, so the "remaining slots go to Stripe" clause was not used.

## 5. Reserve (next 10, rule 15.5 "Extend")

Reading used: the next 10 eligible repositories in one merged list (all providers pooled, ascending hash, main picks removed). Handler and application checks were completed for every repository with a lower hash than the 10th reserve entry.

| Repository | URL | Stars | Last push | Licence | Default branch | Handler file(s) | Hash prefix | Provider |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| langwatch/langwatch | https://github.com/langwatch/langwatch | 5032 | 2026-11-14 (default-branch commit 2026-11-14) | Apache-2.0 | main | modules/github/process/src/rules/github-webhook.rules.ts | 28193d205b28  github |
| rush86999/atom | https://github.com/rush86999/atom | 914 | 2026-11-14 (default-branch commit 2026-11-14) | AGPL-3.0 | main | backend/core/webhook_security.py (GitHub verify) | 375d2fbee753  github |
| steipete/ReleaseBar | https://github.com/steipete/ReleaseBar | 90 | 2026-11-14 (default-branch commit 2026-11-14) | MIT | main | worker/webhook.ts | 3860eedf07c8  github |
| spacesdrive/twiligent | https://github.com/spacesdrive/twiligent | 77 | 2026-11-05 (default-branch commit 2026-11-05) | MIT | main | backend/lib/webhookSignature.ts (Standard Webhooks) | 56b8512e2c45  standardwebhooks |
| ericschmar/moribito | https://github.com/ericschmar/moribito | 282 | 2026-01-24 (default-branch commit 2026-01-10) | MIT | develop | moribito-licenser/src/worker.ts (constructEventAsync) | 5bd2d966ed4d  stripe |
| AlwaysCuriousCo/package-pipeline | https://github.com/AlwaysCuriousCo/package-pipeline | 148 | 2026-10-26 (default-branch commit 2026-10-26) | MIT | main | app/Merchants/Stripe/StripeClient.php (Webhook::constructEvent) | 611ef6cb7efa  stripe |
| jellydn/my-ai-tools | https://github.com/jellydn/my-ai-tools | 129 | 2026-11-14 (default-branch commit 2026-11-14) | MIT | main | src/github-bot/app.ts (x-hub-signature-256) | 6211f5c74f0c  github |
| getsentry/junior | https://github.com/getsentry/junior | 421 | 2026-11-14 (default-branch commit 2026-11-14) | Apache-2.0 | main | packages/junior-github/src/webhooks/handler.ts | 62a040df65a4  github |
| electron/sheriff | https://github.com/electron/sheriff | 153 | 2026-10-22 (default-branch commit 2026-10-22) | MIT | main | src/index.ts (@octokit/webhooks createNodeMiddleware) | 640845756652  github |
| matrix-org/matrix-hookshot | https://github.com/matrix-org/matrix-hookshot | 463 | 2026-11-11 (default-branch commit 2026-11-11) | Apache-2.0 | main | src/Webhooks.ts, src/github/Router.ts (X-Hub-Signature-256) | 660533e35c85  github |

Reserve per provider is therefore GitHub 7, Stripe 2, Standard Webhooks 1, Shopify 0 (see ambiguity 4).

Next eligible per provider after the reserve cutoff (for the PO, if the per-provider reading is preferred): Stripe: mataroablog/mataroa (6dfa48a7b4), CuriousLearner/django-keel (733c8f0d00, a project template), Metaculus/metaculus (734662daf1); Shopify: osaurus-ai/osaurus (7232f1a6a2), nirholas/three.ws (a641155d6b); Standard Webhooks: bighadj22/Wocart (6b5172b1f6), pug-sh/pug (8b5027a16c). These were checked but are not in the merged reserve.

## 6. Samples stratum (reported separately; not decisive)

Encountered but not exhaustively enumerated: `aws-samples/anthropic-on-aws` (GitHub signature verified in a Lambda handler), `stripe-samples/accept-a-payment` and `stripe-samples/identity` (passed E1-E6, handler not checked). A full samples list was not requested for this step.

## 7. Ambiguities for the PO (most literal reading applied in each case)

1. **Query `svix verify`.** Run unquoted (both terms must occur in a file), not as an exact phrase. A phrase search would return far fewer results.
2. **Search coverage.** Code search returns at most 1000 results per query, in GitHub's default relevance order, so the candidate pool is a sample of all matching repositories, not the full set (total_count is 6k-134k per query). Section 15.2 rule 1 does not say how to handle the cap. Search terms also match unrelated code (e.g. `construct_event` in kernel sources), which is why the manual handler check was needed.
3. **Repository in several providers' lists** (70). Each repository keeps one hash; a repository can be picked only once, so a pick for an earlier provider (Stripe, GitHub, Shopify, Standard Webhooks, in quota order) removes it from later ones. No collision occurred among the picks.
4. **Reserve of 10.** Rule 15.5 says "the next 10 repositories from the ordered list" without saying whether the list is per provider or pooled. A merged pool was used, which gave GitHub 7 of 10. Per-provider continuation lists are in section 5 if the PO prefers that.
5. **"Application, not a library".** No criterion is given. Applied as: a deployed service or app whose handler writes state or calls services. Excluded: SDKs, framework packages, GitHub Actions, evaluation output, skill/prompt collections. Judgement calls: `shopperlabs/shopper` (Composer driver package inside an e-commerce admin framework) excluded as a library; `doublewordai/control-layer` excluded because it only signs outgoing Standard Webhooks; `nextcloud/twofactor_gateway` and `piusalfred/whatsapp` excluded because `X-Hub-Signature-256` is used there for WhatsApp, not GitHub.
6. **Templates and boilerplates.** Rule 15.2.3 defines the samples stratum as "official provider sample repos and tutorials". Boilerplates and templates are not official provider samples, and their handlers write state, so they stay in the **main** stratum. Two main Stripe picks are of that kind: `open-v2ai/build-ai-template` and `speedpy/speedpy`. If the PO treats templates as samples, they drop out and the next Stripe picks are `ericschmar/moribito` and `AlwaysCuriousCo/package-pipeline` (both already in the reserve for Stripe, so the reserve would then shift by those two).
7. **Legacy code.** `liberusoftware/project-crm-laravel-filament` has its handler under a `Legacy` directory. It is active code in an application, so it stays in.
8. **"A commit in the 18 months".** Tested on the last commit date of the default branch, not on `pushed_at` (which also moves for other branches).
9. **OSI licence list.** Built from memory of the OSI list; Unlicense, CC0 and WTFPL were treated as not OSI-approved. Repositories with no detectable licence fail E2. Failures on this rule alone were not re-audited.
10. **Manual checks ran only as far as needed.** Verification continued in hash order until quotas and reserve were provably fixed, not across every passing repository.

## 8. Files

- This report: `project/validation/proxy-repo-candidates.md`
- Raw results and logs: `project/validation/raw/` (search pages, `search-log.tsv`, `candidates-by-provider.json`, `eligibility-auto.json`, `exclusions-all.csv`, `selection.json`, `graphql/`, `scoped/`, `handlers/`, `meta/`).
