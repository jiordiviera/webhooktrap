import User from '#models/user'
import { loginValidator } from '#validators/user'
import type { HttpContext } from '@adonisjs/core/http'
import { serializeUserProfile } from '#support/user_profile_payload'

export default class AccessTokensController {
  async store({ request, serialize }: HttpContext) {
    const { email, password } = await request.validateUsing(loginValidator)

    const user = await User.verifyCredentials(email, password)

    if (user.isTwoFactorEnabled) {
      const challengeToken = await User.accessTokens.create(user)
      return serialize({
        requires_2fa: true,
        challenge_token: challengeToken.value!.release(),
      })
    }

    const token = await User.accessTokens.create(user)

    return serialize({
      user: await serializeUserProfile(user),
      token: token.value!.release(),
      email_verified: !!user.emailVerifiedAt,
    })
  }

  async destroy({ auth }: HttpContext) {
    const user = auth.getUserOrFail()
    if (user.currentAccessToken) {
      await User.accessTokens.delete(user, user.currentAccessToken.identifier)
    }

    return {
      message: 'Logged out successfully',
    }
  }
}
