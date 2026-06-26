import InboxAccessDeniedException from '#exceptions/inbox_access_denied_exception'
import Inbox from '#models/inbox'

export default class InboxPolicy {
  static async authorizeView(inboxId: string, userId?: number) {
    const inbox = await Inbox.find(inboxId)

    if (!inbox || inbox.isExpired) {
      throw new InboxAccessDeniedException('Inbox not found')
    }

    if (inbox.userId !== null && inbox.userId !== userId) {
      throw new InboxAccessDeniedException('You cannot access this inbox')
    }

    return inbox
  }

  static async authorizeManage(inboxId: string, userId: number) {
    const inbox = await this.authorizeView(inboxId, userId)

    if (inbox.userId !== userId) {
      throw new InboxAccessDeniedException('You cannot manage this inbox')
    }

    return inbox
  }
}
