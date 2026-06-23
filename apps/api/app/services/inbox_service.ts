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
}