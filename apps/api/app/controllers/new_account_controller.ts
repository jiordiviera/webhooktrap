import User from '#models/user'
import OtpService from '#services/otp_service'
import { signupValidator } from '#validators/user'
import type { HttpContext } from '@adonisjs/core/http'
import { serializeUserProfile } from '#support/user_profile_payload'
import mail from '@adonisjs/mail/services/main'
import RequestOtpNotification from '#mails/request_otp_notification'

export default class NewAccountController {
  async store({ request, serialize }: HttpContext) {
    const { fullName, email, password } = await request.validateUsing(signupValidator)

    const user = await User.create({ fullName, email, password })
    const token = await User.accessTokens.create(user)

    const otp = await OtpService.create(user, 'email_verify')

    try {
      await mail.sendLater(new RequestOtpNotification(user, otp.code, 'email_verify'))
    } catch {}

    return serialize({
      user: await serializeUserProfile(user),
      token: token.value!.release(),
      email_verified: false,
    })
  }
}
