import Event from '#models/event'
import InboxPolicy from '#support/inbox_policy'
import type { ListQuery } from '#support/list_query'
import { resolveSortColumn } from '#support/list_query'

export default class EventService {
  static async listForInbox(inboxId: string, userId: number | undefined, query: ListQuery) {
    await InboxPolicy.authorizeView(inboxId, userId)

    const dbQuery = Event.query().where('inboxId', inboxId)

    if (query.search) {
      dbQuery.where((builder) => {
        builder.whereILike('path', `%${query.search}%`).orWhereILike('method', `%${query.search}%`)
      })
    }

    if (query.filters.method) {
      dbQuery.where('method', query.filters.method)
    }

    const sortColumn = resolveSortColumn(
      query.sort,
      {
        method: 'method',
        path: 'path',
        receivedAt: 'received_at',
        sizeBytes: 'size_bytes',
      },
      'received_at'
    )

    dbQuery.orderBy(sortColumn, query.sortDir)

    return dbQuery.paginate(query.page, query.pageSize)
  }

  static async getForViewer(eventId: string, userId: number | undefined) {
    const event = await Event.find(eventId)
    if (!event) {
      return null
    }

    await InboxPolicy.authorizeView(event.inboxId, userId)
    return event
  }
}
