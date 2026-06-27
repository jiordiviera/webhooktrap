import type { DataTableParams } from '@/features/data-table/types'
import type { EventSummaryDTO, EventDetailDTO, ReplayDTO } from '@workspace/types'
import { apiFetch } from '@/lib/api'
import { buildListQueryString, parsePaginatedResponse } from '@/lib/list-query'

export type EventSummary = EventSummaryDTO
export type EventDetail = EventDetailDTO
export type ReplayRecord = ReplayDTO

type EventsListResponse = {
  data: {
    events: EventSummaryDTO[]
  }
}

type EventDetailResponse = {
  data: {
    event: EventDetailDTO
  }
}

type ReplayResponse = {
  data: {
    replay: ReplayDTO
  }
}

type ReplaysListResponse = {
  data: {
    replays: ReplayDTO[]
  }
}

export async function fetchInboxEvents(inboxId: string) {
  const page = await fetchInboxEventsPage(inboxId, {
    page: 1,
    pageSize: 100,
    sort: { id: 'receivedAt', desc: true },
    filters: {},
    search: '',
  })
  return page.rows
}

export async function fetchInboxEventsPage(
  inboxId: string,
  params: DataTableParams
) {
  const body = await apiFetch<EventsListResponse & { data: { meta?: unknown } }>(
    `/api/v1/inboxes/${inboxId}/events${buildListQueryString(params)}`
  )
  return parsePaginatedResponse<'events', EventSummaryDTO>(body, 'events')
}

export async function fetchEvent(eventId: string) {
  const body = await apiFetch<EventDetailResponse>(`/api/v1/events/${eventId}`)
  return body.data.event
}

export async function replayEvent(
  eventId: string,
  input?: { targetUrl?: string }
) {
  const body = await apiFetch<ReplayResponse>(`/api/v1/events/${eventId}/replay`, {
    method: 'POST',
    body: JSON.stringify(
      input?.targetUrl ? { target_url: input.targetUrl } : {}
    ),
  })
  return body.data.replay
}

export async function fetchEventReplays(eventId: string) {
  const body = await apiFetch<ReplaysListResponse>(`/api/v1/events/${eventId}/replays`)
  return body.data.replays
}

type ShareTokenResponse = {
  data: {
    token: string
  }
}

export async function generateShareToken(eventId: string) {
  const body = await apiFetch<ShareTokenResponse>(`/api/v1/events/${eventId}/share`, {
    method: 'POST',
  })
  return body.data.token
}