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
  console.log('[media:upload] client start', {
    apiUrl: API_URL,
    modelType: input.modelType,
    modelId: input.modelId,
    collection: input.collection,
    fileName: input.file.name,
    fileSize: input.file.size,
    fileType: input.file.type,
  })

  try {
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

    const body = (await response.json().catch((parseError) => {
      console.error('[media:upload] client failed to parse response', parseError)
      return {}
    })) as UploadMediaResponse & {
      message?: string
      data?: { message?: string }
    }

    console.log('[media:upload] client response', {
      status: response.status,
      ok: response.ok,
      body,
    })

    if (!response.ok) {
      const message =
        body.data?.message ?? body.message ?? `Upload failed (${response.status})`
      throw new ApiError(response.status, { message })
    }

    console.log('[media:upload] client success', body.data.media)
    return body.data.media
  } catch (error) {
    console.error('[media:upload] client failed', {
      modelType: input.modelType,
      modelId: input.modelId,
      collection: input.collection,
      fileName: input.file.name,
      error: error instanceof Error ? error.message : error,
    })
    throw error
  }
}