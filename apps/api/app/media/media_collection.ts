import type { MediaCollectionName, MediaModelType } from '#media/types'

export type MediaCollectionConfig = {
  modelType: MediaModelType
  name: MediaCollectionName
  singleFile: boolean
  maxBytes: number
  mimeTypes: string[]
}

export class MediaCollection {
  constructor(readonly config: MediaCollectionConfig) {}

  get key() {
    return `${this.config.modelType}:${this.config.name}`
  }

  acceptsMime(mimeType: string) {
    return this.config.mimeTypes.includes(mimeType)
  }

  acceptsSize(sizeBytes: number) {
    return sizeBytes <= this.config.maxBytes
  }
}
