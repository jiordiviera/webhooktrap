import mediaConfig from '#config/media'
import type { BlobStorage } from '#media/blob_storage'
import type { BlobPutResult } from '#media/types'
import env from '#start/env'
import { del, put } from '@vercel/blob'

function getToken() {
  const token = env.get('BLOB_READ_WRITE_TOKEN')
  if (!token) {
    throw new Error('BLOB_READ_WRITE_TOKEN is required for media storage')
  }
  return token
}

function buildPublicUrl(pathname: string) {
  const base = mediaConfig.cdnBaseUrl?.replace(/\/$/, '')
  if (!base) {
    throw new Error('MEDIA_CDN_BASE_URL is required for media storage')
  }

  const key = pathname.replace(/^\//, '')
  return `${base}/${key}`
}

export default class VercelBlobStorage implements BlobStorage {
  async put(pathname: string, buffer: Buffer, mimeType: string): Promise<BlobPutResult> {
    const blob = await put(pathname, buffer, {
      access: 'public',
      token: getToken(),
      contentType: mimeType,
      addRandomSuffix: false,
    })

    return {
      pathname: blob.pathname,
      url: buildPublicUrl(blob.pathname),
    }
  }

  async delete(pathname: string) {
    await del(pathname, { token: getToken() })
  }
}
