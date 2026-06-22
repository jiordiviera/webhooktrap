import { BaseSchema } from '@adonisjs/lucid/schema'

export default class extends BaseSchema {
  protected tableName = 'replays'

  async up() {
    this.schema.createTable(this.tableName, (table) => {
      table.string('id', 30).primary().notNullable()

      table
        .string('event_id', 30)
        .notNullable()
        .references('id')
        .inTable('events')
        .onDelete('CASCADE')

      table.string('target_url', 2048).notNullable()
      table.integer('status_code').unsigned().nullable()
      table.json('response_headers').nullable()
      table.text('response_body').nullable()
      table.integer('duration_ms').unsigned().nullable()
      table.string('error_code', 50).nullable()
      table.text('error_message').nullable()

      table.timestamp('created_at', { useTz: true }).notNullable()

      table.index(['event_id', 'created_at'])
    })
  }

  async down() {
    this.schema.dropTable(this.tableName)
  }
}
