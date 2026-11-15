#!/usr/bin/env bash
# Proxy-validation run harness (decision 31/42). See HARNESS.md.
#
#   wt.sh <owner__repo> prepare     build images, create the setup networks + egress proxy
#   wt.sh <owner__repo> setup       clone + install inside a fresh container (egress via allowlist proxy only)
#   wt.sh <owner__repo> cutnet      remove the proxy and both networks (no network exists for the repo after this)
#   wt.sh <owner__repo> start       start the app with --network none, start the prober sidecar in its netns
#   wt.sh <owner__repo> run <label> [probe]   one prototype run; saves console + run file (+ probe readings)
#   wt.sh <owner__repo> logs        tail the app log (from inside the app container)
#   wt.sh <owner__repo> exec <cmd>  run a command in the app container (debugging during setup timebox)
#   wt.sh <owner__repo> teardown    destroy containers, volume, networks, images; delete the generated secret
#
# Nothing from the repository ever touches the host filesystem: it lives in a named
# volume that is destroyed at teardown. Only harness-written logs and the prototype's
# run files are copied out (by exact file name, via `docker exec cat`).
set -euo pipefail

HARNESS_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
VALIDATION_DIR="$(dirname "$HARNESS_DIR")"
WORK_ROOT="${WT_WORK_ROOT:-/tmp/claude-1000/-home-jiordiviera-workspace-personal-hookscope/c2233626-cc24-4151-a6c7-628ca5382e55/scratchpad/proxy-runs}"
PROTO_BIN="${WT_PROTO_BIN:-$WORK_ROOT/bin/proto}"

slug="${1:?usage: wt.sh <owner__repo> <command>}"; shift
cmd="${1:?command}"; shift || true
[[ "$slug" =~ ^[A-Za-z0-9._-]+__[A-Za-z0-9._-]+$ ]] || { echo "bad slug"; exit 3; }

RECIPE="$HARNESS_DIR/recipes/$slug"
[[ -f "$RECIPE/recipe.env" ]] || { echo "no recipe at $RECIPE"; exit 3; }
# shellcheck disable=SC1091
source "$RECIPE/recipe.env"   # REPO IMAGE_KIND PORT TARGET_PATH PROVIDER APP_SECRET_ENV ...

lc="$(echo "$slug" | tr 'A-Z_.' 'a-z--')"
NAME="wtp-$lc"                      # prefix for every docker object of this repo
VOL="$NAME-work"
NET_INT="$NAME-int"                 # internal: no route out
NET_EXT="$NAME-ext"                 # bridge: only the proxy is attached
PROXY="$NAME-proxy"
IMG="wtp-tool-$IMAGE_KIND:$lc"
PROXY_IMG="wtp-egress-proxy:$lc"
OUT="${WT_OUT:-$VALIDATION_DIR/runs/$slug}"
SCR="$WORK_ROOT/$slug"
mkdir -p "$OUT" "$SCR/logs"; chmod 700 "$SCR"
LABEL=(--label wt.proxyrun=1 --label "wt.repo=$slug")

# Hardening applied to every container that runs repository code.
HARDEN=(--cap-drop ALL --security-opt no-new-privileges --user 10001:10001
        --read-only --tmpfs /tmp:rw,exec,size=1g,uid=10001,gid=10001
        --tmpfs /wt:rw,exec,size=64m,uid=10001,gid=10001
        -e HOME=/tmp/home)

ts() { date -u +%Y-%m-%dT%H:%M:%SZ; }
log() { echo "[$(ts)] $*" | tee -a "$OUT/setup-log.md"; }

secret_file="$SCR/secret.env"
gen_secret() {
  [[ -f "$secret_file" ]] && return
  umask 077
  local raw; raw="$(head -c 32 /dev/urandom | base64 | tr -d '/+=' | head -c 32)"
  case "$PROVIDER" in
    stripe) echo "WT_SECRET=whsec_test_$raw" > "$secret_file" ;;
    *)      echo "WT_SECRET=wt_test_$raw" > "$secret_file" ;;
  esac
}
secret() { gen_secret; sed -n 's/^WT_SECRET=//p' "$secret_file"; }

# Build the recipe's app env file (fake test values only). __WT_SECRET__ is replaced.
app_env_file() {
  local f="$SCR/app.env"; umask 077; : > "$f"
  if [[ -f "$RECIPE/app.env" ]]; then sed "s|__WT_SECRET__|$(secret)|g" "$RECIPE/app.env" > "$f"; fi
  echo "$f"
}

