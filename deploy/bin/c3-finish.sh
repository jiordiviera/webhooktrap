#!/bin/sh
# C3 finish on the hosted box: run once as root after the base layout exists
# (/etc/webhooktrap/deploy.conf, $APP_DIR/bin/{deploy.sh,deploy-gate,compose-policy.py},
# $APP_DIR/secrets/*). Idempotent: every step checks what is there first and only changes what
# differs. It never prints a secret value.
#
# Usage (from a checkout of this repository, or with the two files copied side by side):
#   sudo sh deploy/bin/c3-finish.sh [--install-bin] [--template PATH]
#                                   [--ghcr-user NAME --ghcr-token-file FILE]
#
#   --install-bin      replace bin/deploy.sh, bin/deploy-gate and bin/compose-policy.py with this
#                      checkout's copies when they differ (without it, a difference is only reported)
#   --template         the .env.production template (default: ../hosted/env.production.template
#                      next to this script)
#   --ghcr-token-file  only if the GHCR packages are private: a file holding a GitHub token with
#                      read:packages only; used for `docker login ghcr.io` as root, then left alone
#
# Steps: 0 bin/ against this checkout, 1 cosign + trusted root, 2 webhooktrap.slice,
#        3 .env.production, 4 GHCR access, 5 verification summary.
set -eu
PATH=/usr/sbin:/usr/bin:/sbin:/bin
umask 022

# --- Pins (ci-cd.md §4.3). Change only together with CI (container.yml uses the same pins). ----
# cosign: the release CI signs with (sigstore/cosign-installer default at the pinned action).
COSIGN_VERSION=v3.1.4
COSIGN_SHA256_AMD64=424e2c97d0b45fd7a6b608a87f090f35633f4c23ece5f092d48bba11aaf4bbfa
COSIGN_SHA256_ARM64=ade83b7aac62e131cacfe96d958948b2150cc9efec46a570a2649b8e27dedad2
#   from https://github.com/sigstore/cosign/releases/download/v3.1.4/cosign_checksums.txt
# Sigstore public-good trusted root, at the root-signing commit that last changed it (2025-09-22).
TRUSTED_ROOT_URL=https://raw.githubusercontent.com/sigstore/root-signing/c9bda74ad2221f938f7d2e0295ca3aad2da710a8/targets/trusted_root.json
TRUSTED_ROOT_SHA256=6494e21ea73fa7ee769f85f57d5a3e6a08725eae1e38c755fc3517c9e6bc0b66
OWNER_REPO=jiordiviera/webhooktrap
SLICE=/etc/systemd/system/webhooktrap.slice
CONF=/etc/webhooktrap/deploy.conf

fail() { echo "c3-finish: $1" >&2; exit "${2:-1}"; }
say() { echo "c3-finish: $1"; }
sha() { sha256sum "$1" | cut -d' ' -f1; }

[ "$(id -u)" = 0 ] || fail "run as root"
here=$(cd "$(dirname "$0")" && pwd)
template="$here/../hosted/env.production.template"
ghcr_user="" ghcr_token_file="" install_bin=0
while [ $# -gt 0 ]; do
    case "$1" in
        --install-bin) install_bin=1; shift ;;
        --template) template=$2; shift 2 ;;
        --ghcr-user) ghcr_user=$2; shift 2 ;;
        --ghcr-token-file) ghcr_token_file=$2; shift 2 ;;
        *) fail "unknown argument: $1" 64 ;;
    esac
done

[ -f "$CONF" ] && [ "$(stat -c '%u %a' "$CONF")" = "0 644" ] || fail "$CONF missing or not root 0644"
APP_DIR=$(sed -n 's/^APP_DIR=\(\/[A-Za-z0-9._\/-]*\)$/\1/p' "$CONF")
[ -n "$APP_DIR" ] && [ -d "$APP_DIR/bin" ] || fail "APP_DIR not set in $CONF or $APP_DIR/bin missing"
tmp=$(mktemp -d)
trap 'rm -rf "$tmp"' EXIT

