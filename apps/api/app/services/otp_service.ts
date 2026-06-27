import Otp from '#models/otp'
import type User from '#models/user'
import { DateTime } from 'luxon'

export type OtpType = 'email_verify' | 'password_reset'

export default class OtpService {
  static generateCode(): string {
    return String(Math.floor(100000 + Math.random() * 900000))
  }

  static generateToken(): string {
    const bytes = new Uint8Array(32)
    crypto.getRandomValues(bytes)
    return Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('')
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

  static async verify(user: User, type: OtpType, code: string): Promise<string | null> {
    const otp = await Otp.query()
      .where('user_id', user.id)
      .where('type', type)
      .where('code', code)
      .whereNull('used_at')
      .where('expires_at', '>', DateTime.now().toSQL()!)
      .first()

    if (!otp) return null

    const verifiedToken = type === 'password_reset' ? this.generateToken() : null

    await otp.merge({ usedAt: DateTime.now(), verifiedToken }).save()
    return verifiedToken
  }
}
