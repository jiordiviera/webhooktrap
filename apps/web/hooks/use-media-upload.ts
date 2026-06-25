'use client'

import { useCallback, useState } from 'react'
import { uploadMedia, type MediaRecord } from '@/lib/media'
import { useAuth } from '@/contexts/auth-context'

type UploadInput = {
  modelType: 'users' | 'inboxes'
  modelId: string
  collection: 'avatar' | 'icon' | 'attachments'
  file: File
}

export function useMediaUpload() {
  const { token } = useAuth()
  const [isUploading, setIsUploading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const upload = useCallback(
    async (input: UploadInput): Promise<MediaRecord | null> => {
      if (!token) {
        setError('You must be signed in to upload media')
        return null
      }

      setIsUploading(true)
      setError(null)

      try {
        return await uploadMedia({
          token,
          ...input,
        })
      } catch (uploadError) {
        setError(uploadError instanceof Error ? uploadError.message : 'Upload failed')
        return null
      } finally {
        setIsUploading(false)
      }
    },
    [token]
  )

  return { upload, isUploading, error }
}