# --- 0. bin/ against this checkout (deploy.sh rev. 14 re-verifies on rollback) ----------------
for pair in "deploy.sh:$here/deploy.sh:0755" "deploy-gate:$here/deploy-gate:0755" \
    "compose-policy.py:$here/../policy/compose-policy.py:0644"; do
    name=${pair%%:*} rest=${pair#*:}
    src=${rest%:*} mode=${rest##*:}
    dst="$APP_DIR/bin/$name"
    [ -f "$src" ] || { say "bin/$name: no copy beside this script, not compared"; continue; }
    if [ -f "$dst" ] && cmp -s "$src" "$dst"; then
        say "bin/$name matches this checkout"
    elif [ "$install_bin" = 1 ]; then
        install -o root -g root -m "$mode" "$src" "$dst"
        say "bin/$name installed from this checkout"
    else
        say "WARNING: bin/$name differs from this checkout; rerun with --install-bin to replace it"
    fi
done

# --- 1. cosign and the trusted root -----------------------------------------------------------
case "$(uname -m)" in
    x86_64) arch=amd64; want=$COSIGN_SHA256_AMD64 ;;
    aarch64) arch=arm64; want=$COSIGN_SHA256_ARM64 ;;
    *) fail "unsupported architecture $(uname -m)" ;;
esac
if [ -f "$APP_DIR/bin/cosign" ] && [ "$(sha "$APP_DIR/bin/cosign")" = "$want" ]; then
    say "cosign $COSIGN_VERSION already installed"
else
    curl -fsSL -o "$tmp/cosign" "https://github.com/sigstore/cosign/releases/download/$COSIGN_VERSION/cosign-linux-$arch"
    [ "$(sha "$tmp/cosign")" = "$want" ] || fail "cosign download does not match the pinned SHA-256; nothing installed"
    install -o root -g root -m 0755 "$tmp/cosign" "$APP_DIR/bin/cosign"
    say "cosign $COSIGN_VERSION installed (SHA-256 checked)"
fi
if [ -f "$APP_DIR/bin/trusted_root.json" ] && [ "$(sha "$APP_DIR/bin/trusted_root.json")" = "$TRUSTED_ROOT_SHA256" ]; then
    say "trusted_root.json already installed"
else
    curl -fsSL -o "$tmp/trusted_root.json" "$TRUSTED_ROOT_URL"
    [ "$(sha "$tmp/trusted_root.json")" = "$TRUSTED_ROOT_SHA256" ] || fail "trusted_root.json does not match the pinned SHA-256; nothing installed"
    install -o root -g root -m 0644 "$tmp/trusted_root.json" "$APP_DIR/bin/trusted_root.json"
    say "trusted_root.json installed (SHA-256 checked)"
fi

# --- 2. The slice (aggregate ceiling for every 2.0 container, compose-profile.md §3) ----------
cat > "$tmp/slice" <<'EOF'
# Webhook Trap 2.0: aggregate ceiling of every container of the `webhooktrap` Compose project
# (cgroup_parent: webhooktrap.slice). Installed by deploy/bin/c3-finish.sh.
[Unit]
Description=Webhook Trap 2.0 containers

[Slice]
MemoryAccounting=yes
MemoryHigh=5632M
MemoryMax=6G
MemorySwapMax=0
CPUAccounting=yes
CPUQuota=300%
TasksAccounting=yes
TasksMax=4096
IOAccounting=yes
IOWeight=50
EOF
if [ -f "$SLICE" ] && cmp -s "$tmp/slice" "$SLICE"; then
    say "webhooktrap.slice unchanged"
else
    install -o root -g root -m 0644 "$tmp/slice" "$SLICE"
    systemctl daemon-reload
    say "webhooktrap.slice installed, daemon reloaded"
fi

