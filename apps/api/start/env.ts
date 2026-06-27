/*
|--------------------------------------------------------------------------
| Environment variables service
|--------------------------------------------------------------------------
|
| The `Env.create` method creates an instance of the Env service. The
| service validates the environment variables and also cast values
| to JavaScript data types.
|
*/

import { Env } from '@adonisjs/core/env'

/**
 * Charge .env depuis la racine du monorepo (hookscope/.env), pas apps/api/.
 */
export default await Env.create(new URL('../../../', import.meta.url), {
  // Node
  NODE_ENV: Env.schema.enum(['development', 'production', 'test'] as const),
  PORT: Env.schema.number(),
  HOST: Env.schema.string({ format: 'host' }),
  LOG_LEVEL: Env.schema.string(),

  // App
  APP_KEY: Env.schema.secret(),
  APP_URL: Env.schema.string({ format: 'url', tld: false }),
  WEB_URL: Env.schema.string({ format: 'url', tld: false }),

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
})
