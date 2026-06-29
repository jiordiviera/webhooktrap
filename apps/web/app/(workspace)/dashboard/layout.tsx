import type { Metadata } from 'next'
import { productName } from '@/lib/config'

export const metadata: Metadata = {
  title: `Dashboard — ${productName}`,
  description: 'Overview of your webhook inboxes, events, and replay activity.',
}

export default function DashboardSegmentLayout({ children }: { children: React.ReactNode }) {
  return children
}