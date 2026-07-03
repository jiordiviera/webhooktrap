import type Inbox from '#models/inbox'
import { BaseTransformer } from '@adonisjs/core/transformers'
import { webUrl } from '#config/app'

export default class InboxTransformer extends BaseTransformer<Inbox> {
  toObject() {
    const lastEvent = this.resource.events?.[0]
    const eventsCount = Number(this.resource.$extras.events_count ?? 0)

    return {
      ...this.pick(this.resource, [
        'id',
        'name',
        'userId',
        'expiresAt',
        'defaultReplayUrl',
        'createdAt',
        'updatedAt',
      ]),
      ingestUrl: new URL(`/hooks/${this.resource.id}`, webUrl).toString(),
      eventsCount,
      lastEventAt: lastEvent?.receivedAt ?? null,
    }
  }
}
