import dns from 'node:dns/promises'
import { isIP } from 'node:net'

/**
 * Thrown when a replay target resolves to a non-public address. Kept distinct
 * from generic replay failures so ReplayService can map it to its own error code.
 */
export class SsrfBlockedError extends Error {}

const BLOCKED_HOSTNAMES = new Set([
  'localhost',
  'metadata.google.internal', // GCP metadata
])

function isBlockedIpv4(ip: string): boolean {
  const [a, b] = ip.split('.').map(Number)

  if (a === 0) return true // "this" network
  if (a === 10) return true // RFC1918 private
  if (a === 127) return true // loopback
  if (a === 169 && b === 254) return true // link-local incl. cloud metadata (169.254.169.254)
  if (a === 172 && b >= 16 && b <= 31) return true // RFC1918 private
  if (a === 192 && b === 168) return true // RFC1918 private
  if (a === 192 && b === 0) return true // IETF protocol assignments / benchmarking
  if (a >= 224) return true // multicast + reserved

  return false
}

function isBlockedIpv6(ip: string): boolean {
  const normalized = ip.toLowerCase()

  if (normalized === '::1' || normalized === '::') return true
  if (/^fe[89ab][0-9a-f]:/.test(normalized)) return true // link-local fe80::/10
  if (/^f[c-d][0-9a-f]{2}:/.test(normalized)) return true // unique local fc00::/7

  // IPv4-mapped IPv6 (::ffff:a.b.c.d) — unwrap and re-check as IPv4
  const mapped = normalized.match(/^::ffff:(\d+\.\d+\.\d+\.\d+)$/)
  if (mapped) return isBlockedIpv4(mapped[1])

  return false
}

/**
 * Blocks replay targets pointing at loopback/private/link-local addresses
 * (incl. cloud metadata endpoints like 169.254.169.254) so an authenticated
 * user can't turn the replay feature into an SSRF probe against the API's
 * own host or internal network. Resolves the hostname and checks the actual
 * IP rather than just the literal string, to catch DNS rebinding.
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

  const hostname = url.hostname.toLowerCase()
  if (BLOCKED_HOSTNAMES.has(hostname)) {
    throw new SsrfBlockedError('Target host is not allowed')
  }

  const literalFamily = isIP(hostname)
  const addresses = literalFamily
    ? [{ address: hostname, family: literalFamily }]
    : await dns.lookup(hostname, { all: true, verbatim: true }).catch(() => {
        throw new SsrfBlockedError('Could not resolve target host')
      })

  for (const { address, family } of addresses) {
    const blocked = family === 4 ? isBlockedIpv4(address) : isBlockedIpv6(address)
    if (blocked) {
      throw new SsrfBlockedError('Target resolves to a private or internal address')
    }
  }
}
