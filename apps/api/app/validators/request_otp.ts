import vine from '@vinejs/vine'

export const requestOtpValidator = vine.compile(
  vine.object({
    email: vine.string().email().maxLength(254),
    type: vine.enum(['email_verify', 'password_reset']),
  })
)
