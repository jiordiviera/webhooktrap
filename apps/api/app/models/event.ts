import { EventSchema } from '#database/schema'
import Inbox from '#models/inbox'
import Replay from '#models/replay'
import ShareToken from '#models/share_token'
import { belongsTo, hasMany } from '@adonisjs/lucid/orm'
import type { BelongsTo, HasMany } from '@adonisjs/lucid/types/relations'

export default class Event extends EventSchema {
  static table = 'events'

  @belongsTo(() => Inbox)
  declare inbox: BelongsTo<typeof Inbox>

  @hasMany(() => Replay)
  declare replays: HasMany<typeof Replay>

  @hasMany(() => ShareToken)
  declare shareTokens: HasMany<typeof ShareToken>
}
