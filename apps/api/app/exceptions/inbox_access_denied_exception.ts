import { Exception } from '@adonisjs/core/exceptions'

export default class InboxAccessDeniedException extends Exception {
  static status = 403
  static code = 'E_INBOX_ACCESS_DENIED'
}