import User from '#models/user'
import testUtils from '@adonisjs/core/services/test_utils'
import { test } from '@japa/runner'

test.group('Profile API', (group) => {
  group.each.setup(async () => {
    await testUtils.db().migrate()
  })

  test('updates profile display name', async ({ client, assert }) => {
    const user = await User.create({
      fullName: 'Before',
      email: 'profile@hookscope.test',
      password: 'password123',
    })

    const token = await User.accessTokens.create(user, ['*'], {
      name: 'test-token',
    })

    const response = await client
      .patch('/api/v1/account/profile')
      .header('authorization', `Bearer ${token.value!.release()}`)
      .json({ fullName: 'After Update' })

    response.assertStatus(200)
    response.assertBodyContains({
      data: {
        fullName: 'After Update',
        email: 'profile@hookscope.test',
      },
    })

    await user.refresh()
    assert.equal(user.fullName, 'After Update')
  })
})
