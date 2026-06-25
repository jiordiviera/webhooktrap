import env from '#start/env'
import { defineConfig } from '@nulix/adonis-2fa'

const twoFactorAuthConfig = defineConfig({
  issuer: env.get('APP_ISSUER', 'adonis'),
})

export default twoFactorAuthConfig
