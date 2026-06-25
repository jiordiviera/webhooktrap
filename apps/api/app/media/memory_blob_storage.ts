import mediaConfig from '#config/media'
import type { BlobStorage } from '#media/blob_storage'
import type { BlobPutResult } from '#media/types'

function buildPublicUrl(pathname: string) {
  const base = mediaConfig.cdnBaseUrl?.replace(/\/$/, '')
  if (!base) {
    throw new Error('MEDIA_CDN_BASE_URL is required for media storage')
  }

  const key = pathname.replace(/^\//, '')
  return `${base}/${key}`
}

export default class MemoryBlobStorage implements BlobStorage {
  private readonly files = new Map<string, Buffer>()

  async put(pathname: string, buffer: Buffer, _mimeType: string): Promise<BlobPutResult> {
    this.files.set(pathname, buffer)

    return {
      pathname,
      url: buildPublicUrl(pathname),
    }
  }

  async delete(pathname: string) {
    this.files.delete(pathname)
  }
}
