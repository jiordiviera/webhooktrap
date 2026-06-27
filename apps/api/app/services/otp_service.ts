import Otp from '#models/otp'
import User from '#models/user'
import { DateTime } from 'luxon'

export type OtpType = 'email_verify' | 'password_reset'

export default class OtpService {
  static generateCode(): string {
    return String(Math.floor(100000 + Math.random() * 900000))
  }

  static async create(user: User, type: OtpType): Promise<Otp> {
    await Otp.query()
      .where('user_id', user.id)
      .where('type', type)
      .whereNull('used_at')
      .where('expires_at', '>', DateTime.now().toSQL()!)
      .delete()

    return Otp.create({
      userId: user.id,
      type,
      code: this.generateCode(),
      expiresAt: DateTime.now().plus({ minutes: 10 }),
    })
  }

  static async verify(user: User, type: OtpType, code: string): Promise<boolean> {
    const otp = await Otp.query()
      .where('user_id', user.id)
      .where('type', type)
      .where('code', code)
      .whereNull('used_at')
      .where('expires_at', '>', DateTime.now().toSQL()!)
      .first()

    if (!otp) return false

    await otp.merge({ usedAt: DateTime.now() }).save()
    return true
  }
}
