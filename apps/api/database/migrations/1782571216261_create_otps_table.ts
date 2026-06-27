import { BaseSchema } from '@adonisjs/lucid/schema'

export default class extends BaseSchema {
  protected tableName = 'otps'

  async up() {
    this.schema.createTable(this.tableName, (table) => {
      table.increments('id')
      table.integer('user_id').unsigned().references('id').inTable('users').onDelete('CASCADE')
      table.string('type', 30).notNullable()
      table.string('code', 6).notNullable()
      table.timestamp('expires_at').notNullable()
      table.timestamp('used_at').nullable()

      table.timestamp('created_at').notNullable()
      table.timestamp('updated_at').nullable()

      table.index(['user_id', 'type'])
    })
  }

  async down() {
    this.schema.dropTable(this.tableName)
  }
}
