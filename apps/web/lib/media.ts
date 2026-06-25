import { API_URL, ApiError } from '@/lib/api'

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

  const response = await fetch(`${API_URL}/api/v1/media`, {
    method: 'POST',
    headers: {
      Authorization: `Bearer ${input.token}`,
      Accept: 'application/json',
    },
    body: formData,
  })

  const body = (await response.json().catch(() => ({}))) as UploadMediaResponse & {
    message?: string
  }

  if (!response.ok) {
    throw new ApiError(response.status, { message: body.message ?? 'Upload failed' })
  }

  return body.data.media
}