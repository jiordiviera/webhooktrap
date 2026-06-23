'use client'

import { InboxList } from '@/app/components/dashboard/inbox-list'
import { useAuth } from '@/contexts/auth-context'

export default function InboxesPage() {
  const { token } = useAuth()

  if (!token) return null

  return <InboxList token={token} />
}