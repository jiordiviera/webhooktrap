'use client'

import { useQuery } from '@tanstack/react-query'
import { fetchInbox } from '@/lib/inboxes'

export function inboxQueryKey(inboxId: string) {
  return ['inbox', inboxId] as const
}

export function useInboxQuery(token: string, inboxId: string) {
  return useQuery({
    queryKey: inboxQueryKey(inboxId),
    queryFn: () => fetchInbox(token, inboxId),
    enabled: Boolean(token && inboxId),
  })
}