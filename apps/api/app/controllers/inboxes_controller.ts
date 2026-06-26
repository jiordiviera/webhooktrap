import EventService from '#services/event_service'
import InboxService from '#services/inbox_service'
import InboxPolicy from '#support/inbox_policy'
import EventTransformer from '#transformers/event_transformer'
import InboxTransformer from '#transformers/inbox_transformer'
import { createInboxValidator, updateInboxValidator } from '#validators/inbox_validator'
import type { HttpContext } from '@adonisjs/core/http'

export default class InboxesController {
  async index({ auth, serialize }: HttpContext) {
    const inboxes = await InboxService.listForUser(auth.getUserOrFail().id)

    return serialize({
      inboxes: InboxTransformer.transform(inboxes),
    })
  }

  async show({ auth, params, serialize }: HttpContext) {
    const inbox = await InboxPolicy.authorizeView(params.id, auth.user?.id)

    return serialize({
      inbox: InboxTransformer.transform(inbox),
    })
  }

  async store({ auth, request, serialize }: HttpContext) {
    const { name } = await request.validateUsing(createInboxValidator)
    const user = auth.user
    const inbox = user
      ? await InboxService.createForUser(user.id, name)
      : await InboxService.createAnonymous(name)

    return serialize({
      inbox: InboxTransformer.transform(inbox),
    })
  }

  async update({ auth, params, request, serialize }: HttpContext) {
    const inbox = await InboxPolicy.authorizeManage(params.id, auth.getUserOrFail().id)
    const payload = await request.validateUsing(updateInboxValidator)

    const updated = await InboxService.update(inbox, {
      name: payload.name,
      defaultReplayUrl: payload.default_replay_url,
    })

    return serialize({
      inbox: InboxTransformer.transform(updated),
    })
  }

  async events({ auth, params, serialize }: HttpContext) {
    const events = await EventService.listForInbox(params.id, auth.user?.id)

    return serialize({
      events: EventTransformer.transform(events),
    })
  }
}
