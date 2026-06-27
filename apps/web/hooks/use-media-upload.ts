'use client'

import { useCallback, useState } from 'react'
import { uploadMedia, type MediaRecord } from '@/lib/media'

type UploadInput = {
  modelType: 'users' | 'inboxes'
  modelId: string
  collection: 'avatar' | 'icon' | 'attachments'
  file: File
}

export function useMediaUpload() {
  const [isUploading, setIsUploading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const upload = useCallback(
    async (input: UploadInput): Promise<MediaRecord | null> => {
      setIsUploading(true)
      setError(null)

      try {
        return await uploadMedia({
          ...input,
        })
      } catch (uploadError) {
        setError(uploadError instanceof Error ? uploadError.message : 'Upload failed')
        return null
      } finally {
        setIsUploading(false)
      }
    },
    []
  )

  return { upload, isUploading, error }
}