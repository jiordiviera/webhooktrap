import { apiFetch } from '@/lib/api'

export type TwoFactorSecret = {
  secret: string
  uri: string
  qr: string
}

export type TwoFactorVerifyResponse = {
  message: string
}

export type TwoFactorRecoveryCodesResponse = {
  recovery_codes: string[]
}

export async function generateTwoFactorSecret(): Promise<TwoFactorSecret> {
  return apiFetch<TwoFactorSecret>('/api/v1/2fa/generate', { method: 'POST' })
}

export async function verifyTwoFactorOtp(otp: string): Promise<TwoFactorVerifyResponse> {
  return apiFetch<TwoFactorVerifyResponse>('/api/v1/2fa/verify', {
    method: 'POST',
    body: JSON.stringify({ otp }),
  })
}

export async function generateRecoveryCodes(): Promise<TwoFactorRecoveryCodesResponse> {
  return apiFetch<TwoFactorRecoveryCodesResponse>('/api/v1/2fa/generate-recovery-codes', {
    method: 'POST',
  })
}

export async function disableTwoFactor(): Promise<void> {
  await apiFetch<void>('/api/v1/2fa/disable', { method: 'POST' })
}
