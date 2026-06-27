import db from '@adonisjs/lucid/services/db'
import Otp from '#models/otp'
import User from '#models/user'
import { resetPasswordValidator } from '#validators/reset_password'
import type { HttpContext } from '@adonisjs/core/http'
import { DateTime } from 'luxon'

export default class ResetPasswordController {
  async store({ request, response, serialize }: HttpContext) {
    const { resetToken, password } = await request.validateUsing(resetPasswordValidator)

    const result = await db.transaction(async (trx) => {
      const otp = await Otp.query({ client: trx })
        .where('verified_token', resetToken)
        .where('type', 'password_reset')
        .whereNotNull('used_at')
        .where('expires_at', '>', DateTime.now().toSQL()!)
        .first()

      if (!otp) return null

      if (!otp.userId) return null

      const user = await User.query({ client: trx }).where('id', otp.userId).firstOrFail()

      user.useTransaction(trx)
      user.password = password
      await user.save()

      otp.useTransaction(trx)
      otp.verifiedToken = null
      await otp.save()

      return { user }
    })

    if (!result) {
      return response.badRequest({ message: 'Invalid or expired reset token' })
    }

    return serialize({ message: 'Password reset successfully' })
  }
}
