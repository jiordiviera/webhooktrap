import { BaseSchema } from '@adonisjs/lucid/schema'

export default class extends BaseSchema {
  protected tableName = 'events'

  async up() {
    this.schema.createTable(this.tableName, (table) => {
      table.string('id', 30).primary().notNullable()

      table
        .string('inbox_id', 16)
        .notNullable()
        .references('id')
        .inTable('inboxes')
        .onDelete('CASCADE')

      table.string('method', 10).notNullable()
      table.string('path', 2048).notNullable()
      table.json('query').notNullable().defaultTo('{}')
      table.json('headers').notNullable().defaultTo('{}')
      table.text('body_text').nullable()
      table.json('body_json').nullable()
      table.string('content_type', 255).nullable()
      table.string('ip', 45).nullable()
      table.integer('size_bytes').unsigned().notNullable().defaultTo(0)

      table.timestamp('received_at', { useTz: true }).notNullable()
      table.timestamp('created_at', { useTz: true }).notNullable()

      table.index(['inbox_id', 'received_at'])
    })
  }

  async down() {
    this.schema.dropTable(this.tableName)
  }
}
