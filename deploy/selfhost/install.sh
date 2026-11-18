#!/bin/sh
# Webhook Trap self-host installer (docs/guides/self-hosting.mdx).
#
# Run it as root from the directory that holds compose.yaml. It writes, and never overwrites:
#   ./secrets/*   every Compose secret, generated with openssl, with the owners the images expect
#   ./.env        settings only, from deploy/selfhost/env.template
#   ./runner/     the (empty) directory of the runner's optional allow-list
# With --start it also starts the stack (docker compose up -d --wait).
#
# Usage:
#   deploy/selfhost/install.sh --version TAG --app-host HOST --capture-host HOST --ws-host HOST
#                              [--tls auto|off] [--http-port PORT] [--start]
#
#   --tls auto (default)  FrankenPHP gets certificates itself; ports 80 and 443 must reach this host.
#   --tls off             your reverse proxy terminates TLS and forwards to 127.0.0.1:PORT
#                         (default 8080), sending X-WT-Edge with the content of secrets/edge_proxy_secret.
set -eu

die() { echo "install.sh: $1" >&2; exit "${2:-1}"; }
say() { echo "install.sh: $1"; }

version="" app_host="" capture_host="" ws_host="" tls=auto http_port="" https_port="" start=0
while [ $# -gt 0 ]; do
    case "$1" in
        --version) version=${2:-}; shift 2 ;;
        --app-host) app_host=${2:-}; shift 2 ;;
        --capture-host) capture_host=${2:-}; shift 2 ;;
        --ws-host) ws_host=${2:-}; shift 2 ;;
        --tls) tls=${2:-}; shift 2 ;;
        --http-port) http_port=${2:-}; shift 2 ;;
        --start) start=1; shift ;;
        -h|--help) sed -n '2,17p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
        *) die "unknown argument $1 (see --help)" 64 ;;
    esac
done

[ -f compose.yaml ] || die "run me from the directory that holds compose.yaml"
[ -f deploy/selfhost/env.template ] || die "deploy/selfhost/env.template is missing"
[ "$(id -u)" = 0 ] || die "run me as root: the secret files must be owned by root with the image's group"
for bin in openssl docker; do command -v "$bin" >/dev/null 2>&1 || die "$bin is not installed"; done
docker compose version >/dev/null 2>&1 || die "the Docker Compose plugin (docker compose) is not installed"

host_ok() { printf '%s' "$1" | grep -Eq '^[A-Za-z0-9]([A-Za-z0-9-]*[A-Za-z0-9])?(\.[A-Za-z0-9]([A-Za-z0-9-]*[A-Za-z0-9])?)+$'; }

if [ ! -f .env ]; then
    [ -n "$version" ] || die "--version is required (a release tag, never latest)" 64
    [ "$version" != latest ] || die "use a release tag, never latest" 64
    printf '%s' "$version" | grep -Eq '^[A-Za-z0-9][A-Za-z0-9._-]{0,127}$' || die "--version is not a valid image tag" 64
    for pair in "app-host:$app_host" "capture-host:$capture_host" "ws-host:$ws_host"; do
        name=${pair%%:*} value=${pair#*:}
        [ -n "$value" ] || die "--$name is required" 64
        host_ok "$value" || die "--$name must be a host name such as app.example.com" 64
    done
    [ "$app_host" != "$capture_host" ] && [ "$app_host" != "$ws_host" ] && [ "$capture_host" != "$ws_host" ] \
        || die "the app, capture and WebSocket hosts must be three different names" 64
    case "$tls" in
        auto)
            [ -z "$http_port" ] || die "--http-port is for --tls off" 64
            # http://app:8080: the runner reaches the app on that name, on its plain port.
            server_name="$app_host, $capture_host, $ws_host, http://app:8080"
            auto_https=on trust=none bind=0.0.0.0 http_port=80 https_port=443 ;;
        off)
            http_port=${http_port:-8080}
            printf '%s' "$http_port" | grep -Eq '^[0-9]{1,5}$' && [ "$http_port" -ge 1 ] && [ "$http_port" -le 65535 ] \
                || die "--http-port must be a port number" 64
            # Compose still publishes the TLS port: give it a free loopback port, your proxy has 443.
            https_port=$((http_port + 1))
            [ "$https_port" -le 65535 ] || https_port=$((http_port - 1))
            server_name=":8080" auto_https=off trust=secret bind=127.0.0.1 ;;
        *) die "--tls must be auto or off" 64 ;;
    esac
