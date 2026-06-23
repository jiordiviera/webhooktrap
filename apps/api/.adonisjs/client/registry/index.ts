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
  'inboxes.store': {
    methods: ["POST"],
    pattern: '/api/v1/inboxes',
    tokens: [{"old":"/api/v1/inboxes","type":0,"val":"api","end":""},{"old":"/api/v1/inboxes","type":0,"val":"v1","end":""},{"old":"/api/v1/inboxes","type":0,"val":"inboxes","end":""}],
    types: placeholder as Registry['inboxes.store']['types'],
  },
  'inboxes.index': {
    methods: ["GET","HEAD"],
    pattern: '/api/v1/inboxes',
    tokens: [{"old":"/api/v1/inboxes","type":0,"val":"api","end":""},{"old":"/api/v1/inboxes","type":0,"val":"v1","end":""},{"old":"/api/v1/inboxes","type":0,"val":"inboxes","end":""}],
    types: placeholder as Registry['inboxes.index']['types'],
  },
  'auth.new_account.store': {
    methods: ["POST"],
    pattern: '/api/v1/auth/signup',
    tokens: [{"old":"/api/v1/auth/signup","type":0,"val":"api","end":""},{"old":"/api/v1/auth/signup","type":0,"val":"v1","end":""},{"old":"/api/v1/auth/signup","type":0,"val":"auth","end":""},{"old":"/api/v1/auth/signup","type":0,"val":"signup","end":""}],
    types: placeholder as Registry['auth.new_account.store']['types'],
  },
  'auth.access_tokens.store': {
    methods: ["POST"],
    pattern: '/api/v1/auth/login',
    tokens: [{"old":"/api/v1/auth/login","type":0,"val":"api","end":""},{"old":"/api/v1/auth/login","type":0,"val":"v1","end":""},{"old":"/api/v1/auth/login","type":0,"val":"auth","end":""},{"old":"/api/v1/auth/login","type":0,"val":"login","end":""}],
    types: placeholder as Registry['auth.access_tokens.store']['types'],
  },
  'auth.oauth.providers': {
    methods: ["GET","HEAD"],
    pattern: '/api/v1/auth/oauth/providers',
    tokens: [{"old":"/api/v1/auth/oauth/providers","type":0,"val":"api","end":""},{"old":"/api/v1/auth/oauth/providers","type":0,"val":"v1","end":""},{"old":"/api/v1/auth/oauth/providers","type":0,"val":"auth","end":""},{"old":"/api/v1/auth/oauth/providers","type":0,"val":"oauth","end":""},{"old":"/api/v1/auth/oauth/providers","type":0,"val":"providers","end":""}],
    types: placeholder as Registry['auth.oauth.providers']['types'],
  },
  'auth.oauth.redirect': {
    methods: ["GET","HEAD"],
    pattern: '/api/v1/auth/oauth/:provider/redirect',
    tokens: [{"old":"/api/v1/auth/oauth/:provider/redirect","type":0,"val":"api","end":""},{"old":"/api/v1/auth/oauth/:provider/redirect","type":0,"val":"v1","end":""},{"old":"/api/v1/auth/oauth/:provider/redirect","type":0,"val":"auth","end":""},{"old":"/api/v1/auth/oauth/:provider/redirect","type":0,"val":"oauth","end":""},{"old":"/api/v1/auth/oauth/:provider/redirect","type":1,"val":"provider","end":""},{"old":"/api/v1/auth/oauth/:provider/redirect","type":0,"val":"redirect","end":""}],
    types: placeholder as Registry['auth.oauth.redirect']['types'],
  },
  'auth.oauth.callback': {
    methods: ["GET","HEAD"],
    pattern: '/api/v1/auth/oauth/:provider/callback',
    tokens: [{"old":"/api/v1/auth/oauth/:provider/callback","type":0,"val":"api","end":""},{"old":"/api/v1/auth/oauth/:provider/callback","type":0,"val":"v1","end":""},{"old":"/api/v1/auth/oauth/:provider/callback","type":0,"val":"auth","end":""},{"old":"/api/v1/auth/oauth/:provider/callback","type":0,"val":"oauth","end":""},{"old":"/api/v1/auth/oauth/:provider/callback","type":1,"val":"provider","end":""},{"old":"/api/v1/auth/oauth/:provider/callback","type":0,"val":"callback","end":""}],
    types: placeholder as Registry['auth.oauth.callback']['types'],
  },
  'profile.profile.show': {
    methods: ["GET","HEAD"],
    pattern: '/api/v1/account/profile',
    tokens: [{"old":"/api/v1/account/profile","type":0,"val":"api","end":""},{"old":"/api/v1/account/profile","type":0,"val":"v1","end":""},{"old":"/api/v1/account/profile","type":0,"val":"account","end":""},{"old":"/api/v1/account/profile","type":0,"val":"profile","end":""}],
    types: placeholder as Registry['profile.profile.show']['types'],
  },
  'profile.access_tokens.destroy': {
    methods: ["POST"],
    pattern: '/api/v1/account/logout',
    tokens: [{"old":"/api/v1/account/logout","type":0,"val":"api","end":""},{"old":"/api/v1/account/logout","type":0,"val":"v1","end":""},{"old":"/api/v1/account/logout","type":0,"val":"account","end":""},{"old":"/api/v1/account/logout","type":0,"val":"logout","end":""}],
    types: placeholder as Registry['profile.access_tokens.destroy']['types'],
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
