export type MediaModelType = 'users' | 'inboxes'

export type MediaCollectionName = 'avatar' | 'icon' | 'attachments'

export type AttachFromMultipartInput = {
  modelType: MediaModelType
  modelId: string
  collection: MediaCollectionName
  fileName: string
  mimeType: string
  buffer: Buffer
}

export type AttachFromUrlInput = {
  modelType: MediaModelType
  modelId: string
  collection: MediaCollectionName
  url: string
}

export type BlobPutResult = {
  url: string
  pathname: string
}
