import User from '#models/user'
import Otp from '#models/otp'
import OtpService from '#services/otp_service'
import testUtils from '@adonisjs/core/services/test_utils'
import { test } from '@japa/runner'

test.group('OtpService', (group) => {
  group.each.setup(async () => {
    await testUtils.db().migrate()
  })

  test('creates and verifies OTP', async ({ assert }) => {
    const user = await User.create({
      email: 'otp-unit@hookscope.test',
      password: 'password123',
    })

    const otp = await OtpService.create(user, 'email_verify')
    assert.isNotNull(otp)
    assert.lengthOf(otp.code, 6)
    assert.isTrue(otp.expiresAt > new Date())

    const valid = await OtpService.verify(user, 'email_verify', otp.code)
    assert.isTrue(valid.verified)

    const otpAfter = await Otp.query().where('user_id', user.id).first()
    assert.isNotNull(otpAfter!.usedAt)
  })
})
