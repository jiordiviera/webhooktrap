import User from '#models/user'
import { signupValidator } from '#validators/user'
import type { HttpContext } from '@adonisjs/core/http'
import { serializeUserProfile } from '#support/user_profile_payload'

export default class NewAccountController {
  async store({ request, serialize }: HttpContext) {
    const { fullName, email, password } = await request.validateUsing(signupValidator)

    const user = await User.create({ fullName, email, password })
    const token = await User.accessTokens.create(user)

    return serialize({
      user: await serializeUserProfile(user),
      token: token.value!.release(),
    })
  }
}
