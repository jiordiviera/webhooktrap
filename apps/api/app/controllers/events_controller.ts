import EventService from '#services/event_service'
import ReplayService from '#services/replay_service'
import { EventDetailTransformer } from '#transformers/event_transformer'
import ReplayTransformer from '#transformers/replay_transformer'
import type { HttpContext } from '@adonisjs/core/http'
import vine from '@vinejs/vine'

const replayValidator = vine.compile(
  vine.object({
    target_url: vine.string().url().maxLength(2048).optional(),
  })
)

export default class EventsController {
  async show({ auth, params, serialize, response }: HttpContext) {
    const event = await EventService.getForViewer(params.id, auth.user?.id)

    if (!event) {
      return response.status(404).send({ data: { message: 'Event not found' } })
    }

    return serialize({
      event: EventDetailTransformer.transform(event),
    })
  }

  async replay({ auth, request, params, response, serialize }: HttpContext) {
    const payload = await request.validateUsing(replayValidator)
    const event = await EventService.getForViewer(params.id, auth.user?.id)

    if (!event) {
      return response.status(404).send({ data: { message: 'Event not found' } })
    }

    await event.load('inbox')

    const targetUrl = payload.target_url ?? event.inbox.defaultReplayUrl
    if (!targetUrl) {
      return response.status(422).send({
        data: { message: 'Replay destination URL is required' },
      })
    }

    const replay = await ReplayService.replay(params.id, auth.user?.id, targetUrl)

    return serialize({
      replay: ReplayTransformer.transform(replay),
    })
  }

  async replays({ auth, params, serialize }: HttpContext) {
    const replays = await ReplayService.listForEvent(params.id, auth.user?.id)

    return serialize({
      replays: ReplayTransformer.transform(replays),
    })
  }
}