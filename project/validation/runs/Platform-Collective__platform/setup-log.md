[2026-11-14T15:36:59Z] prepare: build images (node toolchain, egress proxy); create networks
[2026-11-14T15:37:21Z] prepare: done (proxy on wtp-platform-collective--platform-int, allowlist in harness/proxy/allowlist)
[2026-11-14T15:37:21Z] setup: start (clone + install in wtp-tool-node:platform-collective--platform, egress only through allowlist proxy)
[2026-11-14T15:37:35Z] setup: exit 0 after 13 s (full output kept in scratchpad, not in the repo)
  egress hosts reached:  github.com 
  egress hosts refused:  
[2026-11-14T15:38:42Z] cutnet: proxy and both networks removed; the repo has no network from now on
[2026-11-14T15:38:42Z] teardown: containers, volume, networks, images removed; secret deleted. leftovers: none
