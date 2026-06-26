import EventService from '#services/event_service'
import InboxService from '#services/inbox_service'
import { paginatedPayload } from '#support/paginated_response'
import { parseListQuery } from '#support/list_query'
import InboxPolicy from '#support/inbox_policy'
import EventTransformer from '#transformers/event_transformer'
import InboxTransformer from '#transformers/inbox_transformer'
import { createInboxValidator, updateInboxValidator } from '#validators/inbox_validator'
import type { HttpContext } from '@adonisjs/core/http'

export default class InboxesController {
  async index({ auth, request, serialize }: HttpContext) {
    const listQuery = await parseListQuery(request, {
      sortable: ['name', 'eventsCount', 'lastEventAt', 'createdAt'],
      defaultSort: 'createdAt',
      defaultSortDir: 'desc',
    })

    const inboxes = await InboxService.listForUser(auth.getUserOrFail().id, listQuery)

    return serialize(
      paginatedPayload('inboxes', inboxes, InboxTransformer.transform(inboxes.all()))
    )
  }

  async show({ auth, params, serialize }: HttpContext) {
    const inbox = await InboxPolicy.authorizeView(params.id, auth.user?.id)

    await inbox.loadCount('events')
    await inbox.load('events', (eventsQuery) => {
      eventsQuery.orderBy('receivedAt', 'desc').limit(1)
    })

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

  async events({ auth, params, request, serialize }: HttpContext) {
    const listQuery = await parseListQuery(request, {
      sortable: ['method', 'path', 'receivedAt', 'sizeBytes'],
      defaultSort: 'receivedAt',
      defaultSortDir: 'desc',
      filterable: ['method'],
    })

    const events = await EventService.listForInbox(params.id, auth.user?.id, listQuery)

    return serialize(paginatedPayload('events', events, EventTransformer.transform(events.all())))
  }

  async destroy({ auth, params, serialize }: HttpContext) {
    const inbox = await InboxPolicy.authorizeManage(params.id, auth.getUserOrFail().id)
    await InboxService.delete(inbox)

    return serialize({
      deleted: true,
    })
  }
}
