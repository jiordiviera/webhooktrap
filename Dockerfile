# syntax=docker/dockerfile:1
# Webhook Trap 2.0 image. Derived from jiordiviera/laravel-inertia-vps-template (decision 43),
# adapted per project/operations/compose-profile.md and ci-cd.md.
#
# Targets:
#   runtime  the app image: app, reverb, horizon, scheduler and the engine sandbox (one image)
#   runner   the server runner: the engine binary alone, distroless, non-root (ADR-010 §12)
#
# Base images are pinned by digest (resolved 2026-11-19, PHP 8.5.13); Dependabot (docker ecosystem) proposes bumps.

ARG PHP_VERSION=8.5

# ---------------------------------------------------------------------------
# Engine binary (Go), fed to both the runtime and the runner image (H-5).
# ---------------------------------------------------------------------------
FROM golang:1.27.4-bookworm@sha256:4fa7c968ba93f1635670522dbb01a2c78150443e1385746924566079f36570ea AS engine
ARG ENGINE_VERSION=0.0.0-dev
ARG ENGINE_COMMIT=unknown
WORKDIR /src
COPY agent/go.mod ./
RUN go mod download
COPY agent/ ./
RUN CGO_ENABLED=0 go build -trimpath -buildvcs=false \
      -ldflags "-s -w \
        -X github.com/jiordiviera/webhooktrap/agent/internal/version.Version=${ENGINE_VERSION} \
        -X github.com/jiordiviera/webhooktrap/agent/internal/version.Commit=${ENGINE_COMMIT}" \
      -o /out/webhooktrap ./cmd/webhooktrap \
    && /out/webhooktrap version

# ---------------------------------------------------------------------------
# PHP base: FrankenPHP and the extensions every PHP service shares.
# ---------------------------------------------------------------------------
FROM dunglas/frankenphp:1-php${PHP_VERSION}-bookworm@sha256:e673b1932022450620304b18543d9437205fb5ded3daf25fadf7d777fa4cd0b8 AS base
WORKDIR /app
# Realtime (decision 50, compose-profile §3.2): Reverb needs an event-loop extension, or ReactPHP
# falls back to stream_select and dies near 1,014 sockets. Both `uv` and `event` were built and
# loaded on this exact base on 2026-11-15; `uv` is kept because ReactPHP picks ExtUvLoop first and
# the Reverb docs recommend it. `event` is the tested fallback. Never install both.
# argon2id (domain-model §2.1 rev. 6): the build fails if PHP lacks PASSWORD_ARGON2ID.
RUN install-php-extensions bcmath gd intl opcache pcntl pdo_pgsql redis zip uv \
    && php -r 'exit(extension_loaded("uv") ? 0 : 1);' \
    && php -r 'exit(defined("PASSWORD_ARGON2ID") ? 0 : 1);'

