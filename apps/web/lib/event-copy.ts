import { inboxIngestUrl } from '@/lib/public-url'
import type { EventDetail } from '@/lib/events'

function shellEscape(value: string) {
  return `'${value.replace(/'/g, `'\\''`)}'`
}

export function eventRequestUrl(event: EventDetail) {
  if (event.path.startsWith('http://') || event.path.startsWith('https://')) {
    return event.path
  }

  const url = new URL(inboxIngestUrl(event.inboxId))

  for (const [key, value] of Object.entries(event.query)) {
    url.searchParams.set(key, value)
  }

  return url.toString()
}

export function buildEventJson(event: EventDetail) {
  if (event.bodyJson !== null) {
    return JSON.stringify(event.bodyJson, null, 2)
  }

  if (event.bodyText) {
    try {
      return JSON.stringify(JSON.parse(event.bodyText), null, 2)
    } catch {
      return event.bodyText
    }
  }

  return ''
}

export function buildEventCurl(event: EventDetail) {
  const url = eventRequestUrl(event)
  const lines = [`curl -X ${event.method} ${shellEscape(url)}`]

  for (const [key, value] of Object.entries(event.headers)) {
    const headerValue = Array.isArray(value) ? value.join(', ') : value
    if (headerValue === '[REDACTED]') continue
    lines.push(`  -H ${shellEscape(`${key}: ${headerValue}`)}`)
  }

  const body = buildEventJson(event)
  if (body && event.method !== 'GET' && event.method !== 'HEAD') {
    lines.push(`  -d ${shellEscape(body)}`)
  }

  return lines.join(' \\\n')
}