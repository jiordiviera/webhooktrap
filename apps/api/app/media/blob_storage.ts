import type { BlobPutResult } from '#media/types'

export interface BlobStorage {
  put(pathname: string, buffer: Buffer, mimeType: string): Promise<BlobPutResult>
  delete(pathname: string): Promise<void>
}
