'use client'

import { use } from 'react'
import { InboxDetailPage } from '@/features/inbox/components/inbox-detail-page'

export default function InboxPage(props: PageProps<"/i/[inboxId]">) {
  const { inboxId } = use(props.params)

  return <InboxDetailPage inboxId={inboxId} />
}