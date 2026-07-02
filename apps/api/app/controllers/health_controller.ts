import db from '@adonisjs/lucid/services/db'
import type { HttpContext } from '@adonisjs/core/http'

export default class HealthController {
  async show({ response }: HttpContext) {
    try {
      await db.rawQuery('select 1')
    } catch {
      return response.status(503).send({
        status: 'error',
        database: 'unreachable',
      })
    }

    return response.status(200).send({
      status: 'ok',
      database: 'ok',
      uptime: process.uptime(),
      timestamp: new Date().toISOString(),
    })
  }
}
