import type Inbox from '#models/inbox'
import { BaseTransformer } from '@adonisjs/core/transformers'
import { urlFor } from '@adonisjs/core/services/url_builder'
import { webUrl } from '#config/app'

export default class InboxTransformer extends BaseTransformer<Inbox> {
  toObject() {
    const lastEvent = this.resource.events?.[0]
    const eventsCount = Number(this.resource.$extras.events_count ?? 0)

    return {
      ...this.pick(this.resource, [
        'id',
        'name',
        'expiresAt',
        'defaultReplayUrl',
        'createdAt',
        'updatedAt',
      ]),
      ingestUrl: urlFor('ingest', { inboxId: this.resource.id }, { prefixUrl: webUrl }),
      eventsCount,
      lastEventAt: lastEvent?.receivedAt ?? null,
    }
  }
}
