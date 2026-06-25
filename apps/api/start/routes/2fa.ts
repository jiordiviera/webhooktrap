import { middleware } from '#start/kernel'
import router from '@adonisjs/core/services/router'

const TwoFactorsController = () => import('#controllers/two_factor_auth_controller')

export function twoFactorAuthRoutes() {
  router
    .group(() => {
      router.post('generate', [TwoFactorsController, 'generate']).as('generate')
      router.post('verify', [TwoFactorsController, 'verify']).as('verify')
      router
        .post('generate-recovery-codes', [TwoFactorsController, 'generateRecoveryCodes'])
        .as('generate_recovery_codes')
      router.post('disable', [TwoFactorsController, 'disable']).as('disable')
    })
    .as('2fa')
    .prefix('2fa')
    .middleware(middleware.auth())
}
