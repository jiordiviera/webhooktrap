/* eslint-disable prettier/prettier */
import type { routes } from './index.ts'

export interface ApiDefinition {
  ingest: typeof routes['ingest']
  inboxes: {
    store: typeof routes['inboxes.store']
  }
  auth: {
    newAccount: {
      store: typeof routes['auth.new_account.store']
    }
    accessTokens: {
      store: typeof routes['auth.access_tokens.store']
    }
    oauth: {
      providers: typeof routes['auth.oauth.providers']
      redirect: typeof routes['auth.oauth.redirect']
      callback: typeof routes['auth.oauth.callback']
    }
  }
  profile: {
    profile: {
      show: typeof routes['profile.profile.show']
    }
    accessTokens: {
      destroy: typeof routes['profile.access_tokens.destroy']
    }
  }
}
