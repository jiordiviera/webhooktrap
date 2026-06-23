'use client'

import { DashboardHome } from '@/app/components/dashboard/dashboard-home'
import { useAuth } from '@/contexts/auth-context'

export default function DashboardPage() {
  const { token } = useAuth()

  if (!token) return null

  return <DashboardHome token={token} />
}