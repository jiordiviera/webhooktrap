import MediaService from '#media/media_service'
import type { MediaCollectionName } from '#media/types'
import Media from '#models/media'
import { hasMany } from '@adonisjs/lucid/orm'
import type { HasMany } from '@adonisjs/lucid/types/relations'
import type { NormalizeConstructor } from '@adonisjs/core/types/helpers'
import type { BaseModel } from '@adonisjs/lucid/orm'

function withHasMedia<Model extends NormalizeConstructor<typeof BaseModel>>(
  superclass: Model,
  modelType: 'users' | 'inboxes'
) {
  class HasMediaModel extends superclass {
    static mediaModelType = modelType

    @hasMany(() => Media, {
      foreignKey: 'modelId',
      onQuery: (query) => {
        query.where('model_type', modelType)
      },
    })
    declare media: HasMany<typeof Media>

    getMediaModelId() {
      return String((this as unknown as { id: string | number }).id)
    }

    getMedia(collection?: MediaCollectionName) {
      const query = Media.query()
        .where('model_type', modelType)
        .where('model_id', this.getMediaModelId())

      if (collection) {
        query.where('collection', collection)
      }

      return query.orderBy('order_column', 'asc').orderBy('created_at', 'asc')
    }

    async getFirstMedia(collection: MediaCollectionName) {
      return MediaService.getFirst(modelType, this.getMediaModelId(), collection)
    }

    async getMediaUrl(collection: MediaCollectionName) {
      return MediaService.getMediaUrl(modelType, this.getMediaModelId(), collection)
    }
  }

  return HasMediaModel
}

export function withUserMedia<Model extends NormalizeConstructor<typeof BaseModel>>(
  superclass: Model
) {
  return withHasMedia(superclass, 'users')
}

export function withInboxMedia<Model extends NormalizeConstructor<typeof BaseModel>>(
  superclass: Model
) {
  return withHasMedia(superclass, 'inboxes')
}
