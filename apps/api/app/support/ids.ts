import { customAlphabet } from 'nanoid'
import { ulid } from 'ulid'

const alphabet = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz'
const nanoid12 = customAlphabet(alphabet, 12)
const nanoid32 = customAlphabet(alphabet, 32)

export function inboxId() {
  return nanoid12()
}

export function eventId() {
  return `evt_${ulid()}`
}

export function replayId() {
  return `rpl_${ulid()}`
}

export function shareToken() {
  return nanoid32()
}
