#!/bin/sh
# App image entrypoint for app, reverb, horizon and scheduler (template entrypoint, extended:
# compose-profile.md §1, §2 notes, §2.1). Never prints a secret value.
set -eu

fail() {
    echo "webhooktrap: $1" >&2
    exit "${2:-1}"
}

# 1. DL-3c: a restore is half done (deletion ledger not re-applied). Refuse to start.
if [ -e /status/restore.pending ]; then
    fail "restore.pending present: run accounts:apply-ledger and restore-finish first" 75
fi

# 2. Secrets as files, so they stay out of `docker inspect` and `.env.production` (CLAUDE.md).
#    For each name: <NAME>_FILE if set; else the Compose secret /run/secrets/<file> if mounted;
#    else a plain variable (local development only; the hosted policy refuses plain values,
#    ci-cd.md P-20). DB_PASSWORD has its own file (db_password, readable by the app's uid 33)
#    holding the same value as postgres_password (readable by postgres, uid 999); REDIS_PASSWORD
#    shares valkey's file (valkey reads it as root).
for pair in APP_KEY:app_key DB_PASSWORD:db_password REDIS_PASSWORD:valkey_password \
    REVERB_APP_SECRET:reverb_app_secret MAIL_PASSWORD:mail_password PULSESEND_API_KEY:pulsesend_api_key \
    GITHUB_CLIENT_SECRET:github_client_secret GOOGLE_CLIENT_SECRET:google_client_secret \
    ERROR_TRACKER_DSN:error_tracker_dsn IDENTITY_EMAIL_HMAC_KEY:identity_email_hmac_key \
    IDENTITY_RECOVERY_CODE_KEY:identity_recovery_code_key \
    AGENT_PAIRING_HMAC_KEY:agent_pairing_hmac_key APP_PREVIOUS_KEYS:app_previous_keys; do
    name="${pair%%:*}"
    file_var="${name}_FILE"
    eval "file=\${$file_var:-}"
    if [ -n "$file" ]; then
        [ -r "$file" ] || fail "$file_var points to an unreadable file"
    elif [ -e "/run/secrets/${pair#*:}" ]; then
        file="/run/secrets/${pair#*:}"
        [ -r "$file" ] || fail "secret ${pair#*:} is mounted but unreadable by the runtime user"
    else
        continue
    fi
    eval "export $name=\"\$(cat \"\$file\")\""
done

# 3. Required variables, named when missing.
missing=""
for name in APP_KEY APP_URL DB_HOST DB_DATABASE DB_USERNAME REDIS_HOST; do
    eval "value=\${$name:-}"
    [ -n "$value" ] || missing="$missing $name"
done
[ -z "$missing" ] || fail "missing required variables:$missing" 78

# 4. SV-2: one trust mode for Caddy and PHP (A09 S3-5: the app default is `none`, the image
#    default `secret`), exported so both read the same value.
export PROXY_TRUST_MODE="${PROXY_TRUST_MODE:-secret}"
case "$PROXY_TRUST_MODE" in
    secret|ip|none) ;;
    *) fail "PROXY_TRUST_MODE must be secret, ip or none" 78 ;;
esac
case "${INGEST_MODE:-local}" in
    local|proxy) ;;
    *) fail "INGEST_MODE must be local or proxy" 78 ;;
esac

# 4a. Which process this is (A09 S3-22). `app` and `ingest` run Caddy and use the edge secret;
#     every other process of the image (reverb, horizon, scheduler, migrate, artisan) never
#     reads it and is not given it. Unset: `app` when the command is FrankenPHP, else `worker`.
if [ -z "${WT_ROLE:-}" ]; then
    if [ "${1:-}" = frankenphp ]; then WT_ROLE=app; else WT_ROLE=worker; fi
fi
case "$WT_ROLE" in
    app|ingest|worker) ;;
    *) fail "WT_ROLE must be app, ingest or worker" 78 ;;
esac
if [ "$WT_ROLE" = ingest ] && [ "$PROXY_TRUST_MODE" != secret ]; then
    fail "the ingest service runs in PROXY_TRUST_MODE=secret only" 78
fi

# 4c. Agent protocol (contracts §5). The app front pairs agents, so it needs the pairing HMAC
#     key (a file secret, AGENT_PAIRING_HMAC_KEY_FILE). Dev agent builds are accepted only in
#     APP_ENV local or testing; the variable is refused anywhere else (hosted, production
#     self-host), even though the app would ignore it.
# 4d. The identity keys (domain-model §2.1): only `app` uses them (account deletion, recovery
#     codes). `ingest` and the workers never read them; the app's serve check skips
#     WT_ROLE=ingest (9224c67), which Compose sets explicitly on that service.
missing=""
if [ "$WT_ROLE" = app ]; then
    for name in IDENTITY_EMAIL_HMAC_KEY IDENTITY_RECOVERY_CODE_KEY; do
        eval "value=\${$name:-}"
        [ -n "$value" ] || missing="$missing $name"
    done
fi
if [ "$WT_ROLE" = app ] && [ -z "${AGENT_PAIRING_HMAC_KEY:-}" ]; then
    missing="$missing AGENT_PAIRING_HMAC_KEY"
fi
[ -z "$missing" ] || fail "missing required secrets:$missing (Compose secret or <NAME>_FILE)" 78
case "${AGENT_ALLOW_DEV_BUILDS:-}" in
    ''|0|false|False|FALSE|'(false)') ;;
    *) case "${APP_ENV:-production}" in
           local|testing) ;;
           *) fail "AGENT_ALLOW_DEV_BUILDS is for APP_ENV local or testing only" 78 ;;
       esac ;;
esac

# 4b. Fail closed on the edge proxy secret where it is used (SV-2; A09 S3-3, S3-4, S3-22): the
#     front in mode `secret` checks the host proxy's proof with it, the front with
#     INGEST_MODE=proxy signs the hop to haproxy/ingest with it, and ingest checks that signature.
#     A09 S3-23: the whole file is the secret, exactly 64 lowercase hex characters with at most
#     one final newline (Caddy reads the whole file, HAProxy reads every line as a pattern).
secret_file_ok() {
    [ -f "$1" ] && [ -r "$1" ] || return 1
    size=$(($(wc -c < "$1")))
    case "$size" in
        64) ;;
        65) [ "$(tail -c 1 "$1" | od -An -c | tr -d ' ')" = '\n' ] || return 1 ;;
        *) return 1 ;;
    esac
    head -c 64 "$1" | grep -Eq '^[0-9a-f]{64}$'
}
if [ "$WT_ROLE" != worker ] \
    && { [ "$PROXY_TRUST_MODE" = secret ] || [ "${INGEST_MODE:-local}" = proxy ] || [ "$WT_ROLE" = ingest ]; }; then
    secret_file_ok "${EDGE_PROXY_SECRET_FILE:-/run/secrets/edge_proxy_secret}" \
        || fail "edge proxy secret missing, unreadable or not exactly 64 hex characters" 78
fi

# 5. H-5: the engine binary in this image runs and speaks protocol v1.
webhooktrap version --json | grep -q '"protocol":"v1"' || fail "engine binary missing or wrong protocol"

# 6. Template: cache config, routes, events and views (to the /tmp tmpfs, see Dockerfile ENV).
mkdir -p /tmp/laravel/views
if [ "${APP_ENV:-production}" != local ]; then
    php artisan optimize --no-interaction >/dev/null
fi

# FrankenPHP (CMD) or any artisan command (Compose `command:`).
exec docker-php-entrypoint "$@"
