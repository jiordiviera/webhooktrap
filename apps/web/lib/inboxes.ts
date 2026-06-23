import { apiFetch } from '@/lib/api'

export type InboxSummary = {
  id: string
  name: string
  expiresAt: string | null
  ingestUrl: string
  defaultReplayUrl: string | null
  eventsCount: number
  lastEventAt: string | null
  createdAt: string
  updatedAt: string | null
}

type InboxListResponse = {
  data: {
    inboxes: InboxSummary[]
  }
}

type InboxCreateResponse = {
  data: {
    inbox: InboxSummary
  }
}

export async function fetchInboxes(token: string) {
  const body = await apiFetch<InboxListResponse>('/api/v1/inboxes', { token })
  return body.data.inboxes
}

export async function createInbox(token: string) {
  const body = await apiFetch<InboxCreateResponse>('/api/v1/inboxes', {
    method: 'POST',
    token,
  })
  return body.data.inbox
}

export function inboxPublicUrl(ingestPath: string) {
  const webUrl = process.env.NEXT_PUBLIC_WEB_URL ?? 'http://localhost:7777'
  return `${webUrl}${ingestPath}`
}

export function formatRelativeTime(iso: string | null) {
  if (!iso) return 'No events yet'

  const date = new Date(iso)
  const delta = Date.now() - date.getTime()
  const seconds = Math.floor(delta / 1000)

  if (seconds < 60) return 'Just now'

  const minutes = Math.floor(seconds / 60)
  if (minutes < 60) return `${minutes}m ago`

  const hours = Math.floor(minutes / 60)
  if (hours < 24) return `${hours}h ago`

  const days = Math.floor(hours / 24)
  if (days < 7) return `${days}d ago`

  return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' })
}