import Inbox from '#models/inbox'
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

  static async listForUser(userId: number) {
    return Inbox.query()
      .where('userId', userId)
      .withCount('events')
      .preload('events', (query) => {
        query.orderBy('receivedAt', 'desc').limit(1)
      })
      .orderBy('createdAt', 'desc')
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
}
