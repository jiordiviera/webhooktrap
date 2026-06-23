import Inbox from '#models/inbox'
import User from '#models/user'
import testUtils from '@adonisjs/core/services/test_utils'
import { test } from '@japa/runner'

test.group('Inboxes API', (group) => {
  group.each.setup(async () => {
    await testUtils.db().migrate()
  })

  test('lists inboxes for authenticated user', async ({ client, assert }) => {
    const user = await User.create({
      fullName: 'Dev User',
      email: 'dev@hookscope.test',
      password: 'password123',
    })

    const token = await User.accessTokens.create(user, ['*'], {
      name: 'test-token',
    })

    const inbox = await Inbox.create({
      id: 'abcdefghij12',
      userId: user.id,
      name: 'Stripe Integration',
      expiresAt: null,
    })

    const response = await client
      .get('/api/v1/inboxes')
      .header('authorization', `Bearer ${token.value!.release()}`)

    response.assertStatus(200)
    response.assertBodyContains({
      data: {
        inboxes: [
          {
            id: inbox.id,
            name: 'Stripe Integration',
            ingestUrl: `/i/${inbox.id}`,
            eventsCount: 0,
          },
        ],
      },
    })

    assert.lengthOf(response.body().data.inboxes, 1)
  })

  test('rejects inbox listing without auth', async ({ client }) => {
    const response = await client.get('/api/v1/inboxes')
    response.assertStatus(401)
  })

  test('creates persistent inbox when authenticated', async ({ client, assert }) => {
    const user = await User.create({
      fullName: 'Dev User',
      email: 'signed@hookscope.test',
      password: 'password123',
    })

    const token = await User.accessTokens.create(user, ['*'], {
      name: 'test-token',
    })

    const response = await client
      .post('/api/v1/inboxes')
      .header('authorization', `Bearer ${token.value!.release()}`)

    response.assertStatus(200)

    const inboxIdValue = response.body().data.inbox.id as string
    const inbox = await Inbox.findOrFail(inboxIdValue)

    assert.equal(inbox.userId, user.id)
    assert.isNull(inbox.expiresAt)
  })
})