#!/bin/sh
# Turns checksummed release archives into the seven npm tarballs, and smoke-tests them
# on linux-x64 (project/operations/agent-release.md). Publishes nothing.
#
#   agent/release/npm-pack.sh VERSION ARCHIVE_DIR OUT_DIR
#
# ARCHIVE_DIR holds the six archives from build.sh and SHA256SUMS; every archive is
# checked against SHA256SUMS before a binary is taken out of it. OUT_DIR receives
#   node_modules/@webhooktrap/<pkg>/   the assembled packages (assemble.mjs)
#   tgz/<pkg>-<VERSION>.tgz            `npm pack` output, the exact bytes `npm publish` sends
#   publish-order.txt                  platform packages first, the launcher last
set -eu

if [ "$#" -ne 3 ]; then
    echo "usage: $0 VERSION ARCHIVE_DIR OUT_DIR" >&2
    exit 2
fi
version=$1
here=$(cd "$(dirname "$0")" && pwd)
archives=$(cd "$2" && pwd)
mkdir -p "$3"
out=$(cd "$3" && pwd)

echo "== checksums"
(cd "$archives" && sha256sum --strict -c SHA256SUMS)

echo "== binaries out of the checked archives"
bins="$out/bin"
mkdir -p "$bins"
for t in linux_amd64 linux_arm64 darwin_amd64 darwin_arm64 windows_amd64 windows_arm64; do
    d="$bins/$(echo "$t" | tr _ -)"
    mkdir -p "$d"
    base="webhooktrap_${version}_${t}"
    case $t in
        windows_*) unzip -q -o -j "$archives/$base.zip" "$base/webhooktrap.exe" -d "$d" ;;
        *) tar -xzf "$archives/$base.tar.gz" -C "$d" --strip-components=1 "$base/webhooktrap" ;;
    esac
done

echo "== assemble"
node "$here/../npm/assemble.mjs" --version "$version" --bin-dir "$bins" --out "$out"

echo "== npm pack --dry-run"
mkdir -p "$out/tgz"
while read -r p; do
    (cd "$out/node_modules/@webhooktrap/$p" && npm pack --dry-run >/dev/null 2>&1 && npm pack --pack-destination "$out/tgz" >/dev/null 2>&1)
    tgz="$out/tgz/webhooktrap-$p-$version.tgz"
    [ -f "$tgz" ] || { echo "npm pack produced no $tgz" >&2; exit 1; }
    # No install scripts may ever ship (ADR-010: no postinstall).
    if tar -xOzf "$tgz" package/package.json | grep -Eq '"(pre|post)?install"|"scripts"'; then
        echo "$p: package.json has scripts" >&2
        exit 1
    fi
    echo "$p -> $(basename "$tgz")"
done < "$out/publish-order.txt"

if [ "$(uname -s)-$(uname -m)" = Linux-x86_64 ]; then
    echo "== smoke: launcher next to its platform package"
    got=$(node "$out/node_modules/@webhooktrap/agent/bin/webhooktrap.js" version --json)
    echo "$got"
    echo "$got" | grep -q "\"version\":\"$version\"" || { echo "launcher reported the wrong version" >&2; exit 1; }

    echo "== smoke: exit status passes through"
    set +e
    node "$out/node_modules/@webhooktrap/agent/bin/webhooktrap.js" no-such-command 2>/dev/null
    rc=$?
    set -e
    [ "$rc" -eq 2 ] || { echo "expected exit 2, got $rc" >&2; exit 1; }

    echo "== smoke: platform package missing (--omit=optional)"
    # Outside OUT_DIR: Node resolves upwards and would find OUT_DIR/node_modules.
    scratch=$(mktemp -d)
    trap 'rm -r -f "$scratch"' EXIT
    lone="$scratch/lone/node_modules/@webhooktrap"
    mkdir -p "$lone"
    cp -R "$out/node_modules/@webhooktrap/agent" "$lone/"
    set +e
    msg=$(node "$lone/agent/bin/webhooktrap.js" version 2>&1)
    rc=$?
    set -e
    if [ "$rc" -ne 1 ] || ! echo "$msg" | grep -q 'is not installed'; then
        echo "missing-package message not shown (exit $rc): $msg" >&2
        exit 1
    fi

    echo "== smoke: npm install from the tarballs"
    proj="$scratch/install"
    mkdir -p "$proj"
    echo '{"name":"smoke","private":true}' > "$proj/package.json"
    # The other platform packages are optional: npm skips them (404 or wrong os/cpu).
    (cd "$proj" && npm install --no-audit --no-fund --ignore-scripts \
        "$out/tgz/webhooktrap-agent-linux-x64-$version.tgz" "$out/tgz/webhooktrap-agent-$version.tgz" >/dev/null 2>&1)
    (cd "$proj" && npx --no-install webhooktrap version --json) | grep -q "\"version\":\"$version\""
    echo "ok"
fi
