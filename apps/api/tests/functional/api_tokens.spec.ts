import User from '#models/user'
import testUtils from '@adonisjs/core/services/test_utils'
import { test } from '@japa/runner'

test.group('API Tokens', (group) => {
  group.each.setup(async () => {
    await testUtils.db().migrate()
  })

  test('lists personal access tokens', async ({ client, assert }) => {
    const user = await User.create({
      fullName: 'Token User',
      email: 'tokens@hookscope.test',
      password: 'password123',
    })

    await User.accessTokens.create(user, ['*'], { name: 'My App' })
    await User.accessTokens.create(user, ['*'], { name: 'CLI' })

    const authToken = await User.accessTokens.create(user, ['*'], {
      name: 'test-auth',
    })

    const response = await client
      .get('/api/v1/account/tokens')
      .header('authorization', `Bearer ${authToken.value!.release()}`)

    response.assertStatus(200)
    assert.lengthOf(response.body().data.tokens, 3)

    response.assertBodyContains({
      data: {
        tokens: [{ name: 'My App' }, { name: 'CLI' }, { name: 'test-auth' }],
      },
    })
  })

  test('creates a personal access token', async ({ client, assert }) => {
    const user = await User.create({
      fullName: 'Create Token',
      email: 'create-token@hookscope.test',
      password: 'password123',
    })

    const authToken = await User.accessTokens.create(user, ['*'], {
      name: 'test-auth',
    })

    const response = await client
      .post('/api/v1/account/tokens')
      .header('authorization', `Bearer ${authToken.value!.release()}`)
      .json({ name: 'My New Token' })

    response.assertStatus(200)
    response.assertBodyContains({
      data: {
        name: 'My New Token',
      },
    })

    const body = response.body()
    assert.isDefined(body.data.token)
    assert.isString(body.data.token)
  })

  test('revokes a personal access token', async ({ client, assert }) => {
    const user = await User.create({
      fullName: 'Revoke Token',
      email: 'revoke-token@hookscope.test',
      password: 'password123',
    })

    const target = await User.accessTokens.create(user, ['*'], {
      name: 'To Revoke',
    })

    const authToken = await User.accessTokens.create(user, ['*'], {
      name: 'test-auth',
    })

    const response = await client
      .delete(`/api/v1/account/tokens/${target.identifier}`)
      .header('authorization', `Bearer ${authToken.value!.release()}`)

    response.assertStatus(200)
    response.assertBodyContains({ data: { deleted: true } })

    const remaining = await User.accessTokens.all(user)
    assert.lengthOf(remaining, 1)
    assert.equal(remaining[0].name, 'test-auth')
  })

  test('returns 404 when revoking non-existent token', async ({ client }) => {
    const user = await User.create({
      fullName: 'Not Found',
      email: 'notfound@hookscope.test',
      password: 'password123',
    })

    const authToken = await User.accessTokens.create(user, ['*'], {
      name: 'test-auth',
    })

    const response = await client
      .delete('/api/v1/account/tokens/99999')
      .header('authorization', `Bearer ${authToken.value!.release()}`)

    response.assertStatus(404)
  })

  test('requires authentication', async ({ client }) => {
    const response = await client.get('/api/v1/account/tokens')
    response.assertStatus(401)
  })

  test('validates token name is present', async ({ client }) => {
    const user = await User.create({
      fullName: 'Validation',
      email: 'validation@hookscope.test',
      password: 'password123',
    })

    const authToken = await User.accessTokens.create(user, ['*'], {
      name: 'test-auth',
    })

    const response = await client
      .post('/api/v1/account/tokens')
      .header('authorization', `Bearer ${authToken.value!.release()}`)
      .json({ name: '' })

    response.assertStatus(422)
  })
})
