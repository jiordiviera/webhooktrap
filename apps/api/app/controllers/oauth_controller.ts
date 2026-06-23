import OAuthService from '#services/oauth_service'
import type { OAuthProvider } from '#services/oauth_types'
import User from '#models/user'
import env from '#start/env'
import type { HttpContext } from '@adonisjs/core/http'

const PROVIDER_PATTERN = /^(github|google)$/

export default class OauthController {
  async providers({ ally, serialize }: HttpContext) {
    return serialize({
      providers: ally.configuredProviderNames(),
      signupProviders: ally.signupProviderNames(),
    })
  }

  async redirect({ ally, params, request, response, session }: HttpContext) {
    if (!PROVIDER_PATTERN.test(params.provider) || !ally.has(params.provider)) {
      return this.redirectWithError(response, 'unknown_provider')
    }

    const returnTo = request.input('return_to', env.get('WEB_URL'))
    session.put('oauth.returnTo', returnTo)

    return ally.use(params.provider as OAuthProvider).redirect((redirectRequest) => {
      if (params.provider === 'google') {
        redirectRequest.param('prompt', 'select_account')
      }
    })
  }

  async callback({ ally, params, response, session }: HttpContext) {
    if (!PROVIDER_PATTERN.test(params.provider) || !ally.has(params.provider)) {
      return this.redirectWithError(response, 'unknown_provider')
    }

    const driver = ally.use(params.provider as OAuthProvider)

    if (driver.accessDenied()) {
      return this.redirectWithError(response, 'access_denied')
    }

    if (driver.stateMisMatch()) {
      return this.redirectWithError(response, 'state_mismatch')
    }

    if (driver.hasError()) {
      return this.redirectWithError(response, 'oauth_error')
    }

    const profile = await driver.user()
    const user = await OAuthService.findOrCreate(params.provider as OAuthProvider, profile)
    const accessToken = await User.accessTokens.create(user, ['*'], {
      name: `oauth:${params.provider}`,
    })

    const returnTo = session.pull('oauth.returnTo', env.get('WEB_URL'))
    const token = encodeURIComponent(accessToken.value!.release())

    return response.redirect().toPath(`${returnTo}/auth/callback?token=${token}`)
  }

  private redirectWithError(response: HttpContext['response'], code: string) {
    const returnTo = env.get('WEB_URL')
    return response.redirect().toPath(`${returnTo}/auth/callback?error=${code}`)
  }
}