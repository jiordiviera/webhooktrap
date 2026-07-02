import User from '#models/user'
import Otp from '#models/otp'
import testUtils from '@adonisjs/core/services/test_utils'
import { test } from '@japa/runner'
import { DateTime } from 'luxon'
import limiter from '@adonisjs/limiter/services/main'

test.group('OTP API', (group) => {
  group.each.setup(async () => {
    await testUtils.db().migrate()
    // otp_request/otp_verify throttles key by IP — every request in this
    // group shares the test client's IP, so state must be cleared between
    // tests to avoid bleeding into the next test's rate limit.
    await limiter.clear()
  })

  test('request-otp sends email for existing user', async ({ client, assert }) => {
    const user = await User.create({
      email: 'otp-test@hookscope.test',
      password: 'password123',
    })

    const response = await client
      .post('/api/v1/auth/request-otp')
      .json({ email: 'otp-test@hookscope.test', type: 'email_verify' })

    response.assertStatus(200)
    response.assertBodyContains({ data: {} })

    const otp = await Otp.query().where('user_id', user.id).where('type', 'email_verify').first()
    assert.isNotNull(otp)
    assert.lengthOf(otp!.code, 6)
    assert.isNull(otp!.usedAt)
  })

  test('request-otp always returns 200 even for unknown email', async ({ client }) => {
    const response = await client
      .post('/api/v1/auth/request-otp')
      .json({ email: 'unknown@hookscope.test', type: 'email_verify' })

    response.assertStatus(200)
  })

  test('verify-otp succeeds with valid code', async ({ client, assert }) => {
    const user = await User.create({
      email: 'verify@hookscope.test',
      password: 'password123',
    })

    await Otp.create({
      userId: user.id,
      type: 'email_verify',
      code: '123456',
      expiresAt: DateTime.now().plus({ minutes: 10 }),
    })

    const response = await client
      .post('/api/v1/auth/verify-otp')
      .json({ email: 'verify@hookscope.test', code: '123456', type: 'email_verify' })

    response.assertStatus(200)
    response.assertBodyContains({ data: { message: 'OTP verified successfully' } })

    await user.refresh()
    assert.isNotNull(user.emailVerifiedAt)
  })

  test('verify-otp fails with wrong code', async ({ client }) => {
    const user = await User.create({
      email: 'wrong@hookscope.test',
      password: 'password123',
    })

    await Otp.create({
      userId: user.id,
      type: 'email_verify',
      code: '123456',
      expiresAt: DateTime.now().plus({ minutes: 10 }),
    })

    const response = await client
      .post('/api/v1/auth/verify-otp')
      .json({ email: 'wrong@hookscope.test', code: '000000', type: 'email_verify' })

    response.assertStatus(400)
  })

  test('verify-otp fails with expired code', async ({ client }) => {
    const user = await User.create({
      email: 'expired@hookscope.test',
      password: 'password123',
    })

    await Otp.create({
      userId: user.id,
      type: 'password_reset',
      code: '123456',
      expiresAt: DateTime.now().minus({ minutes: 1 }),
    })

    const response = await client
      .post('/api/v1/auth/verify-otp')
      .json({ email: 'expired@hookscope.test', code: '123456', type: 'password_reset' })

    response.assertStatus(400)
  })
})
