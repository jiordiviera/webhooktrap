import { InboxSchema } from '#database/schema'
import Event from '#models/event'
import User from '#models/user'
import { belongsTo, hasMany } from '@adonisjs/lucid/orm'
import type { BelongsTo, HasMany } from '@adonisjs/lucid/types/relations'
import { DateTime } from 'luxon'

export default class Inbox extends InboxSchema {
  @belongsTo(() => User)
  declare user: BelongsTo<typeof User>

  @hasMany(() => Event)
  declare events: HasMany<typeof Event>

  get isExpired() {
    if (!this.expiresAt) return false
    return this.expiresAt < DateTime.now()
  }
}
