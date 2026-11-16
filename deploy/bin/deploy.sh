#!/bin/sh
# Webhook Trap 2.0 deploy on the hosted box (ci-cd.md §4, steps 1-10; CI-1, CI-1a, CI-2, DL-3c).
# Installed root-owned (0755) as <APP_DIR>/bin/deploy.sh, run only through deploy-gate and its
# sudoers line. Host-specific values (APP_DIR, APP_PORT) come from /etc/webhooktrap/deploy.conf,
# root-owned 0644, written at install (the private hosted deployment plan); never from the caller. It runs as root, so it trusts nothing it is told: it verifies
# the images, checks the Compose file against the on-box policy, and changes nothing until the
# backup and the migration have passed.
#
#   deploy.sh deploy <40-hex sha> <sha256:app digest> <sha256:pg digest>
#   deploy.sh rollback
set -eu

# --- Constants (changed only by a PO-approved reinstall) -------------------------------------
CONF=/etc/webhooktrap/deploy.conf
[ -f "$CONF" ] && [ "$(stat -c '%u %a' "$CONF")" = "0 644" ] || { echo "deploy: $CONF missing or not root 0644" >&2; exit 78; }
APP_DIR=$(sed -n 's/^APP_DIR=\(\/[A-Za-z0-9._\/-]*\)$/\1/p' "$CONF")
APP_PORT=$(sed -n 's/^APP_PORT=\([0-9]\{2,5\}\)$/\1/p' "$CONF")
[ -n "$APP_DIR" ] && [ -n "$APP_PORT" ] || { echo "deploy: $CONF must set APP_DIR and APP_PORT" >&2; exit 78; }
OWNER_REPO=jiordiviera/webhooktrap
APP_REPO=ghcr.io/$OWNER_REPO
PG_REPO=ghcr.io/$OWNER_REPO-postgres
CERT_ID=https://github.com/$OWNER_REPO/.github/workflows/container.yml@refs/heads/v2
OIDC_ISSUER=https://token.actions.githubusercontent.com
COSIGN=$APP_DIR/bin/cosign                    # pinned release, checksum checked at install (C3)
TRUSTED_ROOT=$APP_DIR/bin/trusted_root.json   # Sigstore trusted root, pinned at install
POLICY=$APP_DIR/bin/compose-policy.py         # installed from deploy/policy at C3, never from the image
PROJECT=webhooktrap
# Reads the restore guard without running any 2.0 code (the pinned Valkey image has `test`).
PROBE_IMAGE=valkey/valkey:8-alpine@sha256:2602ace2be6569bc2bbb06f873776516ab9089821525be34f4305c67eb9db02a
LOCK=/run/lock/webhooktrap-deploy.lock
LOG=$APP_DIR/deploy.log

# --- 1. Clean environment, arguments, lock, preconditions ------------------------------------
if [ "${WT_DEPLOY_CLEAN:-}" != 1 ]; then
    exec env -i WT_DEPLOY_CLEAN=1 PATH=/usr/sbin:/usr/bin:/sbin:/bin HOME=/root "$0" "$@"
fi
umask 077

fail() { echo "deploy: $1" >&2; exit "${2:-1}"; }
log() { printf '%s %s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$1" >> "$LOG"; echo "deploy: $1"; }

action="${1:-}"
case "$action" in
    deploy)
        [ $# -eq 4 ] || fail "usage: deploy <sha> <app digest> <pg digest>" 64
        sha=$2 app_digest=$3 pg_digest=$4
        printf '%s' "$sha" | grep -qx '[0-9a-f]\{40\}' || fail "sha must be 40 lowercase hex" 64
        for d in "$app_digest" "$pg_digest"; do
            printf '%s' "$d" | grep -qx 'sha256:[0-9a-f]\{64\}' || fail "digests must be sha256:<64 hex>" 64
        done
        ;;
    rollback) [ $# -eq 1 ] || fail "usage: rollback" 64 ;;
    *) fail "usage: deploy <sha> <app digest> <pg digest> | rollback" 64 ;;
esac

exec 9>"$LOCK"
flock -n 9 || fail "another deploy is running" 75

cd "$APP_DIR"
[ ! -e .env ] || fail "$APP_DIR/.env exists; Compose would read it. Remove it first" 78
[ -f .env.production ] && [ -d secrets ] || fail ".env.production or secrets/ missing" 78
for f in "$COSIGN" "$TRUSTED_ROOT" "$POLICY"; do [ -f "$f" ] || fail "missing $f (C3 install)" 78; done

