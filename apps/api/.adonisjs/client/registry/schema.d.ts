/* eslint-disable prettier/prettier */
/// <reference path="../manifest.d.ts" />

import type { ExtractBody, ExtractErrorResponse, ExtractQuery, ExtractQueryForGet, ExtractResponse } from '@tuyau/core/types'
import type { InferInput, SimpleError } from '@vinejs/vine/types'

export type ParamValue = string | number | bigint | boolean

export interface Registry {
  'ingest': {
    methods: ["GET","POST","PUT","PATCH","DELETE"]
    pattern: '/i/:inboxId'
    types: {
      body: {}
      paramsTuple: [ParamValue]
      params: { inboxId: ParamValue }
      query: {}
      response: ExtractResponse<Awaited<ReturnType<import('#controllers/ingest_controller').default['handle']>>>
      errorResponse: ExtractErrorResponse<Awaited<ReturnType<import('#controllers/ingest_controller').default['handle']>>>
    }
  }
  'v1.2fa.generate': {
    methods: ["POST"]
    pattern: '/api/v1/2fa/generate'
    types: {
      body: {}
      paramsTuple: []
      params: {}
      query: {}
      response: ExtractResponse<Awaited<ReturnType<import('#controllers/two_factor_auth_controller').default['generate']>>>
      errorResponse: ExtractErrorResponse<Awaited<ReturnType<import('#controllers/two_factor_auth_controller').default['generate']>>>
    }
  }
  'v1.2fa.verify': {
    methods: ["POST"]
    pattern: '/api/v1/2fa/verify'
    types: {
      body: ExtractBody<InferInput<(typeof import('#validators/verify_otp').verifyOtpValidator)>>
      paramsTuple: []
      params: {}
      query: ExtractQuery<InferInput<(typeof import('#validators/verify_otp').verifyOtpValidator)>>
      response: ExtractResponse<Awaited<ReturnType<import('#controllers/two_factor_auth_controller').default['verify']>>>
      errorResponse: ExtractErrorResponse<Awaited<ReturnType<import('#controllers/two_factor_auth_controller').default['verify']>>> | { status: 422; response: { errors: SimpleError[] } }
    }
  }
  'v1.2fa.generate_recovery_codes': {
    methods: ["POST"]
    pattern: '/api/v1/2fa/generate-recovery-codes'
    types: {
      body: {}
      paramsTuple: []
      params: {}
      query: {}
      response: ExtractResponse<Awaited<ReturnType<import('#controllers/two_factor_auth_controller').default['generateRecoveryCodes']>>>
      errorResponse: ExtractErrorResponse<Awaited<ReturnType<import('#controllers/two_factor_auth_controller').default['generateRecoveryCodes']>>>
    }
  }
  'v1.2fa.disable': {
    methods: ["POST"]
    pattern: '/api/v1/2fa/disable'
    types: {
      body: {}
      paramsTuple: []
      params: {}
      query: {}
      response: ExtractResponse<Awaited<ReturnType<import('#controllers/two_factor_auth_controller').default['disable']>>>
      errorResponse: ExtractErrorResponse<Awaited<ReturnType<import('#controllers/two_factor_auth_controller').default['disable']>>>
    }
  }
  'v1.inboxes.store': {
    methods: ["POST"]
    pattern: '/api/v1/inboxes'
    types: {
      body: {}
      paramsTuple: []
      params: {}
      query: {}
      response: ExtractResponse<Awaited<ReturnType<import('#controllers/inboxes_controller').default['store']>>>
      errorResponse: ExtractErrorResponse<Awaited<ReturnType<import('#controllers/inboxes_controller').default['store']>>>
    }
  }
  'v1.inboxes.index': {
    methods: ["GET","HEAD"]
    pattern: '/api/v1/inboxes'
    types: {
      body: {}
      paramsTuple: []
      params: {}
      query: {}
      response: ExtractResponse<Awaited<ReturnType<import('#controllers/inboxes_controller').default['index']>>>
      errorResponse: ExtractErrorResponse<Awaited<ReturnType<import('#controllers/inboxes_controller').default['index']>>>
    }
  }
  'v1.auth.new_account.store': {
    methods: ["POST"]
    pattern: '/api/v1/auth/signup'
    types: {
      body: ExtractBody<InferInput<(typeof import('#validators/user').signupValidator)>>
      paramsTuple: []
      params: {}
      query: ExtractQuery<InferInput<(typeof import('#validators/user').signupValidator)>>
      response: ExtractResponse<Awaited<ReturnType<import('#controllers/new_account_controller').default['store']>>>
      errorResponse: ExtractErrorResponse<Awaited<ReturnType<import('#controllers/new_account_controller').default['store']>>> | { status: 422; response: { errors: SimpleError[] } }
    }
  }
  'v1.auth.access_tokens.store': {
    methods: ["POST"]
    pattern: '/api/v1/auth/login'
    types: {
      body: ExtractBody<InferInput<(typeof import('#validators/user').loginValidator)>>
      paramsTuple: []
      params: {}
      query: ExtractQuery<InferInput<(typeof import('#validators/user').loginValidator)>>
      response: ExtractResponse<Awaited<ReturnType<import('#controllers/access_tokens_controller').default['store']>>>
      errorResponse: ExtractErrorResponse<Awaited<ReturnType<import('#controllers/access_tokens_controller').default['store']>>> | { status: 422; response: { errors: SimpleError[] } }
    }
  }
  'v1.auth.oauth.providers': {
    methods: ["GET","HEAD"]
    pattern: '/api/v1/auth/oauth/providers'
    types: {
      body: {}
      paramsTuple: []
      params: {}
      query: {}
      response: ExtractResponse<Awaited<ReturnType<import('#controllers/oauth_controller').default['providers']>>>
      errorResponse: ExtractErrorResponse<Awaited<ReturnType<import('#controllers/oauth_controller').default['providers']>>>
    }
  }
  'v1.auth.oauth.redirect': {
    methods: ["GET","HEAD"]
    pattern: '/api/v1/auth/oauth/:provider/redirect'
    types: {
      body: {}
      paramsTuple: [ParamValue]
      params: { provider: ParamValue }
      query: {}
      response: ExtractResponse<Awaited<ReturnType<import('#controllers/oauth_controller').default['redirect']>>>
      errorResponse: ExtractErrorResponse<Awaited<ReturnType<import('#controllers/oauth_controller').default['redirect']>>>
    }
  }
  'v1.auth.oauth.callback': {
    methods: ["GET","HEAD"]
    pattern: '/api/v1/auth/oauth/:provider/callback'
    types: {
      body: {}
      paramsTuple: [ParamValue]
      params: { provider: ParamValue }
      query: {}
      response: ExtractResponse<Awaited<ReturnType<import('#controllers/oauth_controller').default['callback']>>>
      errorResponse: ExtractErrorResponse<Awaited<ReturnType<import('#controllers/oauth_controller').default['callback']>>>
    }
  }
  'v1.profile.profile.show': {
    methods: ["GET","HEAD"]
    pattern: '/api/v1/account/profile'
    types: {
      body: {}
      paramsTuple: []
      params: {}
      query: {}
      response: ExtractResponse<Awaited<ReturnType<import('#controllers/profile_controller').default['show']>>>
      errorResponse: ExtractErrorResponse<Awaited<ReturnType<import('#controllers/profile_controller').default['show']>>>
    }
  }
  'v1.profile.profile.update': {
    methods: ["PATCH"]
    pattern: '/api/v1/account/profile'
    types: {
      body: ExtractBody<InferInput<(typeof import('#validators/user').updateProfileValidator)>>
      paramsTuple: []
      params: {}
      query: ExtractQuery<InferInput<(typeof import('#validators/user').updateProfileValidator)>>
      response: ExtractResponse<Awaited<ReturnType<import('#controllers/profile_controller').default['update']>>>
      errorResponse: ExtractErrorResponse<Awaited<ReturnType<import('#controllers/profile_controller').default['update']>>> | { status: 422; response: { errors: SimpleError[] } }
    }
  }
  'v1.profile.access_tokens.destroy': {
    methods: ["POST"]
    pattern: '/api/v1/account/logout'
    types: {
      body: {}
      paramsTuple: []
      params: {}
      query: {}
      response: ExtractResponse<Awaited<ReturnType<import('#controllers/access_tokens_controller').default['destroy']>>>
      errorResponse: ExtractErrorResponse<Awaited<ReturnType<import('#controllers/access_tokens_controller').default['destroy']>>>
    }
  }
  'v1.media.index': {
    methods: ["GET","HEAD"]
    pattern: '/api/v1/media'
    types: {
      body: {}
      paramsTuple: []
      params: {}
      query: {}
      response: ExtractResponse<Awaited<ReturnType<import('#controllers/media_controller').default['index']>>>
      errorResponse: ExtractErrorResponse<Awaited<ReturnType<import('#controllers/media_controller').default['index']>>>
    }
  }
  'v1.media.show': {
    methods: ["GET","HEAD"]
    pattern: '/api/v1/media/:id'
    types: {
      body: {}
      paramsTuple: [ParamValue]
      params: { id: ParamValue }
      query: {}
      response: ExtractResponse<Awaited<ReturnType<import('#controllers/media_controller').default['show']>>>
      errorResponse: ExtractErrorResponse<Awaited<ReturnType<import('#controllers/media_controller').default['show']>>>
    }
  }
  'v1.media.store': {
    methods: ["POST"]
    pattern: '/api/v1/media'
    types: {
      body: {}
      paramsTuple: []
      params: {}
      query: {}
      response: ExtractResponse<Awaited<ReturnType<import('#controllers/media_controller').default['store']>>>
      errorResponse: ExtractErrorResponse<Awaited<ReturnType<import('#controllers/media_controller').default['store']>>>
    }
  }
  'v1.media.from_url': {
    methods: ["POST"]
    pattern: '/api/v1/media/from-url'
    types: {
      body: ExtractBody<InferInput<(typeof import('#validators/media_validator').attachFromUrlValidator)>>
      paramsTuple: []
      params: {}
      query: ExtractQuery<InferInput<(typeof import('#validators/media_validator').attachFromUrlValidator)>>
      response: ExtractResponse<Awaited<ReturnType<import('#controllers/media_controller').default['storeFromUrl']>>>
      errorResponse: ExtractErrorResponse<Awaited<ReturnType<import('#controllers/media_controller').default['storeFromUrl']>>> | { status: 422; response: { errors: SimpleError[] } }
    }
  }
  'v1.media.destroy': {
    methods: ["DELETE"]
    pattern: '/api/v1/media/:id'
    types: {
      body: {}
      paramsTuple: [ParamValue]
      params: { id: ParamValue }
      query: {}
      response: ExtractResponse<Awaited<ReturnType<import('#controllers/media_controller').default['destroy']>>>
      errorResponse: ExtractErrorResponse<Awaited<ReturnType<import('#controllers/media_controller').default['destroy']>>>
    }
  }
}
