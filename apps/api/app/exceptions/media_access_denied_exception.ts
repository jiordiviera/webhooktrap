import { Exception } from '@adonisjs/core/exceptions'

export default class MediaAccessDeniedException extends Exception {
  static status = 403
  static code = 'E_MEDIA_ACCESS_DENIED'
}
