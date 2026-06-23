import Event from '#models/event'
import Inbox from '#models/inbox'
import { eventId } from '#support/ids'
import { sanitizeHeaders } from '#support/sanitize_headers'
import type { HttpContext } from '@adonisjs/core/http'
import { DateTime } from 'luxon'

export const MAX_INGEST_BODY_BYTES = 1_048_576

type CapturedBody = {
  bodyText: string | null
  bodyJson: Record<string, unknown> | unknown[] | null
  sizeBytes: number
}

export default class IngestService {
  static async capture(inboxId: string, { request }: HttpContext) {
    const inbox = await Inbox.find(inboxId)
    if (!inbox || inbox.isExpired) {
      return { stored: false }
    }

    const contentType = request.header('content-type')?.split(';')[0]?.trim() ?? null
    const { bodyText, bodyJson, sizeBytes } = this.extractBody(request)

    if (sizeBytes > MAX_INGEST_BODY_BYTES) {
      return { stored: false }
    }

    const receivedAt = DateTime.now()

    await Event.create({
      id: eventId(),
      inboxId: inbox.id,
      method: request.method(),
      path: request.url(),
      query: request.qs(),
      headers: sanitizeHeaders(request.headers()),
      bodyText,
      bodyJson,
      contentType,
      ip: request.ip(),
      sizeBytes,
      receivedAt,
      createdAt: receivedAt,
    })

    return { stored: true }
  }

  private static extractBody(request: HttpContext['request']): CapturedBody {
    if (request.method() === 'GET' || request.method() === 'HEAD') {
      return { bodyText: null, bodyJson: null, sizeBytes: 0 }
    }

    const body = request.body()

    if (body === undefined || body === null) {
      return { bodyText: null, bodyJson: null, sizeBytes: 0 }
    }

    if (typeof body === 'string') {
      const bodyJson = this.tryParseJson(body)
      return { bodyText: body, bodyJson, sizeBytes: Buffer.byteLength(body, 'utf8') }
    }

    if (Buffer.isBuffer(body)) {
      const bodyText = body.toString('utf8')
      return {
        bodyText,
        bodyJson: this.tryParseJson(bodyText),
        sizeBytes: body.byteLength,
      }
    }

    const bodyText = JSON.stringify(body)
    return {
      bodyText,
      bodyJson: typeof body === 'object' ? (body as Record<string, unknown>) : null,
      sizeBytes: Buffer.byteLength(bodyText, 'utf8'),
    }
  }

  private static tryParseJson(value: string) {
    try {
      const parsed = JSON.parse(value)
      if (parsed !== null && typeof parsed === 'object') {
        return parsed as Record<string, unknown> | unknown[]
      }
    } catch {
      return null
    }
    return null
  }
}
