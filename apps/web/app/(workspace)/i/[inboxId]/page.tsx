'use client'

import { use } from 'react'
import { InboxDetailPage } from '@/features/inbox/components/inbox-detail-page'

export default function InboxPage({ params }: { params: Promise<{ inboxId: string }> }) {
  const { inboxId } = use(params)

  return <InboxDetailPage inboxId={inboxId} />
}