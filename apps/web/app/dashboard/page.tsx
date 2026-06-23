'use client'

import { useEffect } from 'react'
import { useRouter } from 'next/navigation'
import { Loader } from '@workspace/ui/components/loader'
import { DashboardShell } from '@/app/components/dashboard/dashboard-shell'
import { InboxList } from '@/app/components/dashboard/inbox-list'
import { useAuth } from '@/contexts/auth-context'

export default function DashboardPage() {
  const router = useRouter()
  const { status, isAuthenticated, token } = useAuth()

  useEffect(() => {
    if (status === 'unauthenticated') {
      router.replace('/login?returnTo=/dashboard')
    }
  }, [status, router])

  if (status === 'loading') {
    return (
      <DashboardShell>
        <div className="flex min-h-[40vh] items-center justify-center">
          <Loader layout="centered" label="Loading dashboard" />
        </div>
      </DashboardShell>
    )
  }

  if (!isAuthenticated || !token) {
    return (
      <DashboardShell>
        <div className="flex min-h-[40vh] items-center justify-center">
          <Loader layout="centered" label="Redirecting to sign in" />
        </div>
      </DashboardShell>
    )
  }

  return (
    <DashboardShell>
      <InboxList token={token} />
    </DashboardShell>
  )
}