import env from '#start/env'

const mediaConfig = {
  disk: env.get('DRIVE_DISK'),
  cdnBaseUrl: env.get('MEDIA_CDN_BASE_URL'),
  bucket: env.get('R2_BUCKET'),
  maxFileSizeMb: env.get('MEDIA_MAX_FILE_SIZE_MB', 10),
}

export default mediaConfig
