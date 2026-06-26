import { isAxiosError } from 'axios'
import { apiClient, ApiError } from '@/lib/api'

export type MediaRecord = {
  id: string
  modelType: 'users' | 'inboxes'
  modelId: string
  collection: 'avatar' | 'icon' | 'attachments'
  fileName: string
  mimeType: string
  sizeBytes: number
  url: string
  orderColumn: number
  createdAt: string
  updatedAt: string | null
}

type UploadMediaInput = {
  token: string
  modelType: 'users' | 'inboxes'
  modelId: string
  collection: 'avatar' | 'icon' | 'attachments'
  file: File
}

type UploadMediaResponse = {
  data: {
    media: MediaRecord
  }
}

export async function uploadMedia(input: UploadMediaInput): Promise<MediaRecord> {
  const formData = new FormData()
  formData.append('file', input.file)
  formData.append('model_type', input.modelType)
  formData.append('model_id', input.modelId)
  formData.append('collection', input.collection)

  try {
    const response = await apiClient.post<UploadMediaResponse>('/api/v1/media', formData, {
      headers: {
        Authorization: `Bearer ${input.token}`,
      },
    })

    return response.data.data.media
  } catch (error) {
    if (isAxiosError(error) && error.response) {
      const body = (error.response.data ?? {}) as UploadMediaResponse & {
        message?: string
        data?: { message?: string }
      }
      const message = body.data?.message ?? body.message ?? `Upload failed (${error.response.status})`
      throw new ApiError(error.response.status, { message })
    }

    throw error
  }
}