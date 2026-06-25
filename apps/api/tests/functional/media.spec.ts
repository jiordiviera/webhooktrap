import User from '#models/user'
import testUtils from '@adonisjs/core/services/test_utils'
import { test } from '@japa/runner'
import { writeFile } from 'node:fs/promises'
import { join } from 'node:path'
import { tmpdir } from 'node:os'

const PNG_BASE64 =
  'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='

test.group('Media API', (group) => {
  group.each.setup(async () => {
    await testUtils.db().migrate()
  })

  test('uploads avatar via multipart', async ({ client, assert }) => {
    const user = await User.create({
      fullName: 'Media User',
      email: 'media@hookscope.test',
      password: 'password123',
    })

    const token = await User.accessTokens.create(user, ['*'], {
      name: 'test-token',
    })

    const filePath = join(tmpdir(), `avatar-${Date.now()}.png`)
    await writeFile(filePath, Buffer.from(PNG_BASE64, 'base64'))

    const response = await client
      .post('/api/v1/media')
      .header('authorization', `Bearer ${token.value!.release()}`)
      .file('file', filePath)
      .fields({
        model_type: 'users',
        model_id: String(user.id),
        collection: 'avatar',
      })

    response.assertStatus(200)
    response.assertBodyContains({
      data: {
        media: {
          modelType: 'users',
          modelId: String(user.id),
          collection: 'avatar',
          mimeType: 'image/png',
        },
      },
    })

    const body = response.body() as { data: { media: { url: string } } }
    assert.isString(body.data.media.url)
    assert.include(body.data.media.url, 'https://media.hookscope.test/users/')
  })

  test('rejects media upload for another user', async ({ client }) => {
    const owner = await User.create({
      fullName: 'Owner',
      email: 'owner@hookscope.test',
      password: 'password123',
    })

    const other = await User.create({
      fullName: 'Other',
      email: 'other@hookscope.test',
      password: 'password123',
    })

    const token = await User.accessTokens.create(other, ['*'], {
      name: 'test-token',
    })

    const filePath = join(tmpdir(), `avatar-denied-${Date.now()}.png`)
    await writeFile(filePath, Buffer.from(PNG_BASE64, 'base64'))

    const response = await client
      .post('/api/v1/media')
      .header('authorization', `Bearer ${token.value!.release()}`)
      .file('file', filePath)
      .fields({
        model_type: 'users',
        model_id: String(owner.id),
        collection: 'avatar',
      })

    response.assertStatus(403)
  })
})
