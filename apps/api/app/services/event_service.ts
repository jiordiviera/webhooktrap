import Event from '#models/event'
import InboxPolicy from '#support/inbox_policy'

const DEFAULT_EVENT_LIMIT = 50

export default class EventService {
  static async listForInbox(
    inboxId: string,
    userId: number | undefined,
    limit = DEFAULT_EVENT_LIMIT
  ) {
    await InboxPolicy.authorizeView(inboxId, userId)

    return Event.query().where('inboxId', inboxId).orderBy('receivedAt', 'desc').limit(limit)
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
