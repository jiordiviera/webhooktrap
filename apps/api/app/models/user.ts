import { withUserMedia } from '#media/has_media'
import { UserSchema } from '#database/schemas/user'
import Inbox from '#models/inbox'
import UserOauthAccount from '#models/user_oauth_account'
import hash from '@adonisjs/core/services/hash'
import { compose } from '@adonisjs/core/helpers'
import { withAuthFinder } from '@adonisjs/auth/mixins/lucid'
import { type AccessToken, DbAccessTokensProvider } from '@adonisjs/auth/access_tokens'
import { hasMany } from '@adonisjs/lucid/orm'
import type { HasMany } from '@adonisjs/lucid/types/relations'

export default class User extends compose(UserSchema, withAuthFinder(hash), withUserMedia) {
  static accessTokens = DbAccessTokensProvider.forModel(User)
  declare currentAccessToken?: AccessToken

  @hasMany(() => Inbox)
  declare inboxes: HasMany<typeof Inbox>

  @hasMany(() => UserOauthAccount)
  declare oauthAccounts: HasMany<typeof UserOauthAccount>

  get initials() {
    const [first, last] = this.fullName ? this.fullName.split(' ') : this.email.split('@')
    if (first && last) {
      return `${first.charAt(0)}${last.charAt(0)}`.toUpperCase()
    }
    return `${first.slice(0, 2)}`.toUpperCase()
  }
}
