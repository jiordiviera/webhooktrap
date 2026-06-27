'use client'

import { DashboardHome } from '@/features/dashboard/components/dashboard-home'
import { useAuth } from '@/contexts/auth-context'

export default function DashboardPage() {
  const { token } = useAuth()

  if (!token) return null

  return <DashboardHome token={token} />
}