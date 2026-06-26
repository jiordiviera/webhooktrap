import type Inbox from '#models/inbox'
import { BaseTransformer } from '@adonisjs/core/transformers'

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
      ingestUrl: `/i/${this.resource.id}`,
      eventsCount,
      lastEventAt: lastEvent?.receivedAt ?? null,
    }
  }
}
