import Event from '#models/event'
import Replay from '#models/replay'
import { replayId } from '#support/ids'
import InboxPolicy from '#support/inbox_policy'

const REPLAY_TIMEOUT_MS = 30_000

const BLOCKED_REQUEST_HEADERS = new Set([
  'connection',
  'content-length',
  'host',
  'keep-alive',
  'te',
  'trailer',
  'transfer-encoding',
  'upgrade',
])

const BLOCKED_RESPONSE_HEADERS = new Set([
  'connection',
  'content-encoding',
  'content-length',
  'keep-alive',
  'transfer-encoding',
])

export default class ReplayService {
  static async listForEvent(eventId: string, userId: number | undefined) {
    const event = await Event.find(eventId)
    if (!event) {
      return []
    }

    await InboxPolicy.authorizeView(event.inboxId, userId)

    return Replay.query().where('eventId', eventId).orderBy('createdAt', 'desc')
  }

  static async replay(eventId: string, userId: number | undefined, targetUrl: string) {
    const event = await Event.find(eventId)
    if (!event) {
      throw new Error('Event not found')
    }

    await InboxPolicy.authorizeView(event.inboxId, userId)

    const startedAt = Date.now()

    try {
      const response = await fetch(targetUrl, {
        method: event.method,
        headers: this.buildRequestHeaders(event.headers),
        body: this.buildRequestBody(event),
        signal: AbortSignal.timeout(REPLAY_TIMEOUT_MS),
      })

      const responseBody = await response.text()
      const responseHeaders = this.sanitizeResponseHeaders(response.headers)

      return Replay.create({
        id: replayId(),
        eventId: event.id,
        targetUrl,
        statusCode: response.status,
        responseHeaders,
        responseBody,
        durationMs: Date.now() - startedAt,
        errorCode: null,
        errorMessage: null,
      })
    } catch (error) {
      const { code, message } = this.mapReplayError(error)

      return Replay.create({
        id: replayId(),
        eventId: event.id,
        targetUrl,
        statusCode: null,
        responseHeaders: null,
        responseBody: null,
        durationMs: Date.now() - startedAt,
        errorCode: code,
        errorMessage: message,
      })
    }
  }

  private static buildRequestHeaders(headers: Record<string, string | string[]>) {
    const result: Record<string, string> = {}

    for (const [key, value] of Object.entries(headers)) {
      const normalized = key.toLowerCase()
      if (BLOCKED_REQUEST_HEADERS.has(normalized)) continue

      result[key] = Array.isArray(value) ? value.join(', ') : value
    }

    return result
  }

  private static buildRequestBody(event: Event) {
    if (event.method === 'GET' || event.method === 'HEAD') {
      return undefined
    }

    if (event.bodyText === null) {
      return undefined
    }

    return event.bodyText
  }

  private static sanitizeResponseHeaders(headers: Headers) {
    const result: Record<string, string> = {}

    headers.forEach((value, key) => {
      if (BLOCKED_RESPONSE_HEADERS.has(key.toLowerCase())) return
      result[key] = value
    })

    return result
  }

  private static mapReplayError(error: unknown) {
    if (error instanceof Error) {
      if (error.name === 'TimeoutError' || error.name === 'AbortError') {
        return { code: 'TIMEOUT', message: 'Replay timed out after 30 seconds' }
      }

      if ('code' in error && error.code === 'ECONNREFUSED') {
        return { code: 'CONNECTION_REFUSED', message: 'Connection refused by target server' }
      }

      return { code: 'REPLAY_FAILED', message: error.message }
    }

    return { code: 'REPLAY_FAILED', message: 'Replay failed' }
  }
}
