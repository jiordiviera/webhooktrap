import type { BlobStorage } from '#media/blob_storage'
import DriveStorage from '#media/drive_storage'
import MemoryBlobStorage from '#media/memory_blob_storage'
import type { BlobPutResult } from '#media/types'
import env from '#start/env'

function createBlobStorage(): BlobStorage {
  if (env.get('NODE_ENV') === 'test') {
    return new MemoryBlobStorage()
  }

  return new DriveStorage()
}

export default class BlobDisk {
  constructor(private readonly storage: BlobStorage = createBlobStorage()) {}

  put(pathname: string, buffer: Buffer, mimeType: string): Promise<BlobPutResult> {
    return this.storage.put(pathname, buffer, mimeType)
  }

  delete(pathname: string): Promise<void> {
    return this.storage.delete(pathname)
  }
}
