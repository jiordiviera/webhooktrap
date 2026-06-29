'use client'

import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { fetchEvent, fetchEventReplays } from '@/lib/events'

export function eventDetailQueryKey(eventId: string) {
  return ['event', eventId] as const
}

export function eventReplaysQueryKey(eventId: string) {
  return ['event-replays', eventId] as const
}

export function useEventDetailQuery(eventId: string | null) {
  return useQuery({
    queryKey: eventId ? eventDetailQueryKey(eventId) : ['event', null],
    queryFn: () => fetchEvent(eventId!),
    enabled: Boolean(eventId),
    placeholderData: keepPreviousData,
  })
}

export function useEventReplaysQuery(eventId: string | null) {
  return useQuery({
    queryKey: eventId ? eventReplaysQueryKey(eventId) : ['event-replays', null],
    queryFn: () => fetchEventReplays(eventId!),
    enabled: Boolean(eventId),
    placeholderData: keepPreviousData,
  })
}