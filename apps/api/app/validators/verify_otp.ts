import vine from '@vinejs/vine'

export const verifyOtpValidator = vine.compile(
  vine.object({
    otp: vine.string(),
  })
)
