import { BaseMail } from '@adonisjs/mail'
import type User from '#models/user'

export default class RequestOtpNotification extends BaseMail {
  subject = 'Your verification code'

  constructor(
    private user: User,
    private code: string,
    private type: 'email_verify' | 'password_reset'
  ) {
    super()
  }

  prepare() {
    this.message.to(this.user.email)

    const title =
      this.type === 'password_reset' ? 'Reset your password' : 'Verify your email address'

    this.message.htmlView('emails/otp', {
      title,
      code: this.code,
      brandName: 'Hookvane',
    })
  }
}
