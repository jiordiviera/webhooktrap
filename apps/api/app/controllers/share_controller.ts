import ShareToken from '#models/share_token'
import Event from '#models/event'
import { EventDetailTransformer } from '#transformers/event_transformer'
import ReplayTransformer from '#transformers/replay_transformer'
import { shareToken } from '#support/ids'
import type { HttpContext } from '@adonisjs/core/http'

export default class ShareController {
  async store({ auth, params, serialize, response }: HttpContext) {
    if (!auth.user) {
      return response.status(401).send({ data: { message: 'Authentication required' } })
    }

    const event = await Event.find(params.id)
    if (!event) {
      return response.status(404).send({ data: { message: 'Event not found' } })
    }

    await event.load('inbox')
    if (event.inbox.userId !== auth.user.id) {
      return response.status(403).send({ data: { message: 'You cannot share this event' } })
    }

    const token = await ShareToken.create({
      eventId: event.id,
      token: shareToken(),
      expiresAt: null,
    })

    return serialize({
      token: token.token,
    })
  }

  async show({ params, serialize, response }: HttpContext) {
    const share = await ShareToken.query().where('token', params.token).preload('event').first()

    if (!share || share.isExpired) {
      return response.status(404).send({ data: { message: 'Share link not found or expired' } })
    }

    const event = share.event
    await event.load('replays', (query) => {
      query.orderBy('created_at', 'desc')
    })

    return serialize({
      event: EventDetailTransformer.transform(event),
      replays: ReplayTransformer.transform(event.replays),
    })
  }
}