# --- 3. .env.production (settings only, P-20) -------------------------------------------------
[ -f "$template" ] || fail "template not found: $template (pass --template)"
secret_names='^(APP_KEY|APP_PREVIOUS_KEYS|DB_PASSWORD|REDIS_PASSWORD|REVERB_APP_SECRET|MAIL_PASSWORD|PULSESEND_API_KEY|GITHUB_CLIENT_SECRET|GOOGLE_CLIENT_SECRET|ERROR_TRACKER_DSN|IDENTITY_EMAIL_HMAC_KEY|IDENTITY_RECOVERY_CODE_KEY|AGENT_PAIRING_HMAC_KEY|POSTGRES_PASSWORD|VALKEY_PASSWORD|EDGE_PROXY_SECRET|BACKUP_CIPHER_PASS|BACKUP_S3_KEY|BACKUP_S3_SECRET)='
! grep -Eq "$secret_names" "$template" || fail "the template sets a secret name (P-20)"
env_file="$APP_DIR/.env.production"
if [ -f "$env_file" ]; then
    grep -Eq "$secret_names" "$env_file" && fail "$env_file holds a secret name: move it to secrets/ (P-20)"
    missing=$(grep -oE '^[A-Z0-9_]+=' "$template" | while read -r key; do grep -q "^$key" "$env_file" || printf '%s ' "${key%=}"; done)
    if [ -n "$missing" ]; then say ".env.production kept; names in the template but not in the file: $missing"
    else say ".env.production kept (every template name present)"; fi
else
    app_id=$(od -An -N4 -tu4 /dev/urandom | tr -d ' ')
    app_key=$(od -An -N16 -tx1 /dev/urandom | tr -d ' \n')
    sed -e "s/^REVERB_APP_ID=__GENERATE__$/REVERB_APP_ID=$app_id/" \
        -e "s/^REVERB_APP_KEY=__GENERATE__$/REVERB_APP_KEY=$app_key/" "$template" > "$tmp/env"
    ! grep -q '^[A-Z0-9_]*=__GENERATE__$' "$tmp/env" || fail "unfilled placeholder in the template"
    install -o root -g root -m 0600 "$tmp/env" "$env_file"
    say ".env.production written from the template (Reverb id and key generated)"
fi
[ ! -e "$APP_DIR/.env" ] || say "WARNING: $APP_DIR/.env exists; deploy.sh refuses to run until it is removed"

# --- 4. GHCR access (the images are pulled and verified as root) ------------------------------
anon=$(mktemp -d)
access=none
if DOCKER_CONFIG=$anon docker manifest inspect "ghcr.io/$OWNER_REPO:v2" >/dev/null 2>&1 \
    && DOCKER_CONFIG=$anon docker manifest inspect "ghcr.io/$OWNER_REPO-postgres:v2" >/dev/null 2>&1; then
    access=public
elif [ -n "$ghcr_token_file" ]; then
    [ -n "$ghcr_user" ] || fail "--ghcr-token-file needs --ghcr-user"
    [ -r "$ghcr_token_file" ] || fail "cannot read $ghcr_token_file"
    docker login ghcr.io -u "$ghcr_user" --password-stdin < "$ghcr_token_file" >/dev/null 2>&1 || fail "docker login ghcr.io failed"
    access=token
elif docker manifest inspect "ghcr.io/$OWNER_REPO:v2" >/dev/null 2>&1; then
    access=token
fi
rm -rf "$anon"
case "$access" in
    public) say "GHCR: packages are public, no registry login needed" ;;
    token) say "GHCR: private packages, root is logged in to ghcr.io" ;;
    none) say "GHCR: NO ACCESS. Make the packages public, or rerun with --ghcr-user and --ghcr-token-file" ;;
esac

# --- 5. Verification summary (names, owners and sizes only, never values) ---------------------
echo
echo "== summary ($APP_DIR)"
for f in deploy.sh deploy-gate compose-policy.py cosign trusted_root.json; do
    p="$APP_DIR/bin/$f"
    if [ -f "$p" ]; then printf '  bin/%-20s %s %s sha256:%s\n' "$f" "$(stat -c '%U:%G %a' "$p")" "$(stat -c %s "$p")B" "$(sha "$p" | cut -c1-16)"
    else printf '  bin/%-20s MISSING\n' "$f"; fi
