import { BaseModel, column } from '@adonisjs/lucid/orm'
import { TwoFactorSecret } from '@nulix/adonis-2fa/types'
import encryption from '@adonisjs/core/services/encryption'
import { DateTime } from 'luxon'

export class UserSchema extends BaseModel {
  static $columns = [
    'createdAt',
    'email',
    'emailVerifiedAt',
    'fullName',
    'id',
    'isTwoFactorEnabled',
    'password',
    'twoFactorRecoveryCodes',
    'twoFactorSecret',
    'updatedAt',
  ] as const
  $columns = UserSchema.$columns
  @column.dateTime({ autoCreate: true })
  declare createdAt: DateTime
  @column()
  declare email: string
  @column.dateTime()
  declare emailVerifiedAt: DateTime | null
  @column()
  declare fullName: string | null
  @column({ isPrimary: true })
  declare id: number
  @column()
  declare isTwoFactorEnabled: boolean | null
  @column({ serializeAs: null })
  declare password: string
  @column({
    serializeAs: null,
    consume: (value: string) => (value ? encryption.decrypt(value) : []),
    prepare: (value: string[]) => encryption.encrypt(value),
  })
  declare twoFactorRecoveryCodes: string[]
  @column({
    serializeAs: null,
    consume: (value: string) => (value ? encryption.decrypt(value) : null),
    prepare: (value: string) => encryption.encrypt(value),
  })
  declare twoFactorSecret: TwoFactorSecret | null
  @column.dateTime({ autoCreate: true, autoUpdate: true })
  declare updatedAt: DateTime | null
}
