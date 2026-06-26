import mediaConfig from '#config/media'
import type { BlobStorage } from '#media/blob_storage'
import type { BlobPutResult } from '#media/types'
import drive from '@adonisjs/drive/services/main'

function buildPublicUrl(pathname: string) {
  const base = mediaConfig.cdnBaseUrl
  const bucket = mediaConfig.bucket

  if (!base) {
    throw new Error('MEDIA_CDN_BASE_URL is required for media storage')
  }

  const key = pathname.replace(/^\//, '')
  return `${base}/${bucket}/${key}`
}

export default class DriveStorage implements BlobStorage {
  async put(pathname: string, buffer: Buffer, mimeType: string): Promise<BlobPutResult> {
    const disk = drive.use(mediaConfig.disk)
    await disk.put(pathname, buffer, { contentType: mimeType })

    return {
      pathname,
      url: buildPublicUrl(pathname),
    }
  }

  async delete(pathname: string) {
    await drive.use(mediaConfig.disk).delete(pathname)
  }
}