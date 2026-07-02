import Event from '#models/event'
import Inbox from '#models/inbox'
import User from '#models/user'
import { eventId, inboxId } from '#support/ids'
import testUtils from '@adonisjs/core/services/test_utils'
import { test } from '@japa/runner'
import { DateTime } from 'luxon'

test.group('Replay API', (group) => {
  group.each.setup(async () => {
    await testUtils.db().migrate()
  })

  async function seedEvent(email: string) {
    const user = await User.create({
      fullName: 'Dev User',
      email,
      password: 'password123',
    })

    const token = await User.accessTokens.create(user, ['*'], { name: 'test-token' })

    const inbox = await Inbox.create({
      id: inboxId(),
      userId: user.id,
      name: 'Test inbox',
      expiresAt: null,
    })

    const event = await Event.create({
      id: eventId(),
      inboxId: inbox.id,
      method: 'POST',
      path: '/',
      query: {},
      headers: { 'content-type': 'application/json' },
      bodyText: '{"hello":"world"}',
      bodyJson: { hello: 'world' },
      contentType: 'application/json',
      ip: '203.0.113.1',
      sizeBytes: 18,
      receivedAt: DateTime.now(),
    })

    return { token: token.value!.release(), event }
  }

  test('blocks replay to a private/internal target', async ({ client, assert }) => {
    const { token, event } = await seedEvent('replay-private@hookscope.test')

    const response = await client
      .post(`/api/v1/events/${event.id}/replay`)
      .header('authorization', `Bearer ${token}`)
      .json({ target_url: 'http://169.254.169.254/latest/meta-data/' })

    response.assertStatus(200)
    response.assertBodyContains({
      data: {
        replay: {
          statusCode: null,
          errorCode: 'BLOCKED_TARGET',
        },
      },
    })

    assert.isNull(response.body().data.replay.responseBody)
  })

  test('blocks replay to loopback', async ({ client }) => {
    const { token, event } = await seedEvent('replay-loopback@hookscope.test')

    const response = await client
      .post(`/api/v1/events/${event.id}/replay`)
      .header('authorization', `Bearer ${token}`)
      .json({ target_url: 'http://127.0.0.1:3333/webhook' })

    response.assertStatus(200)
    response.assertBodyContains({
      data: { replay: { errorCode: 'BLOCKED_TARGET' } },
    })
  })
})
