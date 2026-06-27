'use client'

import { use } from 'react'
import { InboxDetailPage } from '@/features/inbox/components/inbox-detail-page'
import { InboxDetailSkeleton } from '@/features/inbox/components/inbox-detail-skeleton'
import { useAuth } from '@/contexts/auth-context'

export default function InboxPage({ params }: { params: Promise<{ inboxId: string }> }) {
  const { inboxId } = use(params)
  const { token, status } = useAuth()

  if (status === 'loading') {
    return <InboxDetailSkeleton />
  }

  if (!token) return null

  return <InboxDetailPage inboxId={inboxId} token={token} />
}