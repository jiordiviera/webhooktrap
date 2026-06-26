import { apiFetch } from '@/lib/api'

export type EventSummary = {
  id: string
  inboxId: string
  method: string
  path: string
  contentType: string | null
  sizeBytes: number
  receivedAt: string
}

export type EventDetail = EventSummary & {
  query: Record<string, string>
  headers: Record<string, string | string[]>
  bodyText: string | null
  bodyJson: Record<string, unknown> | unknown[] | null
  ip: string | null
  createdAt: string
}

export type ReplayRecord = {
  id: string
  eventId: string
  targetUrl: string
  statusCode: number | null
  responseHeaders: Record<string, string> | null
  responseBody: string | null
  durationMs: number | null
  errorCode: string | null
  errorMessage: string | null
  createdAt: string
}

type EventsListResponse = {
  data: {
    events: EventSummary[]
  }
}

type EventDetailResponse = {
  data: {
    event: EventDetail
  }
}

type ReplayResponse = {
  data: {
    replay: ReplayRecord
  }
}

type ReplaysListResponse = {
  data: {
    replays: ReplayRecord[]
  }
}

export async function fetchInboxEvents(token: string | null, inboxId: string) {
  const body = await apiFetch<EventsListResponse>(`/api/v1/inboxes/${inboxId}/events`, { token })
  return body.data.events
}

export async function fetchEvent(token: string | null, eventId: string) {
  const body = await apiFetch<EventDetailResponse>(`/api/v1/events/${eventId}`, { token })
  return body.data.event
}

export async function replayEvent(
  token: string,
  eventId: string,
  input?: { targetUrl?: string }
) {
  const body = await apiFetch<ReplayResponse>(`/api/v1/events/${eventId}/replay`, {
    method: 'POST',
    token,
    body: JSON.stringify(
      input?.targetUrl ? { target_url: input.targetUrl } : {}
    ),
  })
  return body.data.replay
}

export async function fetchEventReplays(token: string | null, eventId: string) {
  const body = await apiFetch<ReplaysListResponse>(`/api/v1/events/${eventId}/replays`, { token })
  return body.data.replays
}