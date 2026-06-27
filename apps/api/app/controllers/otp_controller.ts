import OtpService from '#services/otp_service'
import User from '#models/user'
import { requestOtpValidator } from '#validators/request_otp'
import { emailOtpValidator } from '#validators/email_otp'
import type { HttpContext } from '@adonisjs/core/http'
import mail from '@adonisjs/mail/services/main'
import RequestOtpNotification from '#mails/request_otp_notification'
import { DateTime } from 'luxon'

export default class OtpController {
  async request({ request, serialize }: HttpContext) {
    const { email, type } = await request.validateUsing(requestOtpValidator)

    const user = await User.findBy('email', email)
    if (!user) {
      return serialize({ message: 'If the email exists, an OTP has been sent.' })
    }

    try {
      const otp = await OtpService.create(user, type)
      await mail.sendLater(new RequestOtpNotification(user, otp.code, type))
    } catch {}

    return serialize({ message: 'If the email exists, an OTP has been sent.' })
  }

  async verify({ request, response, serialize }: HttpContext) {
    try {
      const { email, code, type } = await request.validateUsing(emailOtpValidator)

      const user = await User.findBy('email', email)
      if (!user) {
        return response.badRequest({ message: 'Invalid or expired OTP' })
      }

      const result = await OtpService.verify(user, type, code)
      if (!result.verified) {
        return response.badRequest({ message: 'Invalid or expired OTP' })
      }

      if (type === 'email_verify') {
        user.emailVerifiedAt = DateTime.now()
        await user.save()
      }

      return serialize({
        message: 'OTP verified successfully',
        ...(result.resetToken ? { reset_token: result.resetToken } : {}),
      })
    } catch {
      return response.badRequest({ message: 'Invalid or expired OTP' })
    }
  }
}
