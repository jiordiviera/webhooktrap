/*
|--------------------------------------------------------------------------
| Routes file
|--------------------------------------------------------------------------
|
| The routes file is used for defining the HTTP routes.
|
*/

import { middleware } from '#start/kernel'
import router from '@adonisjs/core/services/router'
import { controllers } from '#generated/controllers'
import { twoFactorAuthRoutes } from '#start/routes/2fa'
import { otpRequestThrottle, otpVerifyThrottle } from '#start/limiter'

router.get('/', () => {
  return { hello: 'world' }
})

router.get('/health', [controllers.Health, 'show']).as('health')

router
  .route('/i/:inboxId', ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], [controllers.Ingest, 'handle'])
  .where('inboxId', /^[A-Za-z0-9]{12}$/)
  .use(middleware.ingestLimit())
  .as('ingest')

router
  .group(() => {
    twoFactorAuthRoutes()
    router.post('inboxes', [controllers.Inboxes, 'store']).as('inboxes.store')
    router
      .get('inboxes/:id', [controllers.Inboxes, 'show'])
      .where('id', /^[A-Za-z0-9]{12}$/)
      .as('inboxes.show')
    router
      .patch('inboxes/:id', [controllers.Inboxes, 'update'])
      .where('id', /^[A-Za-z0-9]{12}$/)
      .as('inboxes.update')
      .use(middleware.auth())
    router
      .delete('inboxes/:id', [controllers.Inboxes, 'destroy'])
      .where('id', /^[A-Za-z0-9]{12}$/)
      .as('inboxes.destroy')
      .use(middleware.auth())
    router
      .get('inboxes/:id/events', [controllers.Inboxes, 'events'])
      .where('id', /^[A-Za-z0-9]{12}$/)
      .as('inboxes.events')
    router.get('inboxes', [controllers.Inboxes, 'index']).as('inboxes.index').use(middleware.auth())

    router
      .get('events/:id', [controllers.Events, 'show'])
      .where('id', /^evt_[0-9A-Z]{26}$/)
      .as('events.show')
    router
      .post('events/:id/replay', [controllers.Events, 'replay'])
      .where('id', /^evt_[0-9A-Z]{26}$/)
      .as('events.replay')
      .use(middleware.auth())
    router
      .get('events/:id/replays', [controllers.Events, 'replays'])
      .where('id', /^evt_[0-9A-Z]{26}$/)
      .as('events.replays')
    router
      .post('events/:id/share', [controllers.Share, 'store'])
      .where('id', /^evt_[0-9A-Z]{26}$/)
      .as('events.share')
      .use(middleware.auth())
    router.get('s/:token', [controllers.Share, 'show']).as('share.show')
    router
      .group(() => {
        router.post('signup', [controllers.NewAccount, 'store'])
        router.post('login', [controllers.AccessTokens, 'store'])

        router.post('request-otp', [controllers.Otp, 'request']).use(otpRequestThrottle)
        router.post('verify-otp', [controllers.Otp, 'verify']).use(otpVerifyThrottle)
        router.post('reset-password', [controllers.ResetPassword, 'store'])

        router.get('oauth/providers', [controllers.Oauth, 'providers'])
        router.get('oauth/:provider/redirect', [controllers.Oauth, 'redirect'])
        router.get('oauth/:provider/callback', [controllers.Oauth, 'callback'])
      })
      .prefix('auth')
      .as('auth')

    router
      .group(() => {
        router.get('profile', [controllers.Profile, 'show'])
        router.patch('profile', [controllers.Profile, 'update'])
        router.post('logout', [controllers.AccessTokens, 'destroy'])
        router.get('tokens', [controllers.ApiTokens, 'index'])
        router.post('tokens', [controllers.ApiTokens, 'store'])
        router.delete('tokens/:id', [controllers.ApiTokens, 'destroy'])
      })
      .prefix('account')
      .as('profile')
      .use(middleware.auth())

    router
      .group(() => {
        router.get('media', [controllers.Media, 'index']).as('media.index')
        router.get('media/:id', [controllers.Media, 'show']).as('media.show')
        router.post('media', [controllers.Media, 'store']).as('media.store')
        router.post('media/from-url', [controllers.Media, 'storeFromUrl']).as('media.from_url')
        router.delete('media/:id', [controllers.Media, 'destroy']).as('media.destroy')
      })
      .use(middleware.auth())
  })
  .as('v1')
  .prefix('/api/v1')
