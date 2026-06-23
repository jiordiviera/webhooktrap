import InboxService from '#services/inbox_service'
import { serializeInbox } from '#support/inbox_payload'
import type { HttpContext } from '@adonisjs/core/http'

export default class InboxesController {
  async index({ auth, serialize }: HttpContext) {
    const inboxes = await InboxService.listForUser(auth.getUserOrFail().id)

    return serialize({
      inboxes: inboxes.map(serializeInbox),
    })
  }

  async store({ auth, serialize }: HttpContext) {
    const user = auth.user
    const inbox = user
      ? await InboxService.createForUser(user.id)
      : await InboxService.createAnonymous()

    return serialize({
      inbox: serializeInbox(inbox),
    })
  }
}