fi

# --- secrets ----------------------------------------------------------------------------------
# Owners: the postgres image's group (999) for the database and backup secrets, the runner's
# (65532) for its token, the app image's web group (33) for the rest; 0440, so the containers read them and nobody else does.
umask 077
mkdir -p secrets runner
chmod 0755 secrets runner

write_secret() { # name group value
    if [ -e "secrets/$1" ]; then
        say "secrets/$1 exists, kept"
    else
        if [ -n "$3" ]; then printf '%s\n' "$3" > "secrets/$1"; else : > "secrets/$1"; fi
        say "secrets/$1 written"
    fi
    chown "root:$2" "secrets/$1"
    chmod 0440 "secrets/$1"
}
hex32() { openssl rand -hex 32; }

if [ -e secrets/postgres_password ]; then
    db_password=$(cat secrets/postgres_password)
else
    db_password=$(hex32)
fi
write_secret postgres_password 999 "$db_password"
write_secret db_password 33 "$db_password"
write_secret backup_cipher_pass 999 "$(hex32)"
write_secret backup_s3_key 999 ""
write_secret backup_s3_secret 999 ""
write_secret app_key 33 "base64:$(openssl rand -base64 32)"
for name in valkey_password reverb_app_secret identity_email_hmac_key identity_recovery_code_key \
            agent_pairing_hmac_key edge_proxy_secret; do
    write_secret "$name" 33 "$(hex32)"
done
for name in mail_password pulsesend_api_key github_client_secret google_client_secret error_tracker_dsn; do
    write_secret "$name" 33 ""
done

# The runner image runs as distroless `nonroot` (65532), not as the web user.
write_secret runner_token 65532 ""

[ "$(cat secrets/postgres_password)" = "$(cat secrets/db_password)" ] \
    || die "secrets/postgres_password and secrets/db_password differ; they must hold the same password"

# --- .env -------------------------------------------------------------------------------------
if [ -f .env ]; then
    say ".env exists, kept (flags other than --start are ignored)"
else
    reverb_id=$(od -An -N4 -tu4 /dev/urandom | tr -d ' ')
    reverb_key=$(openssl rand -hex 16)
    sed -e "s|__VERSION__|$version|g" \
        -e "s|__APP_HOST__|$app_host|g" \
        -e "s|__CAPTURE_HOST__|$capture_host|g" \
        -e "s|__WS_HOST__|$ws_host|g" \
        -e "s|__SERVER_NAME__|\"$server_name\"|g" \
        -e "s|__CADDY_AUTO_HTTPS__|$auto_https|g" \
        -e "s|__PROXY_TRUST_MODE__|$trust|g" \
        -e "s|__BIND_ADDRESS__|$bind|g" \
        -e "s|__HTTP_PORT__|$http_port|g" \
        -e "s|__HTTPS_PORT__|$https_port|g" \
        -e "s|__REVERB_APP_ID__|$reverb_id|g" \
        -e "s|__REVERB_APP_KEY__|$reverb_key|g" \
        deploy/selfhost/env.template > .env
    chmod 0600 .env
    say ".env written ($tls TLS)"
fi

docker compose config --quiet || die "docker compose config failed: check .env"

if [ "$start" = 0 ]; then
    cat <<EOF

Next:
  docker compose up -d --wait
Then open the app URL and create the first account at /register.
EOF
    exit 0
fi

# --- start ------------------------------------------------------------------------------------
# The server runner (Compose profile `runner`) stays off: the app has no runner API in 2.0.
say "starting the stack"
docker compose up -d --wait
docker compose ps
say "done: open $(grep '^APP_URL=' .env | cut -d= -f2-) and create the first account at /register"
