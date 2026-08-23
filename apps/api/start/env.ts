import { Env } from '@adonisjs/core/env'
import { fileURLToPath } from 'node:url'
import { dirname, join } from 'node:path'
import { existsSync } from 'node:fs'

const Dirname = dirname(fileURLToPath(import.meta.url))

let dir = Dirname
while (dir !== dirname(dir)) {
  if (existsSync(join(dir, '.env'))) break
  dir = dirname(dir)
}

const env = await Env.create(new URL(dir + '/', import.meta.url), {
  // Node
  NODE_ENV: Env.schema.enum(['development', 'production', 'test'] as const),
  PORT: Env.schema.number(),
  HOST: Env.schema.string({ format: 'host' }),
  LOG_LEVEL: Env.schema.string(),

  // App
  APP_KEY: Env.schema.secret(),
  APP_URL: Env.schema.string({ format: 'url', tld: false }),
  WEB_URL: Env.schema.string({ format: 'url', tld: false }),

  // Replay SSRF guard — see app/support/ssrf_guard.ts. Lets local/self-hosted
  // setups replay events to localhost/RFC1918 targets. Never allowed in
  // production (enforced below) since it would reintroduce an SSRF exploitable
  // by any authenticated user against internal infra.
  ALLOW_INSECURE_REPLAY_TARGETS: Env.schema.boolean.optional(),

  // Session
  SESSION_DRIVER: Env.schema.enum(['cookie', 'memory', 'database'] as const),

  /*
  |--------------------------------------------------------------------------
  | Database (Lucid)
  | @see https://docs.adonisjs.com/guides/database/lucid#configuration
  |--------------------------------------------------------------------------
  */
  DB_CONNECTION: Env.schema.enum(['postgres', 'sqlite'] as const),
  DATABASE_URL: Env.schema.string.optional(),
  DB_DATABASE: Env.schema.string.optional(),

  /*
  |----------------------------------------------------------
  | OAuth social (GitHub + Google)
  |----------------------------------------------------------
  */
  GITHUB_CLIENT_ID: Env.schema.string.optional(),
  GITHUB_CLIENT_SECRET: Env.schema.string.optional(),
  GOOGLE_CLIENT_ID: Env.schema.string.optional(),
  GOOGLE_CLIENT_SECRET: Env.schema.string.optional(),

  /*
  |----------------------------------------------------------
  | Media storage (Cloudflare R2 via Drive)
  |----------------------------------------------------------
  */
  MEDIA_CDN_BASE_URL: Env.schema.string.optional(),
  MEDIA_MAX_FILE_SIZE_MB: Env.schema.number.optional(),

  /*
  |----------------------------------------------------------
  | Variables for configuring the drive package
  |----------------------------------------------------------
  */
  DRIVE_DISK: Env.schema.enum(['r2'] as const),
  R2_KEY: Env.schema.string(),
  R2_SECRET: Env.schema.string(),
  R2_BUCKET: Env.schema.string(),
  R2_ENDPOINT: Env.schema.string(),

  /*
  |----------------------------------------------------------
  | Variables for configuring the mail package
  |----------------------------------------------------------
  */
  MAIL_MAILER: Env.schema.enum(['resend'] as const),
  MAIL_FROM_NAME: Env.schema.string(),
  MAIL_FROM_ADDRESS: Env.schema.string(),
  RESEND_API_KEY: Env.schema.string(),

  REDIS_HOST: Env.schema.string({ format: 'host' }),
  REDIS_PORT: Env.schema.number(),
  REDIS_PASSWORD: Env.schema.secret.optional(),

  /*
  |----------------------------------------------------------
  | Variables for configuring the limiter package
  |----------------------------------------------------------
  */
  LIMITER_STORE: Env.schema.enum(['redis', 'memory'] as const),

  /*
  |----------------------------------------------------------
  | Error tracking (Sentry) — optional, reporting is a no-op
  | when unset so this never blocks local/dev boot
  |----------------------------------------------------------
  */
  SENTRY_DSN: Env.schema.string.optional(),
})

if (env.get('NODE_ENV') === 'production' && env.get('ALLOW_INSECURE_REPLAY_TARGETS', false)) {
  throw new Error(
    'ALLOW_INSECURE_REPLAY_TARGETS cannot be enabled when NODE_ENV=production — it would ' +
      'reintroduce an SSRF vector exploitable by any authenticated user against internal ' +
      'infra. Unset it or run with a non-production NODE_ENV.'
  )
}

export default env
