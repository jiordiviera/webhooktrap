[2026-11-14T15:39:12Z] prepare: build images (node toolchain, egress proxy); create networks
[2026-11-14T15:39:13Z] prepare: done (proxy on wtp-selftest--proving-sample-int, allowlist in harness/proxy/allowlist)
[2026-11-14T15:39:13Z] setup: start (clone + install in wtp-tool-node:selftest--proving-sample, egress only through allowlist proxy)
[2026-11-14T15:39:14Z] setup: exit 0 after 1 s (full output kept in scratchpad, not in the repo)
  egress hosts reached:  
  egress hosts refused:  
[2026-11-14T15:39:14Z] cutnet: proxy and both networks removed; the repo has no network from now on
[2026-11-14T15:39:14Z] start: app started with --network none; prober attached to its netns
  netns check: lo 
  netns check: 1
[2026-11-14T15:39:14Z] start: app listening on 127.0.0.1:4510 after 1s
[2026-11-14T15:40:21Z] teardown: containers, volume, networks, images removed; secret deleted. leftovers: none
