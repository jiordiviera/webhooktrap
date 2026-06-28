import env from '#start/env'
import { defineConfig, services } from '@adonisjs/ally'

function callbackUrl(provider: 'github' | 'google') {
  return `${env.get('APP_URL')}/api/v1/auth/oauth/${provider}/callback`
}

const allyConfig = defineConfig({
  github: services.github({
    clientId: env.get('GITHUB_CLIENT_ID')!,
    clientSecret: env.get('GITHUB_CLIENT_SECRET')!,
    callbackUrl: callbackUrl('github'),
    scopes: ['user:email', 'read:user'],
  }),
  google: services.google({
    clientId: env.get('GOOGLE_CLIENT_ID')!,
    clientSecret: env.get('GOOGLE_CLIENT_SECRET')!,
    callbackUrl: callbackUrl('google'),
    scopes: ['openid', 'profile', 'email'],
    prompt: 'select_account',
  }),
})

export default allyConfig

declare module '@adonisjs/ally/types' {
  interface SocialProviders extends InferSocialProviders<typeof allyConfig> {}
}
