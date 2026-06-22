import env from '#start/env'
import { defineConfig, services } from '@adonisjs/ally'

function oauthProvider<T>(config: T | undefined) {
  return config ?? ({} as T)
}

const allyConfig = defineConfig({
  ...oauthProvider(
    env.get('GITHUB_CLIENT_ID') && env.get('GITHUB_CLIENT_SECRET')
      ? {
          github: services.github({
            clientId: env.get('GITHUB_CLIENT_ID')!,
            clientSecret: env.get('GITHUB_CLIENT_SECRET')!,
            callbackUrl: `${env.get('APP_URL')}/oauth/github/callback`,
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
            callbackUrl: `${env.get('APP_URL')}/oauth/google/callback`,
          }),
        }
      : undefined
  ),
})

export default allyConfig

declare module '@adonisjs/ally/types' {
  interface SocialProviders extends InferSocialProviders<typeof allyConfig> {}
}
