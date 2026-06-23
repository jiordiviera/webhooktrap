import { EventSchema } from '#database/schema'
import Inbox from '#models/inbox'
import Replay from '#models/replay'
import ShareToken from '#models/share_token'
import { belongsTo, column, hasMany } from '@adonisjs/lucid/orm'
import type { BelongsTo, HasMany } from '@adonisjs/lucid/types/relations'

function jsonColumn() {
  return column({
    prepare: (value: unknown) =>
      value === null || value === undefined ? value : JSON.stringify(value),
    consume: (value: unknown) => {
      if (value === null || value === undefined) return value
      if (typeof value === 'string') return JSON.parse(value)
      return value
    },
  })
}

export default class Event extends EventSchema {
  static table = 'events'
  static selfAssignPrimaryKey = true

  @jsonColumn()
  declare query: Record<string, string>

  @jsonColumn()
  declare headers: Record<string, string | string[]>

  @jsonColumn()
  declare bodyJson: Record<string, unknown> | unknown[] | null

  @belongsTo(() => Inbox)
  declare inbox: BelongsTo<typeof Inbox>

  @hasMany(() => Replay)
  declare replays: HasMany<typeof Replay>

  @hasMany(() => ShareToken)
  declare shareTokens: HasMany<typeof ShareToken>
}
