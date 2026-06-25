/* eslint-disable prettier/prettier */
import type { AdonisEndpoint } from '@tuyau/core/types'
import type { Registry } from './schema.d.ts'
import type { ApiDefinition } from './tree.d.ts'

const placeholder: any = {}

const routes = {
  'ingest': {
    methods: ["GET","POST","PUT","PATCH","DELETE"],
    pattern: '/i/:inboxId',
    tokens: [{"old":"/i/:inboxId","type":0,"val":"i","end":""},{"old":"/i/:inboxId","type":1,"val":"inboxId","end":""}],
    types: placeholder as Registry['ingest']['types'],
  },
  'v1.2fa.generate': {
    methods: ["POST"],
    pattern: '/api/v1/2fa/generate',
    tokens: [{"old":"/api/v1/2fa/generate","type":0,"val":"api","end":""},{"old":"/api/v1/2fa/generate","type":0,"val":"v1","end":""},{"old":"/api/v1/2fa/generate","type":0,"val":"2fa","end":""},{"old":"/api/v1/2fa/generate","type":0,"val":"generate","end":""}],
    types: placeholder as Registry['v1.2fa.generate']['types'],
  },
  'v1.2fa.verify': {
    methods: ["POST"],
    pattern: '/api/v1/2fa/verify',
    tokens: [{"old":"/api/v1/2fa/verify","type":0,"val":"api","end":""},{"old":"/api/v1/2fa/verify","type":0,"val":"v1","end":""},{"old":"/api/v1/2fa/verify","type":0,"val":"2fa","end":""},{"old":"/api/v1/2fa/verify","type":0,"val":"verify","end":""}],
    types: placeholder as Registry['v1.2fa.verify']['types'],
  },
  'v1.2fa.generate_recovery_codes': {
    methods: ["POST"],
    pattern: '/api/v1/2fa/generate-recovery-codes',
    tokens: [{"old":"/api/v1/2fa/generate-recovery-codes","type":0,"val":"api","end":""},{"old":"/api/v1/2fa/generate-recovery-codes","type":0,"val":"v1","end":""},{"old":"/api/v1/2fa/generate-recovery-codes","type":0,"val":"2fa","end":""},{"old":"/api/v1/2fa/generate-recovery-codes","type":0,"val":"generate-recovery-codes","end":""}],
    types: placeholder as Registry['v1.2fa.generate_recovery_codes']['types'],
  },
  'v1.2fa.disable': {
    methods: ["POST"],
    pattern: '/api/v1/2fa/disable',
    tokens: [{"old":"/api/v1/2fa/disable","type":0,"val":"api","end":""},{"old":"/api/v1/2fa/disable","type":0,"val":"v1","end":""},{"old":"/api/v1/2fa/disable","type":0,"val":"2fa","end":""},{"old":"/api/v1/2fa/disable","type":0,"val":"disable","end":""}],
    types: placeholder as Registry['v1.2fa.disable']['types'],
  },
  'v1.inboxes.store': {
    methods: ["POST"],
    pattern: '/api/v1/inboxes',
    tokens: [{"old":"/api/v1/inboxes","type":0,"val":"api","end":""},{"old":"/api/v1/inboxes","type":0,"val":"v1","end":""},{"old":"/api/v1/inboxes","type":0,"val":"inboxes","end":""}],
    types: placeholder as Registry['v1.inboxes.store']['types'],
  },
  'v1.inboxes.index': {
    methods: ["GET","HEAD"],
    pattern: '/api/v1/inboxes',
    tokens: [{"old":"/api/v1/inboxes","type":0,"val":"api","end":""},{"old":"/api/v1/inboxes","type":0,"val":"v1","end":""},{"old":"/api/v1/inboxes","type":0,"val":"inboxes","end":""}],
    types: placeholder as Registry['v1.inboxes.index']['types'],
  },
  'v1.auth.new_account.store': {
    methods: ["POST"],
    pattern: '/api/v1/auth/signup',
    tokens: [{"old":"/api/v1/auth/signup","type":0,"val":"api","end":""},{"old":"/api/v1/auth/signup","type":0,"val":"v1","end":""},{"old":"/api/v1/auth/signup","type":0,"val":"auth","end":""},{"old":"/api/v1/auth/signup","type":0,"val":"signup","end":""}],
    types: placeholder as Registry['v1.auth.new_account.store']['types'],
  },
  'v1.auth.access_tokens.store': {
    methods: ["POST"],
    pattern: '/api/v1/auth/login',
    tokens: [{"old":"/api/v1/auth/login","type":0,"val":"api","end":""},{"old":"/api/v1/auth/login","type":0,"val":"v1","end":""},{"old":"/api/v1/auth/login","type":0,"val":"auth","end":""},{"old":"/api/v1/auth/login","type":0,"val":"login","end":""}],
    types: placeholder as Registry['v1.auth.access_tokens.store']['types'],
  },
  'v1.auth.oauth.providers': {
    methods: ["GET","HEAD"],
    pattern: '/api/v1/auth/oauth/providers',
    tokens: [{"old":"/api/v1/auth/oauth/providers","type":0,"val":"api","end":""},{"old":"/api/v1/auth/oauth/providers","type":0,"val":"v1","end":""},{"old":"/api/v1/auth/oauth/providers","type":0,"val":"auth","end":""},{"old":"/api/v1/auth/oauth/providers","type":0,"val":"oauth","end":""},{"old":"/api/v1/auth/oauth/providers","type":0,"val":"providers","end":""}],
    types: placeholder as Registry['v1.auth.oauth.providers']['types'],
  },
  'v1.auth.oauth.redirect': {
    methods: ["GET","HEAD"],
    pattern: '/api/v1/auth/oauth/:provider/redirect',
    tokens: [{"old":"/api/v1/auth/oauth/:provider/redirect","type":0,"val":"api","end":""},{"old":"/api/v1/auth/oauth/:provider/redirect","type":0,"val":"v1","end":""},{"old":"/api/v1/auth/oauth/:provider/redirect","type":0,"val":"auth","end":""},{"old":"/api/v1/auth/oauth/:provider/redirect","type":0,"val":"oauth","end":""},{"old":"/api/v1/auth/oauth/:provider/redirect","type":1,"val":"provider","end":""},{"old":"/api/v1/auth/oauth/:provider/redirect","type":0,"val":"redirect","end":""}],
    types: placeholder as Registry['v1.auth.oauth.redirect']['types'],
  },
  'v1.auth.oauth.callback': {
    methods: ["GET","HEAD"],
    pattern: '/api/v1/auth/oauth/:provider/callback',
    tokens: [{"old":"/api/v1/auth/oauth/:provider/callback","type":0,"val":"api","end":""},{"old":"/api/v1/auth/oauth/:provider/callback","type":0,"val":"v1","end":""},{"old":"/api/v1/auth/oauth/:provider/callback","type":0,"val":"auth","end":""},{"old":"/api/v1/auth/oauth/:provider/callback","type":0,"val":"oauth","end":""},{"old":"/api/v1/auth/oauth/:provider/callback","type":1,"val":"provider","end":""},{"old":"/api/v1/auth/oauth/:provider/callback","type":0,"val":"callback","end":""}],
    types: placeholder as Registry['v1.auth.oauth.callback']['types'],
  },
  'v1.profile.profile.show': {
    methods: ["GET","HEAD"],
    pattern: '/api/v1/account/profile',
    tokens: [{"old":"/api/v1/account/profile","type":0,"val":"api","end":""},{"old":"/api/v1/account/profile","type":0,"val":"v1","end":""},{"old":"/api/v1/account/profile","type":0,"val":"account","end":""},{"old":"/api/v1/account/profile","type":0,"val":"profile","end":""}],
    types: placeholder as Registry['v1.profile.profile.show']['types'],
  },
  'v1.profile.profile.update': {
    methods: ["PATCH"],
    pattern: '/api/v1/account/profile',
    tokens: [{"old":"/api/v1/account/profile","type":0,"val":"api","end":""},{"old":"/api/v1/account/profile","type":0,"val":"v1","end":""},{"old":"/api/v1/account/profile","type":0,"val":"account","end":""},{"old":"/api/v1/account/profile","type":0,"val":"profile","end":""}],
    types: placeholder as Registry['v1.profile.profile.update']['types'],
  },
  'v1.profile.access_tokens.destroy': {
    methods: ["POST"],
    pattern: '/api/v1/account/logout',
    tokens: [{"old":"/api/v1/account/logout","type":0,"val":"api","end":""},{"old":"/api/v1/account/logout","type":0,"val":"v1","end":""},{"old":"/api/v1/account/logout","type":0,"val":"account","end":""},{"old":"/api/v1/account/logout","type":0,"val":"logout","end":""}],
    types: placeholder as Registry['v1.profile.access_tokens.destroy']['types'],
  },
  'v1.media.index': {
    methods: ["GET","HEAD"],
    pattern: '/api/v1/media',
    tokens: [{"old":"/api/v1/media","type":0,"val":"api","end":""},{"old":"/api/v1/media","type":0,"val":"v1","end":""},{"old":"/api/v1/media","type":0,"val":"media","end":""}],
    types: placeholder as Registry['v1.media.index']['types'],
  },
  'v1.media.show': {
    methods: ["GET","HEAD"],
    pattern: '/api/v1/media/:id',
    tokens: [{"old":"/api/v1/media/:id","type":0,"val":"api","end":""},{"old":"/api/v1/media/:id","type":0,"val":"v1","end":""},{"old":"/api/v1/media/:id","type":0,"val":"media","end":""},{"old":"/api/v1/media/:id","type":1,"val":"id","end":""}],
    types: placeholder as Registry['v1.media.show']['types'],
  },
  'v1.media.store': {
    methods: ["POST"],
    pattern: '/api/v1/media',
    tokens: [{"old":"/api/v1/media","type":0,"val":"api","end":""},{"old":"/api/v1/media","type":0,"val":"v1","end":""},{"old":"/api/v1/media","type":0,"val":"media","end":""}],
    types: placeholder as Registry['v1.media.store']['types'],
  },
  'v1.media.from_url': {
    methods: ["POST"],
    pattern: '/api/v1/media/from-url',
    tokens: [{"old":"/api/v1/media/from-url","type":0,"val":"api","end":""},{"old":"/api/v1/media/from-url","type":0,"val":"v1","end":""},{"old":"/api/v1/media/from-url","type":0,"val":"media","end":""},{"old":"/api/v1/media/from-url","type":0,"val":"from-url","end":""}],
    types: placeholder as Registry['v1.media.from_url']['types'],
  },
  'v1.media.destroy': {
    methods: ["DELETE"],
    pattern: '/api/v1/media/:id',
    tokens: [{"old":"/api/v1/media/:id","type":0,"val":"api","end":""},{"old":"/api/v1/media/:id","type":0,"val":"v1","end":""},{"old":"/api/v1/media/:id","type":0,"val":"media","end":""},{"old":"/api/v1/media/:id","type":1,"val":"id","end":""}],
    types: placeholder as Registry['v1.media.destroy']['types'],
  },
} as const satisfies Record<string, AdonisEndpoint>

export { routes }

export const registry = {
  routes,
  $tree: {} as ApiDefinition,
}

declare module '@tuyau/core/types' {
  export interface UserRegistry {
    routes: typeof routes
    $tree: ApiDefinition
  }
}
