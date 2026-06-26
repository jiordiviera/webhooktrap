import Inbox from '#models/inbox'
import Event from '#models/event'
import testUtils from '@adonisjs/core/services/test_utils'
import { test } from '@japa/runner'
import { DateTime } from 'luxon'
import { webUrl } from '#config/app'

test.group('Ingest', (group) => {
  group.each.setup(async () => {
    await testUtils.db().migrate()
    await Event.query().delete()
    await Inbox.query().delete()
  })

  test('creates anonymous inbox', async ({ client, assert }) => {
    const response = await client.post('/api/v1/inboxes').json({ name: 'Quick test inbox' })

    response.assertStatus(200)
    response.assertBodyContains({
      data: {
        inbox: {
          name: 'Quick test inbox',
        },
      },
    })

    const body = response.body()
    const inboxIdValue = body.data.inbox.id as string
    assert.match(inboxIdValue, /^[A-Za-z0-9]{12}$/)
    response.assertBodyContains({
      data: {
        inbox: {
          ingestUrl: new URL(`/hooks/${inboxIdValue}`, webUrl).toString(),
        },
      },
    })
  })

  test('stores webhook event and always returns 200', async ({ client, assert }) => {
    const created = await client.post('/api/v1/inboxes').json({ name: 'Test inbox' })
    const inboxIdValue = created.body().data.inbox.id

    const response = await client
      .post(`/i/${inboxIdValue}`)
      .header('stripe-signature', 't=123,v1=abc')
      .header('authorization', 'Bearer secret')
      .json({ type: 'checkout.session.completed' })

    response.assertStatus(200)
    response.assertBody({ received: true })

    const event = await Event.query().where('inboxId', inboxIdValue).firstOrFail()
    assert.equal(event.method, 'POST')
    assert.deepEqual(event.bodyJson, { type: 'checkout.session.completed' })
    assert.equal(event.headers.authorization ?? event.headers.Authorization, '[REDACTED]')
    assert.equal(event.headers['stripe-signature'], 't=123,v1=abc')
  })

  test('returns 200 for unknown inbox without storing', async ({ client, assert }) => {
    const response = await client.post('/i/abcdefghij12').json({ ok: true })

    response.assertStatus(200)
    response.assertBody({ received: true })

    const count = await Event.query().count('* as total')
    assert.equal(Number(count[0].$extras.total), 0)
  })

  test('returns 200 for expired inbox without storing', async ({ client, assert }) => {
    const created = await client.post('/api/v1/inboxes').json({ name: 'Test inbox' })
    const inboxIdValue = created.body().data.inbox.id

    const inbox = await Inbox.findOrFail(inboxIdValue)
    inbox.expiresAt = DateTime.now().minus({ hours: 1 })
    await inbox.save()

    const response = await client.post(`/i/${inboxIdValue}`).json({ ok: true })

    response.assertStatus(200)
    response.assertBody({ received: true })

    const count = await Event.query().where('inboxId', inboxIdValue).count('* as total')
    assert.equal(Number(count[0].$extras.total), 0)
  })

  test('filters inbox events with nested filters object', async ({ client, assert }) => {
    const created = await client.post('/api/v1/inboxes').json({ name: 'Test inbox' })
    const inboxIdValue = created.body().data.inbox.id

    await client.post(`/i/${inboxIdValue}`).json({ source: 'post' })
    await client.get(`/i/${inboxIdValue}`).qs({ probe: '1' })

    const response = await client
      .get(`/api/v1/inboxes/${inboxIdValue}/events`)
      .qs({ filters: { method: 'POST' } })

    response.assertStatus(200)

    const body = response.body()
    assert.lengthOf(body.data.events, 1)
    assert.equal(body.data.events[0].method, 'POST')
  })

  test('accepts GET requests with query string', async ({ client, assert }) => {
    const created = await client.post('/api/v1/inboxes').json({ name: 'Test inbox' })
    const inboxIdValue = created.body().data.inbox.id

    const response = await client.get(`/i/${inboxIdValue}`).qs({ challenge: 'abc123' })

    response.assertStatus(200)
    response.assertBody({ received: true })

    const event = await Event.query().where('inboxId', inboxIdValue).firstOrFail()
    assert.equal(event.method, 'GET')
    assert.deepEqual(event.query, { challenge: 'abc123' })
    assert.isNull(event.bodyText)
  })
})
