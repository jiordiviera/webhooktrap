import { BaseSchema } from '@adonisjs/lucid/schema'

export default class extends BaseSchema {
  protected tableName = 'share_tokens'

  async up() {
    this.schema.createTable(this.tableName, (table) => {
      table.increments('id').primary()

      table
        .string('event_id', 30)
        .notNullable()
        .references('id')
        .inTable('events')
        .onDelete('CASCADE')

      table.string('token', 32).notNullable().unique()
      table.timestamp('expires_at', { useTz: true }).nullable()

      table.timestamp('created_at', { useTz: true }).notNullable()

      table.index(['event_id'])
    })
  }

  async down() {
    this.schema.dropTable(this.tableName)
  }
}
