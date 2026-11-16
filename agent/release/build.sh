#!/bin/sh
# Builds one release target of the webhooktrap engine and archives it
# (ADR-010 "Distribution and supply chain" item 2; project/operations/agent-release.md).
#
#   agent/release/build.sh VERSION COMMIT GOOS GOARCH OUT_DIR
#
# VERSION is bare semver (1.2.3, 1.2.3-rc.1), never "v1.2.3": the server compares
# it with config('agent.min_agent_version') and a leading "v" sorts below every release.
# Writes OUT_DIR/webhooktrap_<VERSION>_<GOOS>_<GOARCH>.tar.gz (.zip for windows).
# Reproducible: pinned toolchain (go.mod), CGO off, -trimpath, no VCS stamp, archive
# entries sorted with fixed owner and mtime (SOURCE_DATE_EPOCH, default: the commit time).
# Run from anywhere; uses `go` from PATH (CI: setup-go with agent/go.mod; locally:
# `mise exec go@1.27.4 -- agent/release/build.sh ...`).
set -eu

if [ "$#" -ne 5 ]; then
    echo "usage: $0 VERSION COMMIT GOOS GOARCH OUT_DIR" >&2
    exit 2
fi
version=$1 commit=$2 goos=$3 goarch=$4 out=$5

if ! printf '%s\n' "$version" | grep -Eq '^[0-9]+\.[0-9]+\.[0-9]+(-[0-9A-Za-z.-]+)?$'; then
    echo "build.sh: version must be bare semver such as 1.2.3 (got '$version')" >&2
    exit 2
fi
case "$goos/$goarch" in
    linux/amd64 | linux/arm64 | darwin/amd64 | darwin/arm64 | windows/amd64 | windows/arm64) ;;
    *) echo "build.sh: unsupported target $goos/$goarch" >&2; exit 2 ;;
esac

root=$(cd "$(dirname "$0")/../.." && pwd)
mkdir -p "$out"
out=$(cd "$out" && pwd)
epoch=${SOURCE_DATE_EPOCH:-$(git -C "$root" log -1 --format=%ct 2>/dev/null || echo 0)}

exe=webhooktrap
[ "$goos" = windows ] && exe=webhooktrap.exe
name="webhooktrap_${version}_${goos}_${goarch}"
stage=$(mktemp -d)
trap 'rm -rf "$stage"' EXIT
mkdir "$stage/$name"

pkg=github.com/jiordiviera/webhooktrap/agent/internal/version
(
    cd "$root/agent"
    CGO_ENABLED=0 GOOS=$goos GOARCH=$goarch GOFLAGS=-mod=readonly \
        go build -trimpath -buildvcs=false \
        -ldflags "-s -w -buildid= -X $pkg.Version=$version -X $pkg.Commit=$commit" \
        -o "$stage/$name/$exe" ./cmd/webhooktrap
)
cp "$root/LICENSE" "$stage/$name/LICENSE"
chmod 0755 "$stage/$name/$exe"
chmod 0644 "$stage/$name/LICENSE"
chmod 0755 "$stage/$name"
find "$stage/$name" -exec touch -h -d "@$epoch" {} +

cd "$stage"
if [ "$goos" = windows ]; then
    rm -f "$out/$name.zip"
    # -X: no extra attributes; -D: no directory entries; files listed in sorted order.
    zip -q -X -D "$out/$name.zip" "$name/LICENSE" "$name/$exe"
    echo "$out/$name.zip"
else
    tar --sort=name --owner=0 --group=0 --numeric-owner --mtime="@$epoch" \
        --format=gnu -cf - "$name" | gzip -n -9 > "$out/$name.tar.gz"
    echo "$out/$name.tar.gz"
fi
