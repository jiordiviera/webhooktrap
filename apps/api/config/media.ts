import env from '#start/env'

const mediaConfig = {
  disk: 'vercel_blob' as const,
  cdnBaseUrl: env.get('MEDIA_CDN_BASE_URL'),
  blobReadWriteToken: env.get('BLOB_READ_WRITE_TOKEN'),
  maxFileSizeMb: env.get('MEDIA_MAX_FILE_SIZE_MB', 10),
}

export default mediaConfig
