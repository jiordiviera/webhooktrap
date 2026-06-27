import Otp from '#models/otp'
import User from '#models/user'
import { resetPasswordValidator } from '#validators/reset_password'
import type { HttpContext } from '@adonisjs/core/http'
import { DateTime } from 'luxon'

export default class ResetPasswordController {
  async store({ request, response, serialize }: HttpContext) {
    const { resetToken, password } = await request.validateUsing(resetPasswordValidator)

    const otp = await Otp.query()
      .where('verified_token', resetToken)
      .where('type', 'password_reset')
      .whereNotNull('used_at')
      .where('expires_at', '>', DateTime.now().toSQL()!)
      .first()

    if (!otp) {
      return response.badRequest({ message: 'Invalid or expired reset token' })
    }

    const user = await User.findOrFail(otp.userId)

    user.password = password
    await user.save()

    otp.verifiedToken = null
    await otp.save()

    return serialize({ message: 'Password reset successfully' })
  }
}