done
"$APP_DIR/bin/cosign" version 2>/dev/null | sed -n 's/^GitVersion: */  cosign version: /p'
printf '  deploy.conf: APP_DIR=%s APP_PORT=%s\n' "$APP_DIR" "$(sed -n 's/^APP_PORT=//p' "$CONF")"
printf '  .env.production: %s, %s names, secret names: none\n' "$(stat -c '%U:%G %a' "$env_file")" "$(grep -cE '^[A-Z0-9_]+=' "$env_file")"
printf '  slice: %s\n' "$(systemctl show webhooktrap.slice -p MemoryMax -p CPUQuotaPerSecUSec -p TasksMax 2>/dev/null | tr '\n' ' ')"
echo "  secrets (owner, mode, set or empty):"
for s in app_key db_password postgres_password valkey_password reverb_app_secret identity_email_hmac_key \
    identity_recovery_code_key agent_pairing_hmac_key edge_proxy_secret backup_cipher_pass backup_s3_key \
    backup_s3_secret mail_password pulsesend_api_key github_client_secret google_client_secret error_tracker_dsn; do
    p="$APP_DIR/secrets/$s"
    if [ -f "$p" ]; then
        state="empty"; [ -s "$p" ] && state="set"
        # Owners the containers need (compose-profile.md §5): uid 999 reads these, uid 33 the rest.
        case "$s" in postgres_password|backup_cipher_pass|backup_s3_key|backup_s3_secret) want="0:999 440" ;; *) want="0:33 440" ;; esac
        have=$(stat -c '%u:%g %a' "$p")
        note="ok"; [ "$have" = "$want" ] || note="EXPECTED $want"
        printf '    %-28s %s %-5s %s\n' "$s" "$have" "$state" "$note"
    else
        printf '    %-28s MISSING\n' "$s"
    fi
done
if [ -f "$APP_DIR/secrets/db_password" ] && [ -f "$APP_DIR/secrets/postgres_password" ]; then
    if cmp -s "$APP_DIR/secrets/db_password" "$APP_DIR/secrets/postgres_password"; then
        echo "    db_password and postgres_password: same value"
    else
        echo "    WARNING: db_password and postgres_password differ"
    fi
fi
if [ -f "$APP_DIR/secrets/edge_proxy_secret" ]; then
    size=$(stat -c %s "$APP_DIR/secrets/edge_proxy_secret")
    if head -c 64 "$APP_DIR/secrets/edge_proxy_secret" | grep -Eq '^[0-9a-f]{64}$' && { [ "$size" = 64 ] || [ "$size" = 65 ]; }; then
        echo "    edge_proxy_secret: 64 lowercase hex (S3-23 shape ok)"
    else
        echo "    WARNING: edge_proxy_secret is not exactly 64 lowercase hex"
    fi
fi
echo "  deploy user (webhooktrap-deploy):"
visudo -c >/dev/null 2>&1 && echo "    sudoers: parses" || echo "    WARNING: visudo -c fails"
rules=$(sudo -l -U webhooktrap-deploy 2>/dev/null | sed -n 's/^ *(root) NOPASSWD: //p')
if [ "$rules" = "$APP_DIR/bin/deploy.sh" ]; then echo "    sudo: NOPASSWD for bin/deploy.sh only"
else echo "    WARNING: sudo rules are not exactly NOPASSWD $APP_DIR/bin/deploy.sh"; fi
case "$(passwd -S webhooktrap-deploy 2>/dev/null | cut -d' ' -f2)" in
    L|LK) echo "    password: locked" ;;
    *) echo "    WARNING: password not locked" ;;
esac
if id -nG webhooktrap-deploy 2>/dev/null | tr ' ' '\n' | grep -qx docker; then echo "    WARNING: member of docker"
else echo "    docker group: not a member"; fi
keys=$(getent passwd webhooktrap-deploy | cut -d: -f6)/.ssh/authorized_keys
lines=$(grep -c '^[^#]' "$keys" 2>/dev/null || true)
forced=$(grep -c "^command=\"$APP_DIR/bin/deploy-gate\",.*restrict" "$keys" 2>/dev/null || true)
if [ "${lines:-0}" -ge 1 ] && [ "$lines" = "$forced" ]; then
    printf '    keys: %s, every one forced to bin/deploy-gate with restrict\n' "$lines"
else
    echo "    WARNING: a key in $keys lacks the forced command or restrict (or there is none)"
fi
printf '  GHCR access: %s\n' "$access"
