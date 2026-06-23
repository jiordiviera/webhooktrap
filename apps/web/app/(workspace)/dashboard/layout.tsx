import type { Metadata } from 'next'

export const metadata: Metadata = {
  title: 'Dashboard — Hookscope',
  description: 'Overview of your webhook inboxes, events, and replay activity.',
}

export default function DashboardSegmentLayout({ children }: { children: React.ReactNode }) {
  return children
}