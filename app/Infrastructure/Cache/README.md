# Cache (infrastructure adapter)

Cache wiring over the Redis-compatible store; one of three places allowed to touch Redis/Valkey (rule 3).

`ShortLivedCache`: fail-soft get/put/forget for short-lived lookups (capture-key resolution, DL-2); a store outage is a miss, never an error on the ingest path.

`StoreFailures`: throttled reporting of fail-open store outages (A09 S3-11): one `report()` and one `wt.store_failure` warning (`kind`, `suppressed`) per kind per minute, throttled in a local store.

`StoreFailures` also adds each line's failures to a per-minute row in `store_failure_minutes` (database, so every container's counts reach `/health` while Valkey is down); `recent(5)` is `/health`'s `store_failures_5m`.

`StoreProbe`: one read of the default store, for `/health`.
