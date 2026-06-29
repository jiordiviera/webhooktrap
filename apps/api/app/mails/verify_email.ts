import { BaseMail } from '@adonisjs/mail'
import type User from '#models/user'

export default class VerifyEmailNotification extends BaseMail {
  subject = 'Verify your email address'

  constructor(private user: User) {
    super()
  }

  prepare() {
    this.message.to(this.user.email)
    this.message.htmlView('emails/verify_email', {
      subject: this.subject,
      title: 'Verify your email address',
      body: `Hi ${this.user.email}, click the link below to verify your email address.`,
    })
  }
}
