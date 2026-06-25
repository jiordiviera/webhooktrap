import type { MediaCollectionName, MediaModelType } from '#media/types'
import { BaseModel, column } from '@adonisjs/lucid/orm'
import { DateTime } from 'luxon'

export default class Media extends BaseModel {
  static table = 'media'

  @column({ isPrimary: true })
  declare id: string

  @column()
  declare modelType: MediaModelType

  @column()
  declare modelId: string

  @column()
  declare collection: MediaCollectionName

  @column()
  declare disk: string

  @column()
  declare fileName: string

  @column()
  declare mimeType: string

  @column()
  declare sizeBytes: number

  @column()
  declare blobUrl: string

  @column()
  declare blobPathname: string

  @column()
  declare customProps: Record<string, unknown>

  @column()
  declare conversions: Record<string, unknown>

  @column()
  declare orderColumn: number

  @column.dateTime({ autoCreate: true })
  declare createdAt: DateTime

  @column.dateTime({ autoCreate: true, autoUpdate: true })
  declare updatedAt: DateTime | null
}
