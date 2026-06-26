import type Replay from '#models/replay'
import { BaseTransformer } from '@adonisjs/core/transformers'

export default class ReplayTransformer extends BaseTransformer<Replay> {
  toObject() {
    return this.pick(this.resource, [
      'id',
      'eventId',
      'targetUrl',
      'statusCode',
      'responseHeaders',
      'responseBody',
      'durationMs',
      'errorCode',
      'errorMessage',
      'createdAt',
    ])
  }
}