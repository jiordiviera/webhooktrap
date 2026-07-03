import { BaseCommand } from '@adonisjs/core/ace'
import type { CommandOptions } from '@adonisjs/core/types/ace'

export default class PurgeExpiredInboxes extends BaseCommand {
  static commandName = 'purge:expired-inboxes'
  static description = 'Delete anonymous inboxes (and their events/replays) past their expiresAt'

  static options: CommandOptions = {
    startApp: true,
  }

  async run() {
    const { default: Inbox } = await import('#models/inbox')
    const { DateTime } = await import('luxon')

    const expired = await Inbox.query()
      .whereNotNull('expiresAt')
      .where('expiresAt', '<', DateTime.now().toSQL()!)

    if (expired.length === 0) {
      this.logger.info('No expired inboxes to purge.')
      return
    }

    for (const inbox of expired) {
      await inbox.delete()
    }

    this.logger.success(`Purged ${expired.length} expired inbox(es).`)
  }
}
