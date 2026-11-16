#!/bin/sh
# Installs the webhooktrap agent from a GitHub release (ADR-010 "Distribution and supply chain"
# item 3; project/operations/agent-release.md §6).
#
#   curl --proto '=https' --tlsv1.2 -fsSL https://raw.githubusercontent.com/jiordiviera/webhooktrap/v2/agent/install.sh | sh
#
# Steps: detect OS and CPU → download the archive, SHA256SUMS and its Sigstore bundle (HTTPS
# only) → check the archive's SHA-256 against SHA256SUMS → verify SHA256SUMS' signature with
# cosign when cosign is installed (else print how to do it by hand) → install the binary to
# $PREFIX/bin (default ~/.local/bin). Nothing is installed if a check fails.
#
# Environment:
#   WEBHOOKTRAP_VERSION            1.2.3 (default: the newest 1.x release)
#   PREFIX                         install prefix; the binary goes to $PREFIX/bin (default ~/.local)
#   WEBHOOKTRAP_REQUIRE_SIGNATURE  1: refuse to install when cosign is not available
set -eu

repo=jiordiviera/webhooktrap
major=1
workflow=.github/workflows/agent-release.yml
issuer=https://token.actions.githubusercontent.com

say() { printf 'webhooktrap-install: %s\n' "$*" >&2; }
die() { say "$*"; exit 1; }

# --- tools ---------------------------------------------------------------------------------
if command -v curl >/dev/null 2>&1; then
    fetch() { curl --proto '=https' --tlsv1.2 -fsSL --retry 3 -o "$2" "$1"; }
    fetch_stdout() { curl --proto '=https' --tlsv1.2 -fsSL --retry 3 "$1"; }
elif command -v wget >/dev/null 2>&1; then
    fetch() { wget --https-only -q -O "$2" "$1"; }
    fetch_stdout() { wget --https-only -q -O - "$1"; }
else
    die "needs curl or wget"
fi
if command -v sha256sum >/dev/null 2>&1; then
    sha256() { sha256sum "$1" | cut -d ' ' -f 1; }
elif command -v shasum >/dev/null 2>&1; then
    sha256() { shasum -a 256 "$1" | cut -d ' ' -f 1; }
else
    die "needs sha256sum or shasum"
fi
command -v tar >/dev/null 2>&1 || die "needs tar"

# --- platform ------------------------------------------------------------------------------
case "$(uname -s)" in
    Linux) os=linux ;;
    Darwin) os=darwin ;;
    *) die "unsupported OS $(uname -s). On Windows: npm i -g @webhooktrap/agent, or the .zip from https://github.com/$repo/releases" ;;
esac
case "$(uname -m)" in
    x86_64 | amd64) arch=amd64 ;;
    aarch64 | arm64) arch=arm64 ;;
    *) die "unsupported CPU $(uname -m) (supported: amd64, arm64)" ;;
esac
# An x86_64 shell under Rosetta on Apple silicon: take the native build.
if [ "$os" = darwin ] && [ "$arch" = amd64 ] && [ "$(sysctl -n sysctl.proc_translated 2>/dev/null || echo 0)" = 1 ]; then
    arch=arm64
fi

# --- version -------------------------------------------------------------------------------
version=${WEBHOOKTRAP_VERSION:-}
version=${version#v}
version=${version#agent-v}
if [ -z "$version" ]; then
    # Newest non-prerelease agent-v<major>.* tag (the list is newest first).
    version=$(fetch_stdout "https://api.github.com/repos/$repo/releases?per_page=100" |
        sed -n 's/.*"tag_name": *"agent-v\([0-9][0-9]*\.[0-9][0-9]*\.[0-9][0-9]*\)".*/\1/p' |
        grep "^$major\." | head -n 1) || true
    [ -n "$version" ] || die "no agent-v$major.* release found at https://github.com/$repo/releases; set WEBHOOKTRAP_VERSION"
fi
printf '%s\n' "$version" | grep -Eq '^[0-9]+\.[0-9]+\.[0-9]+(-[0-9A-Za-z.-]+)?$' || die "bad version '$version'"

tag="agent-v$version"
base="https://github.com/$repo/releases/download/$tag"
name="webhooktrap_${version}_${os}_${arch}"
archive="$name.tar.gz"
prefix=${PREFIX:-$HOME/.local}
bindir="$prefix/bin"

tmp=$(mktemp -d 2>/dev/null || mktemp -d -t webhooktrap)
trap 'rm -r -f "$tmp"' EXIT INT TERM

say "webhooktrap $version for $os/$arch"
fetch "$base/$archive" "$tmp/$archive" || die "download failed: $base/$archive"
fetch "$base/SHA256SUMS" "$tmp/SHA256SUMS" || die "download failed: $base/SHA256SUMS"
fetch "$base/SHA256SUMS.sigstore.json" "$tmp/SHA256SUMS.sigstore.json" || die "download failed: $base/SHA256SUMS.sigstore.json"

# --- signature over SHA256SUMS -------------------------------------------------------------
identity="https://github.com/$repo/$workflow@refs/tags/$tag"
if command -v cosign >/dev/null 2>&1; then
    cosign verify-blob --bundle "$tmp/SHA256SUMS.sigstore.json" \
        --certificate-identity "$identity" --certificate-oidc-issuer "$issuer" \
        "$tmp/SHA256SUMS" >/dev/null 2>&1 || die "SHA256SUMS signature did NOT verify for $identity. Nothing installed."
    say "signature verified (Sigstore, $identity)"
elif [ "${WEBHOOKTRAP_REQUIRE_SIGNATURE:-0}" = 1 ]; then
    die "cosign not found and WEBHOOKTRAP_REQUIRE_SIGNATURE=1. Install cosign (https://docs.sigstore.dev) and retry."
else
    say "cosign not found: the SHA-256 is checked, the signature is NOT. To check it yourself:"
    say "  curl -fsSL -O $base/SHA256SUMS -O $base/SHA256SUMS.sigstore.json"
    say "  cosign verify-blob --bundle SHA256SUMS.sigstore.json \\"
    say "    --certificate-identity $identity \\"
    say "    --certificate-oidc-issuer $issuer SHA256SUMS"
    say "  or: gh attestation verify $archive --repo $repo"
fi

# --- checksum ------------------------------------------------------------------------------
want=$(awk -v f="$archive" '$2 == f || $2 == "*" f { print $1 }' "$tmp/SHA256SUMS")
[ -n "$want" ] || die "$archive is not listed in SHA256SUMS"
got=$(sha256 "$tmp/$archive")
[ "$got" = "$want" ] || die "SHA-256 mismatch for $archive (want $want, got $got). Nothing installed."
say "SHA-256 ok"

# --- install -------------------------------------------------------------------------------
mkdir -p "$tmp/x"
tar -xzf "$tmp/$archive" -C "$tmp/x"
[ -f "$tmp/x/$name/webhooktrap" ] || die "archive has no $name/webhooktrap"
mkdir -p "$bindir"
cp "$tmp/x/$name/webhooktrap" "$bindir/.webhooktrap.new.$$"
chmod 0755 "$bindir/.webhooktrap.new.$$"
mv -f "$bindir/.webhooktrap.new.$$" "$bindir/webhooktrap"
say "installed $bindir/webhooktrap"
"$bindir/webhooktrap" version || true

case ":$PATH:" in
    *":$bindir:"*) ;;
    *) say "$bindir is not on your PATH; add it, e.g.: export PATH=\"$bindir:\$PATH\"" ;;
esac
