import { MAX_INGEST_BODY_BYTES } from '#services/ingest_service'
import type { HttpContext } from '@adonisjs/core/http'
import type { NextFn } from '@adonisjs/core/types/http'

export default class IngestBodyLimitMiddleware {
  async handle({ request }: HttpContext, next: NextFn) {
    const contentLength = Number(request.header('content-length') ?? 0)
    if (contentLength > MAX_INGEST_BODY_BYTES) {
      return { received: true }
    }

    return next()
  }
}