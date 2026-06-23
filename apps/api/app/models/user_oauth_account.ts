import { UserOauthAccountSchema } from '#database/schema'
import User from '#models/user'
import { belongsTo } from '@adonisjs/lucid/orm'
import type { BelongsTo } from '@adonisjs/lucid/types/relations'

export default class UserOauthAccount extends UserOauthAccountSchema {
  static table = 'user_oauth_accounts'

  @belongsTo(() => User)
  declare user: BelongsTo<typeof User>
}