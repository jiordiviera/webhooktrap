import MediaAccessDeniedException from '#exceptions/media_access_denied_exception'
import type { MediaModelType } from '#media/types'
import Inbox from '#models/inbox'
import Media from '#models/media'
import User from '#models/user'

export default class MediaPolicy {
  static async authorize(userId: number, modelType: MediaModelType, modelId: string) {
    if (modelType === 'users') {
      if (String(userId) !== modelId) {
        throw new MediaAccessDeniedException('You cannot manage media for this user')
      }
      return
    }

    if (modelType === 'inboxes') {
      const inbox = await Inbox.find(modelId)
      if (!inbox || inbox.userId !== userId) {
        throw new MediaAccessDeniedException('You cannot manage media for this inbox')
      }
      return
    }

    throw new MediaAccessDeniedException(`Unsupported model type "${modelType}"`)
  }

  static async authorizeMediaOwner(userId: number, mediaId: string) {
    const media = await Media.find(mediaId)
    if (!media) {
      throw new MediaAccessDeniedException('Media not found')
    }

    await this.authorize(userId, media.modelType, media.modelId)
    return media
  }

  static async resolveUser(userId: number) {
    return User.findOrFail(userId)
  }
}
