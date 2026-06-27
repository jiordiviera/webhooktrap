import { apiFetch } from '@/lib/api'
import type { EventDetail, ReplayRecord } from '@/lib/events'

type SharedEventResponse = {
  data: {
    event: EventDetail
    replays: ReplayRecord[]
  }
}

export async function fetchSharedEvent(token: string) {
  const body = await apiFetch<SharedEventResponse>(`/api/v1/s/${token}`, { skipAuth: true })
  return body.data
}

export type { EventDetail, ReplayRecord }
