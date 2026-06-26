import { BaseSchema } from '@adonisjs/lucid/schema'

export default class extends BaseSchema {
  protected tableName = 'media'

  async up() {
    this.schema.createTable(this.tableName, (table) => {
      table.uuid('id').primary().notNullable()

      table.string('model_type', 64).notNullable()
      table.string('model_id', 64).notNullable()
      table.string('collection', 64).notNullable()

      table.string('disk', 32).notNullable().defaultTo('r2')
      table.string('file_name', 255).notNullable()
      table.string('mime_type', 128).notNullable()
      table.bigInteger('size_bytes').notNullable()

      table.string('blob_url', 2048).notNullable()
      table.string('blob_pathname', 512).notNullable()

      table.json('custom_props').notNullable().defaultTo('{}')
      table.json('conversions').notNullable().defaultTo('{}')
      table.integer('order_column').notNullable().defaultTo(0)

      table.timestamp('created_at', { useTz: true }).notNullable()
      table.timestamp('updated_at', { useTz: true }).nullable()

      table.index(['model_type', 'model_id', 'collection'])
      table.index(['blob_pathname'])
    })
  }

  async down() {
    this.schema.dropTable(this.tableName)
  }
}
