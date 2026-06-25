import { detectMimeType } from '#media/mime_validator'
import MediaPolicy from '#media/media_policy'
import MediaService from '#media/media_service'
import type { MediaCollectionName, MediaModelType } from '#media/types'
import MediaTransformer from '#transformers/media_transformer'
import { attachFromUrlValidator } from '#validators/media_validator'
import type { HttpContext } from '@adonisjs/core/http'
import { readFile } from 'node:fs/promises'

export default class MediaController {
  async index({ auth, request, serialize }: HttpContext) {
    const user = auth.getUserOrFail()
    const modelType = request.input('model_type') as MediaModelType
    const modelId = request.input('model_id') as string
    const collection = request.input('collection') as MediaCollectionName | undefined

    await MediaPolicy.authorize(user.id, modelType, modelId)

    const media = await MediaService.list(modelType, modelId, collection)

    return serialize({
      media: media.map((item) => MediaTransformer.transform(item)),
    })
  }

  async show({ auth, params, serialize }: HttpContext) {
    const user = auth.getUserOrFail()
    const media = await MediaPolicy.authorizeMediaOwner(user.id, params.id)

    return serialize({
      media: MediaTransformer.transform(media),
    })
  }

  async store({ auth, request, response, serialize }: HttpContext) {
    const user = auth.getUserOrFail()
    const modelType = request.input('model_type') as MediaModelType
    const modelId = request.input('model_id') as string
    const collection = request.input('collection') as MediaCollectionName

    await MediaPolicy.authorize(user.id, modelType, modelId)

    const uploaded = request.file('file', {
      size: '20mb',
      extnames: ['jpg', 'jpeg', 'png', 'webp', 'pdf'],
    })

    if (!uploaded) {
      return response.status(422).send({
        data: { message: 'File is required' },
      })
    }

    if (!uploaded.isValid) {
      return response.status(422).send({
        data: {
          message: uploaded.errors[0]?.message ?? 'Invalid file upload',
        },
      })
    }

    const buffer = await readFile(uploaded.tmpPath!)
    const mimeType =
      detectMimeType(buffer) ??
      (uploaded.type && uploaded.type !== 'application/octet-stream' ? uploaded.type : null)

    if (!mimeType) {
      return response.status(422).send({
        data: { message: 'Unsupported or undetectable file type' },
      })
    }

    const media = await MediaService.attachFromMultipart({
      modelType,
      modelId,
      collection,
      fileName: uploaded.clientName,
      mimeType,
      buffer,
    })

    return serialize({
      media: MediaTransformer.transform(media),
    })
  }

  async storeFromUrl({ auth, request, serialize }: HttpContext) {
    const user = auth.getUserOrFail()
    const payload = await request.validateUsing(attachFromUrlValidator)

    await MediaPolicy.authorize(user.id, payload.model_type, payload.model_id)

    const media = await MediaService.attachFromUrl({
      modelType: payload.model_type,
      modelId: payload.model_id,
      collection: payload.collection,
      url: payload.url,
    })

    return serialize({
      media: MediaTransformer.transform(media),
    })
  }

  async destroy({ auth, params, serialize }: HttpContext) {
    const user = auth.getUserOrFail()
    const media = await MediaPolicy.authorizeMediaOwner(user.id, params.id)

    await MediaService.delete(media)

    return serialize({
      deleted: true,
    })
  }
}
