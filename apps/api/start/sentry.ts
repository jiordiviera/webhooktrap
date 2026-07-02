/**
 * Preloaded first so it initializes before anything else can throw.
 * No-op when SENTRY_DSN isn't set (local dev, tests) — see #support/error_tracker.
 */
import env from '#start/env'
import * as Sentry from '@sentry/node'

const dsn = env.get('SENTRY_DSN')

if (dsn) {
  Sentry.init({
    dsn,
    environment: env.get('NODE_ENV'),
    tracesSampleRate: 0,
  })
}
