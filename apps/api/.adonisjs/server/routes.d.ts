import '@adonisjs/core/types/http'

type ParamValue = string | number | bigint | boolean

export type ScannedRoutes = {
  ALL: {
    'ingest': { paramsTuple: [ParamValue]; params: {'inboxId': ParamValue} }
    'v1.2fa.generate': { paramsTuple?: []; params?: {} }
    'v1.2fa.verify': { paramsTuple?: []; params?: {} }
    'v1.2fa.generate_recovery_codes': { paramsTuple?: []; params?: {} }
    'v1.2fa.disable': { paramsTuple?: []; params?: {} }
    'v1.inboxes.store': { paramsTuple?: []; params?: {} }
    'v1.inboxes.index': { paramsTuple?: []; params?: {} }
    'v1.auth.new_account.store': { paramsTuple?: []; params?: {} }
    'v1.auth.access_tokens.store': { paramsTuple?: []; params?: {} }
    'v1.auth.oauth.providers': { paramsTuple?: []; params?: {} }
    'v1.auth.oauth.redirect': { paramsTuple: [ParamValue]; params: {'provider': ParamValue} }
    'v1.auth.oauth.callback': { paramsTuple: [ParamValue]; params: {'provider': ParamValue} }
    'v1.profile.profile.show': { paramsTuple?: []; params?: {} }
    'v1.profile.profile.update': { paramsTuple?: []; params?: {} }
    'v1.profile.access_tokens.destroy': { paramsTuple?: []; params?: {} }
    'v1.media.index': { paramsTuple?: []; params?: {} }
    'v1.media.show': { paramsTuple: [ParamValue]; params: {'id': ParamValue} }
    'v1.media.store': { paramsTuple?: []; params?: {} }
    'v1.media.from_url': { paramsTuple?: []; params?: {} }
    'v1.media.destroy': { paramsTuple: [ParamValue]; params: {'id': ParamValue} }
  }
  GET: {
    'ingest': { paramsTuple: [ParamValue]; params: {'inboxId': ParamValue} }
    'v1.inboxes.index': { paramsTuple?: []; params?: {} }
    'v1.auth.oauth.providers': { paramsTuple?: []; params?: {} }
    'v1.auth.oauth.redirect': { paramsTuple: [ParamValue]; params: {'provider': ParamValue} }
    'v1.auth.oauth.callback': { paramsTuple: [ParamValue]; params: {'provider': ParamValue} }
    'v1.profile.profile.show': { paramsTuple?: []; params?: {} }
    'v1.media.index': { paramsTuple?: []; params?: {} }
    'v1.media.show': { paramsTuple: [ParamValue]; params: {'id': ParamValue} }
  }
  HEAD: {
    'v1.inboxes.index': { paramsTuple?: []; params?: {} }
    'v1.auth.oauth.providers': { paramsTuple?: []; params?: {} }
    'v1.auth.oauth.redirect': { paramsTuple: [ParamValue]; params: {'provider': ParamValue} }
    'v1.auth.oauth.callback': { paramsTuple: [ParamValue]; params: {'provider': ParamValue} }
    'v1.profile.profile.show': { paramsTuple?: []; params?: {} }
    'v1.media.index': { paramsTuple?: []; params?: {} }
    'v1.media.show': { paramsTuple: [ParamValue]; params: {'id': ParamValue} }
  }
  POST: {
    'ingest': { paramsTuple: [ParamValue]; params: {'inboxId': ParamValue} }
    'v1.2fa.generate': { paramsTuple?: []; params?: {} }
    'v1.2fa.verify': { paramsTuple?: []; params?: {} }
    'v1.2fa.generate_recovery_codes': { paramsTuple?: []; params?: {} }
    'v1.2fa.disable': { paramsTuple?: []; params?: {} }
    'v1.inboxes.store': { paramsTuple?: []; params?: {} }
    'v1.auth.new_account.store': { paramsTuple?: []; params?: {} }
    'v1.auth.access_tokens.store': { paramsTuple?: []; params?: {} }
    'v1.profile.access_tokens.destroy': { paramsTuple?: []; params?: {} }
    'v1.media.store': { paramsTuple?: []; params?: {} }
    'v1.media.from_url': { paramsTuple?: []; params?: {} }
  }
  PUT: {
    'ingest': { paramsTuple: [ParamValue]; params: {'inboxId': ParamValue} }
  }
  PATCH: {
    'ingest': { paramsTuple: [ParamValue]; params: {'inboxId': ParamValue} }
    'v1.profile.profile.update': { paramsTuple?: []; params?: {} }
  }
  DELETE: {
    'ingest': { paramsTuple: [ParamValue]; params: {'inboxId': ParamValue} }
    'v1.media.destroy': { paramsTuple: [ParamValue]; params: {'id': ParamValue} }
  }
}
declare module '@adonisjs/core/types/http' {
  export interface RoutesList extends ScannedRoutes {}
}