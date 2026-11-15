# Cache (infrastructure adapter)

Cache wiring over the Redis-compatible store; one of three places allowed to touch Redis/Valkey (rule 3).

`ShortLivedCache`: fail-soft get/put/forget for short-lived lookups (capture-key resolution, DL-2); a store outage is a miss, never an error on the ingest path.

`StoreFailures`: throttled reporting of fail-open store outages (A09 S3-11): one `report()` and one `wt.store_failure` warning (`kind`, `suppressed`) per kind per minute, throttled in a local store.