# DL-3c: a restore is half done (deletion ledger not re-applied).
if docker volume inspect "${PROJECT}_backup_status" >/dev/null 2>&1 \
    && docker run --rm --network none -v "${PROJECT}_backup_status:/s:ro" --entrypoint test "$PROBE_IMAGE" -e /s/restore.pending; then
    fail "restore.pending is present: finish the restore (accounts:apply-ledger, restore-finish) first" 75
fi

# .env.production first, then the caller's .env.deploy (the later file wins): bare names in the
# Compose file (postgres, valkey, backup `environment: [NAME]`) resolve from these two files only.
compose() { docker compose -p "$PROJECT" --project-directory "$APP_DIR" --env-file "$APP_DIR/.env.production" "$@"; }
installed() { compose --env-file .env.deploy -f compose.production.yaml "$@"; }

# Step 8: /up answers and every long-running service is healthy (the one-shot is excluded).
# Up to 180 s: longer than the scheduler's start_period (150 s, first heartbeat at a minute
# boundary), so a first deploy is not judged while a service is still "starting".
# `backup` is left out: its check (5 min interval) is about backup freshness, which step 4
# already gated; it is watched through /health and its own alert, not by the deploy.
healthy() {
    i=0
    while [ $i -lt 90 ]; do
        if curl -fsS -o /dev/null "http://127.0.0.1:$APP_PORT/up" \
            && ! installed ps --format '{{.Service}} {{.Health}}' | grep -v '^backup ' | grep -v ' healthy$' | grep -q .; then
            return 0
        fi
        i=$((i + 1)); sleep 2
    done
    return 1
}

work=$(mktemp -d "$APP_DIR/.deploy.XXXXXX")
trap 'rm -rf "$work"' EXIT

# Step 2: the box verifies each image itself (signature by container.yml on v2, for that commit).
verify_refs() { # sha app_ref pg_ref
    for ref in "$2" "$3"; do
        "$COSIGN" verify --trusted-root "$TRUSTED_ROOT" \
            --certificate-identity "$CERT_ID" --certificate-oidc-issuer "$OIDC_ISSUER" \
            --certificate-github-workflow-sha "$1" "$ref" >/dev/null \
            || fail "signature check failed for $ref" 1
    done
}
# Step 3: the on-box policy on the resolved configuration (P-11: the JSON is deleted at once).
policy_check() { # compose_file env_file app_ref pg_ref
    compose --env-file "$2" -f "$1" config --format json > "$work/resolved.json"
    if ! python3 -I "$POLICY" --config "$work/resolved.json" --app-ref "$3" --pg-ref "$4" \
        --app-dir "$APP_DIR" --app-port "$APP_PORT"; then
        rm -f "$work/resolved.json"
        fail "Compose policy refused the file" 1
    fi
    rm -f "$work/resolved.json"
}

if [ "$action" = rollback ]; then
    [ -f .env.deploy.previous ] && [ -f compose.production.yaml.previous ] || fail "nothing to roll back to" 1
    # The previous deployment is verified again, as a new one would be (ci-cd.md §4.3).
    prev_sha=$(sed -n 's/^IMAGE_TAG=\([0-9a-f]\{40\}\)$/\1/p' .env.deploy.previous)
    prev_app=$(sed -n 's/^APP_DIGEST=\(sha256:[0-9a-f]\{64\}\)$/\1/p' .env.deploy.previous)
    prev_pg=$(sed -n 's/^PG_DIGEST=\(sha256:[0-9a-f]\{64\}\)$/\1/p' .env.deploy.previous)
    [ -n "$prev_sha" ] && [ -n "$prev_app" ] && [ -n "$prev_pg" ] || fail ".env.deploy.previous is not a deploy.sh file" 1
    verify_refs "$prev_sha" "$APP_REPO@$prev_app" "$PG_REPO@$prev_pg"
    policy_check compose.production.yaml.previous .env.deploy.previous "$APP_REPO@$prev_app" "$PG_REPO@$prev_pg"
    log "rollback to $prev_sha: signatures and policy checked again"
    for f in .env.deploy compose.production.yaml; do
        mv "$f" "$f.swap" && mv "$f.previous" "$f" && mv "$f.swap" "$f.previous"
    done
    installed up -d --remove-orphans
    healthy || { installed ps --format '{{.Service}} {{.Status}}' >&2; fail "rollback is unhealthy" 1; }
    log "rollback: the previous images and Compose file are serving"
    exit 0