prepare() {
  log "prepare: build images ($IMAGE_KIND toolchain, egress proxy); create networks"
  local ctx="$SCR/imgctx"; mkdir -p "$ctx"
  cp "$HARNESS_DIR/images/$IMAGE_KIND/Dockerfile" "$ctx/Dockerfile"; cp "$PROTO_BIN" "$ctx/proto"
  docker build -q ${BUILD_ARGS:-} -t "$IMG" "$ctx" >> "$SCR/logs/build.log" 2>&1
  docker build -q -t "$PROXY_IMG" "$HARNESS_DIR/proxy" >> "$SCR/logs/build.log" 2>&1
  docker network create --internal "${LABEL[@]}" "$NET_INT" >/dev/null
  docker network create "${LABEL[@]}" "$NET_EXT" >/dev/null
  docker volume create "${LABEL[@]}" "$VOL" >/dev/null
  docker run -d --name "$PROXY" "${LABEL[@]}" --network "$NET_EXT" --cap-drop ALL \
    --security-opt no-new-privileges --read-only --tmpfs /tmp --tmpfs /run/tinyproxy:uid=10002 \
    --memory 256m --pids-limit 128 "$PROXY_IMG" >/dev/null
  docker network connect "$NET_INT" "$PROXY"
  log "prepare: done (proxy on $NET_INT, allowlist in harness/proxy/allowlist)"
}

setup() {
  local t0; t0=$(date +%s)
  log "setup: start (clone + install in $IMG, egress only through allowlist proxy)"
  local px="http://$PROXY:8888"
  set +e
  docker run --rm --name "$NAME-setup" "${LABEL[@]}" "${HARDEN[@]}" \
    --network "$NET_INT" --cpus 4 --memory 4g --pids-limit 1024 \
    -e "HTTP_PROXY=$px" -e "HTTPS_PROXY=$px" -e "http_proxy=$px" -e "https_proxy=$px" \
    -e "NO_PROXY=localhost,127.0.0.1" -e "REPO=$REPO" -e "GIT_TERMINAL_PROMPT=0" \
    -v "$VOL:/work" -i "$IMG" bash -s < "$RECIPE/setup.sh" > "$SCR/logs/setup-$(date +%s).log" 2>&1
  local rc=$?
  set -e
  local t1; t1=$(date +%s)
  log "setup: exit $rc after $(( (t1-t0) )) s (full output kept in scratchpad, not in the repo)"
  return $rc
}

