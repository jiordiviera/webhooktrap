import type Event from '#models/event'
import { BaseTransformer } from '@adonisjs/core/transformers'

export default class EventTransformer extends BaseTransformer<Event> {
  toObject() {
    return this.pick(this.resource, [
      'id',
      'inboxId',
      'method',
      'path',
      'contentType',
      'sizeBytes',
      'receivedAt',
    ])
  }
}

export class EventDetailTransformer extends EventTransformer {
  toObject() {
    return {
      ...super.toObject(),
      query: this.resource.query,
      headers: this.resource.headers,
      bodyText: this.resource.bodyText,
      bodyJson: this.resource.bodyJson,
      ip: this.resource.ip,
      createdAt: this.resource.createdAt,
    }
  }
}
