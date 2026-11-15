# Generic Node toolchain. Trusted official bases only; no repository code.
FROM node:22-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends git sqlite3 ca-certificates python3 make g++ \
  && apt-get clean && find /var/lib/apt/lists -type f -delete \
  && (userdel node || true) && useradd -u 10001 -m -d /home/runner runner \
  && mkdir -p /work /wt && chown runner:runner /work /wt
COPY proto /opt/wt/proto
ENV HOME=/tmp/home npm_config_cache=/tmp/home/.npm
USER 10001
WORKDIR /work
LABEL wt.proxyrun="1"
