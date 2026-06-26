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
    console.log('[media:drive] put start', {
      disk: mediaConfig.disk,
      pathname,
      mimeType,
      sizeBytes: buffer.byteLength,
      cdnBaseUrl: mediaConfig.cdnBaseUrl,
    })

    try {
      const disk = drive.use(mediaConfig.disk)
      await disk.put(pathname, buffer, { contentType: mimeType })

      const result = {
        pathname,
        url: buildPublicUrl(pathname),
      }

      console.log('[media:drive] put success', result)
      return result
    } catch (error) {
      console.error('[media:drive] put failed', {
        disk: mediaConfig.disk,
        pathname,
        mimeType,
        error: error instanceof Error ? error.message : error,
        stack: error instanceof Error ? error.stack : undefined,
      })
      throw error
    }
  }

  async delete(pathname: string) {
    console.log('[media:drive] delete start', { disk: mediaConfig.disk, pathname })

    try {
      await drive.use(mediaConfig.disk).delete(pathname)
      console.log('[media:drive] delete success', { pathname })
    } catch (error) {
      console.error('[media:drive] delete failed', {
        pathname,
        error: error instanceof Error ? error.message : error,
        stack: error instanceof Error ? error.stack : undefined,
      })
      throw error
    }
  }
}
