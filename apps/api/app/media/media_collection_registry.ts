import { MediaCollection } from '#media/media_collection'
import type { MediaCollectionName, MediaModelType } from '#media/types'

const COLLECTIONS: MediaCollection[] = [
  new MediaCollection({
    modelType: 'users',
    name: 'avatar',
    singleFile: true,
    maxBytes: 2 * 1024 * 1024,
    mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
  }),
  new MediaCollection({
    modelType: 'inboxes',
    name: 'icon',
    singleFile: true,
    maxBytes: 1 * 1024 * 1024,
    mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
  }),
  new MediaCollection({
    modelType: 'inboxes',
    name: 'attachments',
    singleFile: false,
    maxBytes: 10 * 1024 * 1024,
    mimeTypes: ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'],
  }),
]

const byKey = new Map(COLLECTIONS.map((collection) => [collection.key, collection]))

export default class MediaCollectionRegistry {
  static resolve(modelType: MediaModelType, collection: MediaCollectionName) {
    const entry = byKey.get(`${modelType}:${collection}`)
    if (!entry) {
      throw new Error(`Unknown media collection "${collection}" for model "${modelType}"`)
    }
    return entry
  }

  static all() {
    return COLLECTIONS
  }
}
