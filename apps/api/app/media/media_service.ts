import { randomUUID } from 'node:crypto'
import { extname } from 'node:path'
import mediaConfig from '#config/media'
import BlobDisk from '#media/blob_disk'
import MediaCollectionRegistry from '#media/media_collection_registry'
import { detectMimeType } from '#media/mime_validator'
import type {
  AttachFromMultipartInput,
  AttachFromUrlInput,
  MediaCollectionName,
  MediaModelType,
} from '#media/types'
import Media from '#models/media'

export default class MediaService {
  static #disk = new BlobDisk()

  static async list(modelType: MediaModelType, modelId: string, collection?: MediaCollectionName) {
    const query = Media.query()
      .where('model_type', modelType)
      .where('model_id', modelId)
      .orderBy('order_column', 'asc')
      .orderBy('created_at', 'asc')

    if (collection) {
      query.where('collection', collection)
    }

    return query
  }

  static async getFirst(
    modelType: MediaModelType,
    modelId: string,
    collection: MediaCollectionName
  ) {
    return Media.query()
      .where('model_type', modelType)
      .where('model_id', modelId)
      .where('collection', collection)
      .orderBy('order_column', 'asc')
      .orderBy('created_at', 'asc')
      .first()
  }

  static async getMediaUrl(
    modelType: MediaModelType,
    modelId: string,
    collection: MediaCollectionName
  ) {
    const media = await this.getFirst(modelType, modelId, collection)
    return media?.blobUrl
  }

  static async attachFromMultipart(input: AttachFromMultipartInput) {
    const collection = MediaCollectionRegistry.resolve(input.modelType, input.collection)

    if (!collection.acceptsMime(input.mimeType)) {
      throw new Error(`Mime type "${input.mimeType}" is not allowed for this collection`)
    }

    if (!collection.acceptsSize(input.buffer.byteLength)) {
      throw new Error(`File exceeds maximum size for collection "${input.collection}"`)
    }

    const detectedMime = detectMimeType(input.buffer)
    if (!detectedMime || detectedMime !== input.mimeType) {
      throw new Error(`File content does not match expected type "${input.mimeType}"`)
    }

    if (collection.config.singleFile) {
      await this.deleteForCollection(input.modelType, input.modelId, input.collection)
    }

    const extension = extname(input.fileName) || extensionFromMime(input.mimeType)
    const pathname = `${input.modelType}/${input.modelId}/${input.collection}/${randomUUID()}${extension}`

    const stored = await this.#disk.put(pathname, input.buffer, input.mimeType)

    return Media.create({
      id: randomUUID(),
      modelType: input.modelType,
      modelId: input.modelId,
      collection: input.collection,
      disk: mediaConfig.disk,
      fileName: input.fileName,
      mimeType: input.mimeType,
      sizeBytes: input.buffer.byteLength,
      blobUrl: stored.url,
      blobPathname: stored.pathname,
      customProps: {},
      conversions: {},
      orderColumn: 0,
    })
  }

  static async attachFromUrl(input: AttachFromUrlInput) {
    const response = await fetch(input.url)
    if (!response.ok) {
      throw new Error(`Failed to fetch media from URL (${response.status})`)
    }

    const buffer = Buffer.from(await response.arrayBuffer())
    const mimeType = response.headers.get('content-type')?.split(';')[0]?.trim() ?? 'image/jpeg'
    const fileName = input.url.split('/').pop() ?? 'remote-file'

    return this.attachFromMultipart({
      modelType: input.modelType,
      modelId: input.modelId,
      collection: input.collection,
      fileName,
      mimeType,
      buffer,
    })
  }

  static async delete(media: Media) {
    await this.#disk.delete(media.blobPathname)
    await media.delete()
  }

  static async deleteForCollection(
    modelType: MediaModelType,
    modelId: string,
    collection: MediaCollectionName
  ) {
    const existing = await this.list(modelType, modelId, collection)
    for (const item of existing) {
      await this.delete(item)
    }
  }
}

function extensionFromMime(mimeType: string) {
  switch (mimeType) {
    case 'image/jpeg':
      return '.jpg'
    case 'image/png':
      return '.png'
    case 'image/webp':
      return '.webp'
    case 'application/pdf':
      return '.pdf'
    default:
      return ''
  }
}
