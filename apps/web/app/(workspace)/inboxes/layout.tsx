import type { Metadata } from 'next'
import { productName } from '@/lib/config'

export const metadata: Metadata = {
  title: `Inboxes — ${productName}`,
  description: 'Manage ingest URLs and open webhook inboxes.',
}

export default function InboxesSegmentLayout({ children }: { children: React.ReactNode }) {
  return children
}