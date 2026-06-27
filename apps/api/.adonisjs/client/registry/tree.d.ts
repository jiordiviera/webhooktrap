/* eslint-disable prettier/prettier */
import type { routes } from './index.ts'

export interface ApiDefinition {
  ingest: typeof routes['ingest']
  v1: {
    2Fa: {
      generate: typeof routes['v1.2fa.generate']
      verify: typeof routes['v1.2fa.verify']
      generateRecoveryCodes: typeof routes['v1.2fa.generate_recovery_codes']
      disable: typeof routes['v1.2fa.disable']
    }
    inboxes: {
      store: typeof routes['v1.inboxes.store']
      show: typeof routes['v1.inboxes.show']
      update: typeof routes['v1.inboxes.update']
      destroy: typeof routes['v1.inboxes.destroy']
      events: typeof routes['v1.inboxes.events']
      index: typeof routes['v1.inboxes.index']
    }
    events: {
      show: typeof routes['v1.events.show']
      replay: typeof routes['v1.events.replay']
      replays: typeof routes['v1.events.replays']
      share: typeof routes['v1.events.share']
    }
    share: {
      show: typeof routes['v1.share.show']
    }
    auth: {
      newAccount: {
        store: typeof routes['v1.auth.new_account.store']
      }
      accessTokens: {
        store: typeof routes['v1.auth.access_tokens.store']
      }
      oauth: {
        providers: typeof routes['v1.auth.oauth.providers']
        redirect: typeof routes['v1.auth.oauth.redirect']
        callback: typeof routes['v1.auth.oauth.callback']
      }
    }
    profile: {
      profile: {
        show: typeof routes['v1.profile.profile.show']
        update: typeof routes['v1.profile.profile.update']
      }
      accessTokens: {
        destroy: typeof routes['v1.profile.access_tokens.destroy']
      }
    }
    media: {
      index: typeof routes['v1.media.index']
      show: typeof routes['v1.media.show']
      store: typeof routes['v1.media.store']
      fromUrl: typeof routes['v1.media.from_url']
      destroy: typeof routes['v1.media.destroy']
    }
  }
}
