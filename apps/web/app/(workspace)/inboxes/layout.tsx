import type { Metadata } from 'next'

export const metadata: Metadata = {
  title: 'Inboxes — Hookscope',
  description: 'Manage ingest URLs and open webhook inboxes.',
}

export default function InboxesSegmentLayout({ children }: { children: React.ReactNode }) {
  return children
}