fi

app_ref="$APP_REPO@$app_digest"
pg_ref="$PG_REPO@$pg_digest"

# --- 2. Verify on the box, then pull ---------------------------------------------------------
verify_refs "$sha" "$app_ref" "$pg_ref"
log "verified $app_ref and $pg_ref for $sha"
docker pull -q "$app_ref" >/dev/null
docker pull -q "$pg_ref" >/dev/null

# --- 3. Extract the Compose file and check it ------------------------------------------------
cid=$(docker create --pull=never "$app_ref")
docker cp "$cid:/app/deploy/compose.production.yaml" "$work/compose.production.yaml" >/dev/null
docker rm "$cid" >/dev/null
[ -f "$work/compose.production.yaml" ] && [ ! -L "$work/compose.production.yaml" ] || fail "no Compose file in the image"
[ "$(wc -c < "$work/compose.production.yaml")" -le 65536 ] || fail "Compose file over 64 KiB"
cat > "$work/.env.deploy" <<EOF
APP_IMAGE=$APP_REPO
APP_DIGEST=$app_digest
IMAGE_TAG=$sha
PG_IMAGE=$PG_REPO
PG_DIGEST=$pg_digest
APP_PORT=$APP_PORT
EOF
policy_check "$work/compose.production.yaml" "$work/.env.deploy" "$app_ref" "$pg_ref"
candidate_hash=$(sha256sum "$work/compose.production.yaml" | cut -d' ' -f1)
log "policy passed, Compose file sha256 $candidate_hash"

cand() { compose --env-file "$work/.env.deploy" -f "$work/compose.production.yaml" "$@"; }

# --- 4. Pre-migrate backup (skipped on the first deploy: no database yet) ----------------------
if docker volume inspect "${PROJECT}_pgdata" >/dev/null 2>&1; then
    cand up -d postgres valkey
    cand run --rm --no-deps backup backup-now --type=incr || fail "pre-migrate backup failed; nothing changed" 1
    log "pre-migrate backup done"
else
    log "first deploy: no database yet, pre-migrate backup skipped"
    cand up -d postgres valkey
fi

# --- 5. Migrate ------------------------------------------------------------------------------
cand run --rm --no-deps app php artisan migrate --force --no-interaction \
    || fail "migration failed; the installed files still name the previous deployment" 1
log "migrations applied"

# --- 6. Install the checked files (atomic, Compose file first) -------------------------------
if [ -f .env.deploy ] && [ -f compose.production.yaml ]; then
    cp -p .env.deploy .env.deploy.previous.tmp && mv .env.deploy.previous.tmp .env.deploy.previous
    cp -p compose.production.yaml compose.production.yaml.previous.tmp \
        && mv compose.production.yaml.previous.tmp compose.production.yaml.previous
fi
[ "$(sha256sum "$work/compose.production.yaml" | cut -d' ' -f1)" = "$candidate_hash" ] || fail "candidate changed"
install -m 0644 "$work/compose.production.yaml" compose.production.yaml.tmp && mv compose.production.yaml.tmp compose.production.yaml
install -m 0600 "$work/.env.deploy" .env.deploy.tmp && mv .env.deploy.tmp .env.deploy

# --- 7. Up -----------------------------------------------------------------------------------
installed up -d --remove-orphans

# --- 8. Health, 9. automatic rollback --------------------------------------------------------
if ! healthy; then
    installed ps --format '{{.Service}} {{.Status}}' >&2
    if [ -f .env.deploy.previous ] && [ -f compose.production.yaml.previous ]; then
        cp -p .env.deploy.previous .env.deploy && cp -p compose.production.yaml.previous compose.production.yaml
        installed up -d --remove-orphans
        if healthy; then log "deploy of $sha failed health; rolled back to the previous images"
        else log "deploy of $sha failed health; the rollback is unhealthy too"; fi
    else
        log "first deploy of $sha failed health; nothing to roll back to"
    fi
    fail "health check failed" 1
fi

# --- 10. Keep the two previous image sets, log ------------------------------------------------
for repo in "$APP_REPO" "$PG_REPO"; do
    docker image ls --digests --format '{{.Repository}}@{{.Digest}}' "$repo" | grep -v '<none>' \
        | tail -n +4 | xargs -r docker image rm >/dev/null 2>&1 || true
done
log "deployed $sha app $app_digest pg $pg_digest compose $candidate_hash"
