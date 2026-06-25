import twoFactorAuth from '@nulix/adonis-2fa/services/main'
import type { HttpContext } from '@adonisjs/core/http'

import { verifyOtpValidator } from '#validators/verify_otp'

export default class TwoFactorAuthController {
  async generate({ auth }: HttpContext) {
    const user = auth.user!

    user.twoFactorSecret = await twoFactorAuth.generateSecret(user.email)
    user.isTwoFactorEnabled = false

    await user.save()

    return user.twoFactorSecret
  }

  async disable({ auth, response }: HttpContext) {
    if (!auth.user!.isTwoFactorEnabled) {
      return response.badRequest({ message: 'User without 2FA active' })
    }

    await auth
      .user!.merge({ isTwoFactorEnabled: false, twoFactorRecoveryCodes: [], twoFactorSecret: null })
      .save()

    return response.noContent()
  }

  async verify({ auth, request, response }: HttpContext) {
    const { otp } = await request.validateUsing(verifyOtpValidator)

    const user = auth.user!

    const isValid = twoFactorAuth.verifyToken(
      user.twoFactorSecret?.secret,
      otp,
      user.twoFactorRecoveryCodes
    )

    if (!isValid) {
      return response.badRequest({ message: 'OTP invalid' })
    }

    if (!user.isTwoFactorEnabled) {
      await user.merge({ isTwoFactorEnabled: true }).save()
    }

    return response.ok({ message: 'OTP valid' })
  }

  async generateRecoveryCodes({ auth, response }: HttpContext) {
    const user = auth.user!

    if (!user.isTwoFactorEnabled) {
      return response.badRequest({ message: 'User without 2FA active' })
    }

    const recoveryCodes = twoFactorAuth.generateRecoveryCodes()

    await user.merge({ twoFactorRecoveryCodes: recoveryCodes }).save()

    return { recovery_codes: recoveryCodes }
  }
}
