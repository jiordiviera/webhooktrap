import type { HttpContext } from '@adonisjs/core/http'
import type { NextFn } from '@adonisjs/core/types/http'

export default class ForceJsonResponseMiddleware {
  handle(ctx: HttpContext, next: NextFn) {
    if (!ctx.request.url().includes('/auth/oauth/') && !ctx.request.url().startsWith('/i/')) {
      ctx.request.request.headers.accept = 'application/json'
    }
    return next()
  }
}
