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

router.get('/', () => {
  return { hello: 'world' }
})

router
  .route('/i/:inboxId', ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], [controllers.Ingest, 'handle'])
  .where('inboxId', /^[A-Za-z0-9]{12}$/)
  .use(middleware.ingestLimit())
  .as('ingest')

router
  .group(() => {
    router.post('inboxes', [controllers.Inboxes, 'store']).as('inboxes.store')
    router
      .get('inboxes', [controllers.Inboxes, 'index'])
      .as('inboxes.index')
      .use(middleware.auth())
    router
      .group(() => {
        router.post('signup', [controllers.NewAccount, 'store'])
        router.post('login', [controllers.AccessTokens, 'store'])

        router.get('oauth/providers', [controllers.Oauth, 'providers'])
        router.get('oauth/:provider/redirect', [controllers.Oauth, 'redirect'])
        router.get('oauth/:provider/callback', [controllers.Oauth, 'callback'])
      })
      .prefix('auth')
      .as('auth')

    router
      .group(() => {
        router.get('profile', [controllers.Profile, 'show'])
        router.post('logout', [controllers.AccessTokens, 'destroy'])
      })
      .prefix('account')
      .as('profile')
      .use(middleware.auth())
  })
  .prefix('/api/v1')
