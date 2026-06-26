import Inbox from '#models/inbox'
import type { ListQuery } from '#support/list_query'
import { resolveSortColumn } from '#support/list_query'
import { inboxId } from '#support/ids'
import { DateTime } from 'luxon'

const ANONYMOUS_TTL_HOURS = 48

export default class InboxService {
  static async createAnonymous(name = 'Untitled inbox') {
    return Inbox.create({
      id: inboxId(),
      name,
      expiresAt: DateTime.now().plus({ hours: ANONYMOUS_TTL_HOURS }),
    })
  }

  static async createForUser(userId: number, name = 'Untitled inbox') {
    return Inbox.create({
      id: inboxId(),
      userId,
      name,
      expiresAt: null,
    })
  }

  static async listForUser(userId: number, query: ListQuery) {
    const dbQuery = Inbox.query()
      .where('userId', userId)
      .withCount('events')
      .preload('events', (eventsQuery) => {
        eventsQuery.orderBy('receivedAt', 'desc').limit(1)
      })

    if (query.search) {
      dbQuery.where((builder) => {
        builder.whereILike('name', `%${query.search}%`).orWhereILike('id', `%${query.search}%`)
      })
    }

    const sortColumn = resolveSortColumn(
      query.sort,
      {
        name: 'name',
        eventsCount: 'events_count',
        lastEventAt: 'created_at',
        createdAt: 'created_at',
      },
      'created_at'
    )

    dbQuery.orderBy(sortColumn, query.sortDir)

    return dbQuery.paginate(query.page, query.pageSize)
  }

  static async update(
    inbox: Inbox,
    data: {
      name?: string
      defaultReplayUrl?: string | null
    }
  ) {
    inbox.merge(data)
    await inbox.save()
    return inbox
  }

  static async delete(inbox: Inbox) {
    await inbox.delete()
  }
}
