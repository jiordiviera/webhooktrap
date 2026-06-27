import { z } from 'zod'

const envSchema = z.object({
  NODE_ENV: z
    .enum(['development', 'production', 'test'])
    .default('development'),

  /** API origin — used by proxy and server-side fetch. */
  APP_URL: z.url().default('http://localhost:3333'),

  /** Web origin — used for metadata, OAuth callbacks, ingest links. */
  WEB_URL: z.url().default('http://localhost:7777'),

  /** Public web origin (injected at build-time by Next.js). */
  NEXT_PUBLIC_WEB_URL: z.url().optional(),

  /** Public API origin (injected at build-time by Next.js). */
  NEXT_PUBLIC_API_URL: z.url().optional(),
})

function parseEnv() {
  const isServer = typeof process !== 'undefined' && typeof process.exit === 'function'
  const result = envSchema.safeParse(process.env)

  if (!result.success) {
    if (isServer) {
      console.error('Invalid environment variables:')
      for (const issue of result.error.issues) {
        console.error(`  ${issue.path.join('.')}: ${issue.message}`)
      }
      process.exit(1)
    }
    return envSchema.parse({ NODE_ENV: process.env.NODE_ENV })
  }

  return result.data
}

export const env = parseEnv()
