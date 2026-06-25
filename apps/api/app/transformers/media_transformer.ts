import { resolvePublicMediaUrl } from '#media/public_media_url'
import type Media from '#models/media'
import { BaseTransformer } from '@adonisjs/core/transformers'

export default class MediaTransformer extends BaseTransformer<Media> {
  toObject() {
    return {
      id: this.resource.id,
      modelType: this.resource.modelType,
      modelId: this.resource.modelId,
      collection: this.resource.collection,
      fileName: this.resource.fileName,
      mimeType: this.resource.mimeType,
      sizeBytes: this.resource.sizeBytes,
      url: resolvePublicMediaUrl(this.resource.blobUrl),
      orderColumn: this.resource.orderColumn,
      createdAt: this.resource.createdAt,
      updatedAt: this.resource.updatedAt,
    }
  }
}
