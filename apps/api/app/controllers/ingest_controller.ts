import IngestService, { MAX_INGEST_BODY_BYTES } from '#services/ingest_service'
import type { HttpContext } from '@adonisjs/core/http'

export default class IngestController {
  /**
   * Public webhook ingest — always 200 OK for providers (Stripe, GitHub, …).
   */
  async handle(ctx: HttpContext) {
    const contentLength = Number(ctx.request.header('content-length') ?? 0)
    if (contentLength > MAX_INGEST_BODY_BYTES) {
      return { received: true }
    }

    await IngestService.capture(ctx.params.inboxId, ctx)

    return { received: true }
  }
}
