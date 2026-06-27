import type { DataTableParams } from '@/features/data-table/types'
import { apiFetch } from '@/lib/api'
import { buildListQueryString, parsePaginatedResponse } from '@/lib/list-query'

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

export async function fetchInboxes() {
  const page = await fetchInboxesPage({
    page: 1,
    pageSize: 100,
    sort: null,
    filters: {},
    search: '',
  })
  return page.rows
}

export async function fetchInboxesPage(params: DataTableParams) {
  const body = await apiFetch<InboxListResponse & { data: { meta?: unknown } }>(
    `/api/v1/inboxes${buildListQueryString(params)}`
  )
  return parsePaginatedResponse<'inboxes', InboxSummary>(body, 'inboxes')
}

export async function createInbox(input: { name: string }) {
  const body = await apiFetch<InboxCreateResponse>('/api/v1/inboxes', {
    method: 'POST',
    body: JSON.stringify({ name: input.name }),
  })
  return body.data.inbox
}

export async function fetchInbox(inboxId: string) {
  const body = await apiFetch<InboxCreateResponse>(`/api/v1/inboxes/${inboxId}`)
  return body.data.inbox
}

export async function updateInbox(
  inboxId: string,
  input: { name?: string; defaultReplayUrl?: string | null }
) {
  const body = await apiFetch<InboxCreateResponse>(`/api/v1/inboxes/${inboxId}`, {
    method: 'PATCH',
    body: JSON.stringify({
      ...(input.name !== undefined ? { name: input.name } : {}),
      ...(input.defaultReplayUrl !== undefined
        ? { default_replay_url: input.defaultReplayUrl }
        : {}),
    }),
  })
  return body.data.inbox
}

export async function deleteInbox(inboxId: string) {
  await apiFetch<{ data: { deleted: boolean } }>(`/api/v1/inboxes/${inboxId}`, {
    method: 'DELETE',
  })
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