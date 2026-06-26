'use client'

import { use } from 'react'
import { InboxDetailPage } from '@/app/components/inbox/inbox-detail-page'
import { useAuth } from '@/contexts/auth-context'

export default function InboxPage({ params }: { params: Promise<{ inboxId: string }> }) {
  const { inboxId } = use(params)
  const { token } = useAuth()

  if (!token) return null

  return <InboxDetailPage inboxId={inboxId} token={token} />
}