import '@adonisjs/core/types/http'

type ParamValue = string | number | bigint | boolean

export type ScannedRoutes = {
  ALL: {
    'ingest': { paramsTuple: [ParamValue]; params: {'inboxId': ParamValue} }
    'inboxes.store': { paramsTuple?: []; params?: {} }
    'inboxes.index': { paramsTuple?: []; params?: {} }
    'auth.new_account.store': { paramsTuple?: []; params?: {} }
    'auth.access_tokens.store': { paramsTuple?: []; params?: {} }
    'auth.oauth.providers': { paramsTuple?: []; params?: {} }
    'auth.oauth.redirect': { paramsTuple: [ParamValue]; params: {'provider': ParamValue} }
    'auth.oauth.callback': { paramsTuple: [ParamValue]; params: {'provider': ParamValue} }
    'profile.profile.show': { paramsTuple?: []; params?: {} }
    'profile.access_tokens.destroy': { paramsTuple?: []; params?: {} }
  }
  GET: {
    'ingest': { paramsTuple: [ParamValue]; params: {'inboxId': ParamValue} }
    'inboxes.index': { paramsTuple?: []; params?: {} }
    'auth.oauth.providers': { paramsTuple?: []; params?: {} }
    'auth.oauth.redirect': { paramsTuple: [ParamValue]; params: {'provider': ParamValue} }
    'auth.oauth.callback': { paramsTuple: [ParamValue]; params: {'provider': ParamValue} }
    'profile.profile.show': { paramsTuple?: []; params?: {} }
  }
  HEAD: {
    'inboxes.index': { paramsTuple?: []; params?: {} }
    'auth.oauth.providers': { paramsTuple?: []; params?: {} }
    'auth.oauth.redirect': { paramsTuple: [ParamValue]; params: {'provider': ParamValue} }
    'auth.oauth.callback': { paramsTuple: [ParamValue]; params: {'provider': ParamValue} }
    'profile.profile.show': { paramsTuple?: []; params?: {} }
  }
  POST: {
    'ingest': { paramsTuple: [ParamValue]; params: {'inboxId': ParamValue} }
    'inboxes.store': { paramsTuple?: []; params?: {} }
    'auth.new_account.store': { paramsTuple?: []; params?: {} }
    'auth.access_tokens.store': { paramsTuple?: []; params?: {} }
    'profile.access_tokens.destroy': { paramsTuple?: []; params?: {} }
  }
  PUT: {
    'ingest': { paramsTuple: [ParamValue]; params: {'inboxId': ParamValue} }
  }
  PATCH: {
    'ingest': { paramsTuple: [ParamValue]; params: {'inboxId': ParamValue} }
  }
  DELETE: {
    'ingest': { paramsTuple: [ParamValue]; params: {'inboxId': ParamValue} }
  }
}
declare module '@adonisjs/core/types/http' {
  export interface RoutesList extends ScannedRoutes {}
}