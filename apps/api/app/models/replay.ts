import { ReplaySchema } from '#database/schema'
import Event from '#models/event'
import { belongsTo } from '@adonisjs/lucid/orm'
import type { BelongsTo } from '@adonisjs/lucid/types/relations'

export default class Replay extends ReplaySchema {
  static selfAssignPrimaryKey = true

  @belongsTo(() => Event)
  declare event: BelongsTo<typeof Event>
}
