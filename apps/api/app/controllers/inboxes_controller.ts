import InboxService from '#services/inbox_service'
import type { HttpContext } from '@adonisjs/core/http'

export default class InboxesController {
  async store({ serialize }: HttpContext) {
    const inbox = await InboxService.createAnonymous()

    return serialize({
      inbox: {
        id: inbox.id,
        name: inbox.name,
        expiresAt: inbox.expiresAt,
        ingestUrl: `/i/${inbox.id}`,
      },
    })
  }
}