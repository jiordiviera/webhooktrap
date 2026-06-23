import type Inbox from '#models/inbox'
import { DateTime } from 'luxon'

type InboxWithMeta = Inbox & {
  $extras: {
    events_count?: string | number
  }
}

export function serializeInbox(inbox: InboxWithMeta) {
  const lastEvent = inbox.events?.[0]
  const eventsCount = Number(inbox.$extras.events_count ?? 0)

  return {
    id: inbox.id,
    name: inbox.name,
    expiresAt: inbox.expiresAt,
    ingestUrl: `/i/${inbox.id}`,
    defaultReplayUrl: inbox.defaultReplayUrl,
    eventsCount,
    lastEventAt: lastEvent?.receivedAt ?? null,
    createdAt: inbox.createdAt,
    updatedAt: inbox.updatedAt,
  }
}

export function isInboxExpired(expiresAt: DateTime | null) {
  if (!expiresAt) return false
  return expiresAt < DateTime.now()
}