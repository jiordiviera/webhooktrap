import env from '#start/env'
import { defineConfig, services } from '@adonisjs/ally'

function callbackUrl(provider: string) {
  return `${env.get('APP_URL')}/api/v1/auth/oauth/${provider}/callback`
}

function oauthProvider<T extends Record<string, unknown>>(config: T | undefined) {
  return config ?? ({} as T)
}

const allyConfig = defineConfig({
  ...oauthProvider(
    env.get('GITHUB_CLIENT_ID') && env.get('GITHUB_CLIENT_SECRET')
      ? {
          github: services.github({
            clientId: env.get('GITHUB_CLIENT_ID')!,
            clientSecret: env.get('GITHUB_CLIENT_SECRET')!,
            callbackUrl: callbackUrl('github'),
            scopes: ['user:email', 'read:user'],
          }),
        }
      : undefined
  ),
  ...oauthProvider(
    env.get('GOOGLE_CLIENT_ID') && env.get('GOOGLE_CLIENT_SECRET')
      ? {
          google: services.google({
            clientId: env.get('GOOGLE_CLIENT_ID')!,
            clientSecret: env.get('GOOGLE_CLIENT_SECRET')!,
            callbackUrl: callbackUrl('google'),
            scopes: ['openid', 'profile', 'email'],
            prompt: 'select_account',
          }),
        }
      : undefined
  ),
})

export default allyConfig

declare module '@adonisjs/ally/types' {
  interface SocialProviders extends InferSocialProviders<typeof allyConfig> {}
}