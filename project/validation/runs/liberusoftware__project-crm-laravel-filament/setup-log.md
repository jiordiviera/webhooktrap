[2026-11-14T15:29:40Z] prepare: build images (php toolchain, egress proxy); create networks
[2026-11-14T15:31:16Z] prepare: done (proxy on wtp-liberusoftware--project-crm-laravel-filament-int, allowlist in harness/proxy/allowlist)
[2026-11-14T15:31:21Z] setup: start (clone + install in wtp-tool-php:liberusoftware--project-crm-laravel-filament, egress only through allowlist proxy)
[2026-11-14T15:31:23Z] setup: exit 0 after 2 s (full output kept in scratchpad, not in the repo)
[2026-11-14T15:34:19Z] setup: start (clone + install in wtp-tool-php:liberusoftware--project-crm-laravel-filament, egress only through allowlist proxy)
[2026-11-14T15:34:52Z] setup: exit 0 after 33 s (full output kept in scratchpad, not in the repo)
[2026-11-14T15:35:17Z] cutnet: proxy and both networks removed; the repo has no network from now on
[2026-11-14T15:35:17Z] start: app started with --network none; prober attached to its netns
  netns check: lo 
  netns check: 1
[2026-11-14T15:35:18Z] start: app listening on 127.0.0.1:8000 after 2s
[2026-11-14T15:36:49Z] teardown: containers, volume, networks, images removed; secret deleted. leftovers: none
