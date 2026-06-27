import vine from '@vinejs/vine'

export const emailOtpValidator = vine.compile(
  vine.object({
    email: vine.string().email().maxLength(254),
    code: vine.string().minLength(6).maxLength(6),
    type: vine.enum(['email_verify', 'password_reset'] as const),
  })
)
