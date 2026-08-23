import dns from 'node:dns/promises'
import { isIP } from 'node:net'
import env from '#start/env'
import logger from '@adonisjs/core/services/logger'

/**
 * Thrown when a replay target resolves to a non-public address. Kept distinct
 * from generic replay failures so ReplayService can map it to its own error code.
 */
export class SsrfBlockedError extends Error {}

// Never bypassable, even with ALLOW_INSECURE_REPLAY_TARGETS: cloud metadata
// endpoints have no legitimate replay use case, local or otherwise.
const ALWAYS_BLOCKED_HOSTNAMES = new Set([
  'metadata.google.internal', // GCP metadata
])

// Bypassable with ALLOW_INSECURE_REPLAY_TARGETS=true for local/self-hosted dev.
const PRIVATE_HOSTNAMES = new Set(['localhost'])

function isAlwaysBlockedIpv4(ip: string): boolean {
  const [a, b] = ip.split('.').map(Number)

  if (a === 0) return true // "this" network
  if (a === 169 && b === 254) return true // link-local incl. cloud metadata (169.254.169.254)
  if (a === 192 && b === 0) return true // IETF protocol assignments / benchmarking
  if (a >= 224) return true // multicast + reserved

  return false
}

function isPrivateIpv4(ip: string): boolean {
  const [a, b] = ip.split('.').map(Number)

  if (a === 10) return true // RFC1918 private
  if (a === 127) return true // loopback
  if (a === 172 && b >= 16 && b <= 31) return true // RFC1918 private
  if (a === 192 && b === 168) return true // RFC1918 private

  return false
}

function isAlwaysBlockedIpv6(ip: string): boolean {
  const normalized = ip.toLowerCase()

  if (normalized === '::') return true // unspecified
  if (/^fe[89ab][0-9a-f]:/.test(normalized)) return true // link-local fe80::/10

  const mapped = normalized.match(/^::ffff:(\d+\.\d+\.\d+\.\d+)$/)
  if (mapped) return isAlwaysBlockedIpv4(mapped[1])

  return false
}

function isPrivateIpv6(ip: string): boolean {
  const normalized = ip.toLowerCase()

  if (normalized === '::1') return true // loopback
  if (/^f[c-d][0-9a-f]{2}:/.test(normalized)) return true // unique local fc00::/7

  // IPv4-mapped IPv6 (::ffff:a.b.c.d) — unwrap and re-check as IPv4
  const mapped = normalized.match(/^::ffff:(\d+\.\d+\.\d+\.\d+)$/)
  if (mapped) return isPrivateIpv4(mapped[1])

  return false
}

function isInsecureReplayAllowed(): boolean {
  return env.get('ALLOW_INSECURE_REPLAY_TARGETS', false)
}

/**
 * Blocks replay targets pointing at loopback/private/link-local addresses
 * (incl. cloud metadata endpoints like 169.254.169.254) so an authenticated
 * user can't turn the replay feature into an SSRF probe against the API's
 * own host or internal network. Resolves the hostname and checks the actual
 * IP rather than just the literal string, to catch DNS rebinding.
 *
 * Loopback/RFC1918 targets (but never cloud metadata) can be allowed via
 * ALLOW_INSECURE_REPLAY_TARGETS=true, for local/self-hosted setups where
 * replaying to localhost is the whole point. Boot fails if that flag is
 * combined with NODE_ENV=production (see start/env.ts).
 */
export async function assertSafeReplayTarget(rawUrl: string): Promise<void> {
  let url: URL

  try {
    url = new URL(rawUrl)
  } catch {
    throw new SsrfBlockedError('Invalid target URL')
  }

  if (url.protocol !== 'http:' && url.protocol !== 'https:') {
    throw new SsrfBlockedError('Only http/https replay targets are allowed')
  }

  const insecureAllowed = isInsecureReplayAllowed()
  const hostname = url.hostname.toLowerCase()

  if (ALWAYS_BLOCKED_HOSTNAMES.has(hostname)) {
    throw new SsrfBlockedError('Target host is not allowed')
  }

  if (PRIVATE_HOSTNAMES.has(hostname) && !insecureAllowed) {
    throw new SsrfBlockedError('Target host is not allowed')
  }

  // URL.hostname keeps the brackets around an IPv6 literal (e.g. "[::1]"),
  // which net.isIP()/dns.lookup() don't recognize as an IP — strip them.
  const bareHostname =
    hostname.startsWith('[') && hostname.endsWith(']') ? hostname.slice(1, -1) : hostname

  const literalFamily = isIP(bareHostname)
  const addresses = literalFamily
    ? [{ address: bareHostname, family: literalFamily }]
    : await dns.lookup(bareHostname, { all: true, verbatim: true }).catch(() => {
        throw new SsrfBlockedError('Could not resolve target host')
      })

  let bypassedPrivateTarget = PRIVATE_HOSTNAMES.has(hostname) && insecureAllowed

  for (const { address, family } of addresses) {
    const alwaysBlocked = family === 4 ? isAlwaysBlockedIpv4(address) : isAlwaysBlockedIpv6(address)
    if (alwaysBlocked) {
      throw new SsrfBlockedError('Target resolves to a private or internal address')
    }

    const isPrivate = family === 4 ? isPrivateIpv4(address) : isPrivateIpv6(address)
    if (isPrivate && !insecureAllowed) {
      throw new SsrfBlockedError('Target resolves to a private or internal address')
    }

    if (isPrivate) {
      bypassedPrivateTarget = true
    }
  }

  if (bypassedPrivateTarget) {
    logger.warn(
      { targetUrl: rawUrl },
      'ssrf_guard: replay allowed to a private/internal target because ALLOW_INSECURE_REPLAY_TARGETS=true'
    )
  }
}
