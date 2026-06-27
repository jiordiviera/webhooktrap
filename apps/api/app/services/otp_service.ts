import db from '@adonisjs/lucid/services/db'
import Otp from '#models/otp'
import type User from '#models/user'
import { DateTime } from 'luxon'

export type OtpType = 'email_verify' | 'password_reset'

export type VerifyResult =
  | { verified: false }
  | { verified: true; resetToken?: string }

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
    const trx = await db.transaction()
    try {
      await Otp.query({ client: trx })
        .where('user_id', user.id)
        .where('type', type)
        .whereNull('used_at')
        .where('expires_at', '>', DateTime.now().toSQL()!)
        .delete()

      const otp = await Otp.create(
        {
          userId: user.id,
          type,
          code: this.generateCode(),
          expiresAt: DateTime.now().plus({ minutes: 10 }),
        },
        { client: trx }
      )

      await trx.commit()
      return otp
    } catch (error) {
      await trx.rollback()
      throw error
    }
  }

  static async verify(user: User, type: OtpType, code: string): Promise<VerifyResult> {
    const trx = await db.transaction()
    try {
      const otp = await Otp.query({ client: trx })
        .where('user_id', user.id)
        .where('type', type)
        .where('code', code)
        .whereNull('used_at')
        .where('expires_at', '>', DateTime.now().toSQL()!)
        .first()

      if (!otp) {
        await trx.rollback()
        return { verified: false }
      }

      const resetToken = type === 'password_reset' ? this.generateToken() : undefined

      await otp.useTransaction(trx).merge({ usedAt: DateTime.now(), verifiedToken: resetToken ?? null }).save()

      await trx.commit()
      return { verified: true, resetToken }
    } catch (error) {
      await trx.rollback()
      throw error
    }
  }
}
