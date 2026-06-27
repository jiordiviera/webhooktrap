import vine from '@vinejs/vine'

const password = () => vine.string().minLength(8).maxLength(32)

export const resetPasswordValidator = vine.compile(
  vine.object({
    resetToken: vine.string(),
    password: password(),
    passwordConfirmation: password().sameAs('password'),
  })
)
