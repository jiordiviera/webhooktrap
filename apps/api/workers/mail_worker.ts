import 'reflect-metadata'
import { Ignitor } from '@adonisjs/core'
import { Worker } from 'bullmq'

const APP_ROOT = new URL('../', import.meta.url)

const IMPORTER = (filePath: string) => {
  if (filePath.startsWith('./') || filePath.startsWith('../')) {
    return import(new URL(filePath, APP_ROOT).href)
  }
  return import(filePath)
}

const app = new Ignitor(APP_ROOT, { importer: IMPORTER })
  .tap((a) => {
    a.booting(async () => {
      await import('#start/env')
    })
    a.listen('SIGTERM', () => a.terminate())
    a.listenIf(a.managedByPm2, 'SIGINT', () => a.terminate())
  })
  .createApp('web')

await app.init()
await app.boot()

const { default: mail } = await import('@adonisjs/mail/services/main')
const { default: env } = await import('#start/env')

const passwordSecret = env.get('REDIS_PASSWORD')
const connection = {
  host: env.get('REDIS_HOST'),
  port: env.get('REDIS_PORT'),
  password: passwordSecret ? passwordSecret.release() : undefined,
}

new Worker(
  'emails',
  async (job) => {
    if (job.name === 'send_email') {
      const { mailMessage, config, mailerName } = job.data
      await mail.use(mailerName).sendCompiled(mailMessage, config)
    }
  },
  { connection }
)
