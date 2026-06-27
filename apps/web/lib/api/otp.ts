import { apiFetch } from '@/lib/api'

export type OtpType = 'email_verify' | 'password_reset'

type OtpRequestResponse = {
  data: { message: string }
}

type OtpVerifyResponse = {
  data: { message: string; reset_token?: string }
}

type ResetPasswordResponse = {
  data: { message: string }
}

export async function requestOtp(email: string, type: OtpType) {
  return apiFetch<OtpRequestResponse>('/api/v1/auth/request-otp', {
    method: 'POST',
    skipAuth: true,
    body: JSON.stringify({ email, type }),
  })
}

export async function verifyOtp(email: string, code: string, type: OtpType) {
  return apiFetch<OtpVerifyResponse>('/api/v1/auth/verify-otp', {
    method: 'POST',
    skipAuth: true,
    body: JSON.stringify({ email, code, type }),
  })
}

export async function resetPassword(
  resetToken: string,
  password: string,
  passwordConfirmation: string
) {
  return apiFetch<ResetPasswordResponse>('/api/v1/auth/reset-password', {
    method: 'POST',
    skipAuth: true,
    body: JSON.stringify({ resetToken, password, passwordConfirmation }),
  })
}