# ---------------------------------------------------------------------------
# Build: Composer dependencies and the front-end bundle.
# ---------------------------------------------------------------------------
FROM base AS build
ARG PNPM_VERSION=12.18.0
COPY --from=composer:2@sha256:ca11f643d7791ebdd90a34fc20caf11380d434007ae539a9ea75cd9212e13dca /usr/bin/composer /usr/bin/composer
COPY --from=node:22-bookworm-slim@sha256:e9f565d32859f0cbfb52125eafe8be39390f24dac302dc3cdf566e8ba3ab21ee /usr/local/bin/node /usr/local/bin/node
COPY --from=node:22-bookworm-slim@sha256:e9f565d32859f0cbfb52125eafe8be39390f24dac302dc3cdf566e8ba3ab21ee /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && apt-get update && apt-get install -y --no-install-recommends unzip \
    && rm -rf /var/lib/apt/lists/* \
    && npm install --global pnpm@${PNPM_VERSION}
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction
COPY package.json pnpm-lock.yaml pnpm-workspace.yaml .npmrc ./
RUN pnpm install --frozen-lockfile
COPY . .
# Wayfinder calls Artisan during the front-end build, hence the throwaway .env. The Reverb host,
# port and key reach the browser at runtime through Inertia shared props, never through VITE_*
# build variables, so the image carries no domain (compose-profile §5, note for A05).
RUN cp .env.example .env \
    && composer dump-autoload --optimize --classmap-authoritative --no-dev --no-interaction \
    && php artisan filament:assets --no-interaction \
    && php artisan key:generate --no-interaction \
    && pnpm run build \
    && test -f bootstrap/ssr/ssr.js \
    && find bootstrap/ssr -name '*.map' -delete \
    && rm -f .env \
    && rm -rf node_modules tests agent

# ---------------------------------------------------------------------------
# Runtime: app, reverb, horizon, scheduler; the engine sandbox uses the same image.
# ---------------------------------------------------------------------------
FROM base AS runtime
# The root filesystem is read-only in Compose: Laravel's config/route/event caches and compiled
# views go to the /tmp tmpfs. Logs go to stderr.
ENV SERVER_NAME=:8080 \
    DB_CONNECTION=pgsql \
    PROXY_TRUST_MODE=secret \
    LOG_CHANNEL=stderr \
    APP_CONFIG_CACHE=/tmp/laravel/config.php \
    APP_ROUTES_CACHE=/tmp/laravel/routes-v7.php \
    APP_EVENTS_CACHE=/tmp/laravel/events.php \
    VIEW_COMPILED_PATH=/tmp/laravel/views
# The base image gives frankenphp cap_net_bind_service as a file capability. Under Compose's
# cap_drop: [ALL] + no-new-privileges the kernel then refuses to exec it ("Operation not
# permitted"). The image only listens on 8080/8443, so the file capability is removed.
RUN apt-get update && apt-get install -y --no-install-recommends curl \
    && rm -rf /var/lib/apt/lists/* \
    && setcap -r /usr/local/bin/frankenphp \
    && test -z "$(getcap /usr/local/bin/frankenphp)" \
    && groupadd --system --gid 10001 engine \
    && useradd --system --uid 10001 --gid engine --no-create-home --shell /usr/sbin/nologin engine
COPY --from=engine /out/webhooktrap /usr/local/bin/webhooktrap
# SSR of the public pages (PO decision 2026-11-16): the `ssr` service runs `node
# /app/bootstrap/ssr/ssr.js` from this image. Only the node binary is copied, from the same pinned
# node image as the build stage (no npm, no node_modules: the bundle is self-contained). About
# +125 MB uncompressed, +40 MB compressed; a second image would cost more and break "one image".
COPY --from=node:22-bookworm-slim@sha256:e9f565d32859f0cbfb52125eafe8be39390f24dac302dc3cdf566e8ba3ab21ee /usr/local/bin/node /usr/local/bin/node
COPY --from=build --chown=www-data:www-data /app /app
COPY docker/Caddyfile /etc/frankenphp/Caddyfile
# The `ingest` service runs this one instead (A09 S3-3, S3-4; compose-profile §2.3).
COPY docker/Caddyfile.ingest /etc/frankenphp/Caddyfile.ingest
# HAProxy capture gate (decision 61): shipped in this signed image, copied out by `haproxy-config`.
COPY docker/haproxy.cfg /etc/webhooktrap/haproxy.cfg
COPY docker/haproxy-received-200.http /etc/webhooktrap/received-200.http
COPY docker/caddy/ /etc/frankenphp/caddy/
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-webhooktrap.ini
COPY --chmod=755 docker/entrypoint.sh /usr/local/bin/app-entrypoint
COPY --chmod=755 docker/reverb-boot.sh /usr/local/bin/reverb-boot
# deploy.sh extracts this file from the signed image and checks it against the on-box policy
# before using it (ci-cd.md §4 step 3).
COPY compose.production.yaml /app/deploy/compose.production.yaml
# /run/engine: Docker seeds a new `engine_sock` volume with this owner and mode, so `engine`
# (10001:10001) can create its socket (0660) and `app` reaches it through group_add 10001.
# An existing volume keeps its old owner: chown it or re-create it (compose-profile.md §2 notes).
RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache /data /config \
    && mkdir -p /status && chown 999:999 /status \
    && mkdir -p /run/engine && chown 10001:10001 /run/engine && chmod 0770 /run/engine \
    && webhooktrap version \
    && node --version
USER www-data
EXPOSE 8080 8443
HEALTHCHECK NONE
ENTRYPOINT ["app-entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile", "--adapter", "caddyfile"]

# ---------------------------------------------------------------------------
# Runner: the engine alone, no PHP, no credentials baked in (ADR-010 §12, S-11).
# ---------------------------------------------------------------------------
FROM gcr.io/distroless/static-debian12:nonroot@sha256:afa5c872c891853ca7fcf1f12c3edb23f7eeef36189728842dd51042ff57f7ab AS runner
COPY --from=engine /out/webhooktrap /usr/local/bin/webhooktrap
USER nonroot:nonroot
ENTRYPOINT ["/usr/local/bin/webhooktrap"]
CMD ["version"]
