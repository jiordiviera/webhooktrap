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
  | Media storage (Vercel Blob)
  |----------------------------------------------------------
  */
  BLOB_READ_WRITE_TOKEN: Env.schema.string.optional(),
  MEDIA_CDN_BASE_URL: Env.schema.string.optional(),
  MEDIA_MAX_FILE_SIZE_MB: Env.schema.number.optional(),
})
