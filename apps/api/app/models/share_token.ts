import { ShareTokenSchema } from '#database/schema'
import Event from '#models/event'
import { belongsTo } from '@adonisjs/lucid/orm'
import type { BelongsTo } from '@adonisjs/lucid/types/relations'
import { DateTime } from 'luxon'

export default class ShareToken extends ShareTokenSchema {
  static table = 'share_tokens'

  @belongsTo(() => Event)
  declare event: BelongsTo<typeof Event>

  get isExpired() {
    if (!this.expiresAt) return false
    return this.expiresAt < DateTime.now()
  }
}
