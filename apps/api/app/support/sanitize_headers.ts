const REDACTED = '[REDACTED]'
const SENSITIVE_HEADERS = new Set(['authorization', 'cookie', 'set-cookie', 'x-api-key'])

export function sanitizeHeaders(headers: Record<string, string | string[] | undefined>) {
  const sanitized: Record<string, string | string[]> = {}

  for (const [key, value] of Object.entries(headers)) {
    if (value === undefined) continue
    sanitized[key] = SENSITIVE_HEADERS.has(key.toLowerCase()) ? REDACTED : value
  }

  return sanitized
}
