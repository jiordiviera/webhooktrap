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
      .json({ name: 'Stripe checkout' })

    response.assertStatus(200)
    response.assertBodyContains({
      data: {
        inbox: {
          name: 'Stripe checkout',
        },
      },
    })

    const inboxIdValue = response.body().data.inbox.id as string
    const inbox = await Inbox.findOrFail(inboxIdValue)

    assert.equal(inbox.userId, user.id)
    assert.equal(inbox.name, 'Stripe checkout')
    assert.isNull(inbox.expiresAt)
  })

  test('deletes inbox when owner is authenticated', async ({ client, assert }) => {
    const user = await User.create({
      fullName: 'Dev User',
      email: 'delete@hookscope.test',
      password: 'password123',
    })

    const token = await User.accessTokens.create(user, ['*'], {
      name: 'test-token',
    })

    const inbox = await Inbox.create({
      id: 'deleteinbox1',
      userId: user.id,
      name: 'To delete',
      expiresAt: null,
    })

    const response = await client
      .delete(`/api/v1/inboxes/${inbox.id}`)
      .header('authorization', `Bearer ${token.value!.release()}`)

    response.assertStatus(200)
    response.assertBodyContains({
      data: {
        deleted: true,
      },
    })

    const deleted = await Inbox.find(inbox.id)
    assert.isNull(deleted)
  })

  test('rejects inbox deletion for another user', async ({ client }) => {
    const owner = await User.create({
      fullName: 'Owner',
      email: 'inbox-owner@hookscope.test',
      password: 'password123',
    })

    const other = await User.create({
      fullName: 'Other',
      email: 'inbox-other@hookscope.test',
      password: 'password123',
    })

    const token = await User.accessTokens.create(other, ['*'], {
      name: 'test-token',
    })

    const inbox = await Inbox.create({
      id: 'notmineinbx1',
      userId: owner.id,
      name: 'Protected inbox',
      expiresAt: null,
    })

    const response = await client
      .delete(`/api/v1/inboxes/${inbox.id}`)
      .header('authorization', `Bearer ${token.value!.release()}`)

    response.assertStatus(403)
  })
})