cutnet() {
  # Keep the list of hosts the setup reached / was refused (no payloads, only host names).
  docker logs "$PROXY" > "$SCR/logs/proxy.log" 2>&1 || true
  { echo "  egress hosts reached:  $(grep -oE 'CONNECT [^: ]+' "$SCR/logs/proxy.log" | awk '{print $2}' | sort -u | tr '\n' ' ')"
    echo "  egress hosts refused:  $(grep -iE 'filtered|denied' "$SCR/logs/proxy.log" | grep -oE '"[^"]+"' | tr -d '"' | sort -u | tr '\n' ' ')"; } \
    | tee -a "$OUT/setup-log.md"
  docker rm -f "$PROXY" >/dev/null 2>&1 || true
  docker network rm "$NET_INT" "$NET_EXT" >/dev/null 2>&1 || true
  log "cutnet: proxy and both networks removed; the repo has no network from now on"
}

start() {
  local envf; envf="$(app_env_file)"
  docker run -d --name "$NAME-app" "${LABEL[@]}" "${HARDEN[@]}" --network none \
    --cpus 2 --memory 2g --pids-limit 512 --env-file "$envf" -e "PORT=$PORT" \
    -e "WT_START_B64=$(base64 -w0 < "$RECIPE/start.sh")" \
    -v "$VOL:/work" "$IMG" bash -c 'echo "$WT_START_B64" | base64 -d > /wt/start.sh; exec bash /wt/start.sh > /tmp/app.log 2>&1' \
    >/dev/null
  # Prober: shares the app's network namespace (loopback only), same volume, same toolchain.
  docker run -d --name "$NAME-prober" "${LABEL[@]}" "${HARDEN[@]}" \
    --network "container:$NAME-app" --cpus 1 --memory 1g --pids-limit 256 \
    --env-file "$envf" -v "$VOL:/work" "$IMG" sleep infinity >/dev/null
  for f in probe.sh reset.sh; do
    [[ -f "$RECIPE/$f" ]] && docker exec -i "$NAME-prober" sh -c "cat > /wt/$f" < "$RECIPE/$f"
  done
  docker exec "$NAME-prober" sh -c 'mkdir -p /wt/run /wt/fx'
  for fx in "$RECIPE"/fixtures/*.json; do
    docker exec -i "$NAME-prober" sh -c "cat > /wt/fx/$(basename "$fx")" < "$fx"
  done
  log "start: app started with --network none; prober attached to its netns"
  # Verify isolation: no interface besides lo, no route.
  docker exec "$NAME-prober" sh -c 'cat /proc/net/dev | tail -n +3 | cut -d: -f1 | tr -d " " | tr "\n" " "; echo; cat /proc/net/route | wc -l' \
    | sed 's/^/  netns check: /' | tee -a "$OUT/setup-log.md"
  local i
  for i in $(seq 1 120); do
    if docker exec "$NAME-prober" bash -c "exec 3<>/dev/tcp/127.0.0.1/$PORT" 2>/dev/null; then
      log "start: app listening on 127.0.0.1:$PORT after ${i}s"; return 0; fi
    sleep 1
  done
  log "start: app did not listen within 120 s"; return 1
}

run_proto() {
  local label="${1:?label}" mode="${2:-L1}"
  [[ "$label" =~ ^[a-z0-9-]+$ ]] || { echo "bad label"; exit 3; }
  local s; s="$(secret)"
  local senv
  case "$PROVIDER" in stripe) senv=STRIPE_WEBHOOK_SECRET;; github) senv=GITHUB_WEBHOOK_SECRET;;
    shopify) senv=SHOPIFY_WEBHOOK_SECRET;; *) senv=WEBHOOK_SECRET;; esac
  local dir="/wt/run/$label"
  local cfg=""
  docker exec "$NAME-prober" sh -c "mkdir -p $dir && : > /wt/probe-readings.log"
  # Fixtures are imported fresh per run, in file-name order (= capture order).
  for fx in "$RECIPE"/fixtures/*.json; do
    docker exec -w "$dir" "$NAME-prober" /opt/wt/proto import --provider "$PROVIDER" "/wt/fx/$(basename "$fx")" >/dev/null
    sleep 1   # distinct received_at, keeps capture order = file order
  done
  if [[ "$mode" == probe ]]; then
    printf 'probe: sh /wt/probe.sh\n%s' "$( [[ -f $RECIPE/reset.sh ]] && echo 'reset: sh /wt/reset.sh' )" \
      | docker exec -i "$NAME-prober" sh -c "cat > $dir/proving.yml"
    cfg="--config proving.yml"
  fi
  local url="http://127.0.0.1:$PORT$TARGET_PATH"
  set +e
  docker exec -w "$dir" -e "$senv=$s" -e NO_COLOR=1 "$NAME-prober" \
    /opt/wt/proto run --target "$url" --provider "$PROVIDER" --yes --ascii --color never $cfg \
    > "$OUT/$label.console.txt" 2>&1
  local rc=$?
  set -e
  echo "exit_code: $rc" >> "$OUT/$label.console.txt"
  # Copy the run file out by exact, validated name.
  local f
  for f in $(docker exec "$NAME-prober" sh -c "ls $dir/.proving/runs/ 2>/dev/null"); do
    [[ "$f" =~ ^run_[0-9]{8}T[0-9]{6}Z-[0-9a-f]{4}\.json$ ]] || continue
    docker exec "$NAME-prober" cat "$dir/.proving/runs/$f" > "$OUT/$label.run.json"
  done
  if [[ "$mode" == probe ]]; then
    docker exec "$NAME-prober" cat /wt/probe-readings.log > "$OUT/$label.probe-readings.log" || true
  fi
  # App log tail for this run (handler errors), secret-scrubbed.
  docker exec "$NAME-app" sh -c 'tail -c 20000 /tmp/app.log' 2>/dev/null | sed "s|$s|[REDACTED]|g" > "$SCR/logs/$label.app.log" || true
  # Belt and braces: no file copied out may contain the secret.
  if grep -rlF -- "$s" "$OUT" >/dev/null 2>&1; then echo "SECRET FOUND IN OUTPUT - scrubbing"; grep -rlF -- "$s" "$OUT" | xargs sed -i "s|$s|[REDACTED]|g"; fi
  echo "run $label ($mode): proto exit $rc"
}

teardown() {
  docker rm -f "$NAME-app" "$NAME-prober" "$NAME-setup" "$PROXY" >/dev/null 2>&1 || true
  docker network rm "$NET_INT" "$NET_EXT" >/dev/null 2>&1 || true
  docker volume rm "$VOL" >/dev/null 2>&1 || true
  docker image rm "$IMG" "$PROXY_IMG" >/dev/null 2>&1 || true
  shred -u "$secret_file" "$SCR/app.env" 2>/dev/null || true
  local left; left="$(docker ps -aq --filter "label=wt.repo=$slug"; docker volume ls -q --filter "label=wt.repo=$slug"; docker network ls -q --filter "label=wt.repo=$slug")"
  log "teardown: containers, volume, networks, images removed; secret deleted. leftovers: ${left:-none}"
}

case "$cmd" in
  prepare) prepare ;;
  setup) setup ;;
  cutnet) cutnet ;;
  start) start ;;
  run) run_proto "$@" ;;
  logs) docker exec "$NAME-app" sh -c 'tail -n 80 /tmp/app.log' | sed "s|$(secret)|[REDACTED]|g" ;;
  exec) docker exec -i "$NAME-prober" bash -c "$*" ;;
  setupexec) docker run --rm "${LABEL[@]}" "${HARDEN[@]}" --network none --memory 1g --pids-limit 256 -v "$VOL:/work" -i "$IMG" bash -c "$*" ;;
  teardown) teardown ;;
  *) echo "unknown command"; exit 3 ;;
esac
