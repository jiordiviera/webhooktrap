import { BaseSchema } from '@adonisjs/lucid/schema'

export default class extends BaseSchema {
  protected tableName = 'users'

  async up() {
    this.schema.alterTable(this.tableName, (table) => {
      table.boolean('is_two_factor_enabled').defaultTo(false)
      table.text('two_factor_secret').nullable()
      table.text('two_factor_recovery_codes').nullable()
    })
  }

  async down() {
    this.schema.alterTable(this.tableName, (table) => {
      table.dropColumns('is_two_factor_enabled', 'two_factor_secret', 'two_factor_recovery_codes')
    })
  }
}
