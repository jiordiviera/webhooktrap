import type { Metadata } from 'next'

export const metadata: Metadata = {
  title: 'Dashboard — Hookscope',
  description: 'Manage your saved webhook inboxes and ingest URLs.',
}

export default function DashboardLayout({ children }: { children: React.ReactNode }) {
  return children
}