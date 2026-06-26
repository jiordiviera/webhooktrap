/*
|--------------------------------------------------------------------------
| Test runner entrypoint
|--------------------------------------------------------------------------
|
| The "test.ts" file is the entrypoint for running tests using Japa.
|
| Either you can run this file directly or use the "test"
| command to run this file and monitor file changes.
|
*/

/**
 * Force test DB before Adonis loads dotenv files.
 * process.env wins over .env / .env.test (loaded in parallel), so this
 * keeps tests on sqlite even when hookscope/.env points at postgres.
 */
process.env.NODE_ENV = 'test'
process.env.SESSION_DRIVER = 'memory'
process.env.DB_CONNECTION = 'sqlite'
process.env.DB_DATABASE = ':memory:'
delete process.env.DATABASE_URL
process.env.DRIVE_DISK = 'r2'
process.env.R2_KEY = 'test-r2-key'
process.env.R2_SECRET = 'test-r2-secret'
process.env.R2_BUCKET = 'test-bucket'
process.env.R2_ENDPOINT = 'https://test.r2.cloudflarestorage.com'
process.env.MEDIA_CDN_BASE_URL = 'https://media.hookscope.test'

import 'reflect-metadata'
import { Ignitor, prettyPrintError } from '@adonisjs/core'
import { configure, processCLIArgs, run } from '@japa/runner'

/**
 * URL to the application root. AdonisJS need it to resolve
 * paths to file and directories for scaffolding commands
 */
const APP_ROOT = new URL('../', import.meta.url)

/**
 * The importer is used to import files in context of the
 * application.
 */
const IMPORTER = (filePath: string) => {
  if (filePath.startsWith('./') || filePath.startsWith('../')) {
    return import(new URL(filePath, APP_ROOT).href)
  }
  return import(filePath)
}

new Ignitor(APP_ROOT, { importer: IMPORTER })
  .tap((app) => {
    app.booting(async () => {
      await import('#start/env')
    })
    app.listen('SIGTERM', () => app.terminate())
    app.listenIf(app.managedByPm2, 'SIGINT', () => app.terminate())
  })
  .testRunner()
  .configure(async (app) => {
    const { runnerHooks, ...config } = await import('../tests/bootstrap.js')

    processCLIArgs(process.argv.splice(2))
    configure({
      ...app.rcFile.tests,
      ...config,
      ...{
        setup: runnerHooks.setup,
        teardown: runnerHooks.teardown.concat([() => app.terminate()]),
      },
    })
  })
  .run(() => run())
  .catch((error) => {
    process.exitCode = 1
    prettyPrintError(error)
  })
