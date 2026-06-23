'use client'

import { useEffect } from 'react'
import { useRouter } from 'next/navigation'
import { Loader } from '@workspace/ui/components/loader'
import { DashboardShell } from '@/app/components/dashboard/dashboard-shell'
import { useAuth } from '@/contexts/auth-context'

export function WorkspaceLayout({ children }: { children: React.ReactNode }) {
  const router = useRouter()
  const { status, isAuthenticated } = useAuth()

  useEffect(() => {
    if (status === 'unauthenticated') {
      router.replace('/login?returnTo=/dashboard')
    }
  }, [status, router])

  if (status === 'loading') {
    return (
      <DashboardShell>
        <div className="flex min-h-[40vh] items-center justify-center">
          <Loader layout="centered" label="Loading workspace" />
        </div>
      </DashboardShell>
    )
  }

  if (!isAuthenticated) {
    return (
      <DashboardShell>
        <div className="flex min-h-[40vh] items-center justify-center">
          <Loader layout="centered" label="Redirecting to sign in" />
        </div>
      </DashboardShell>
    )
  }

  return <DashboardShell>{children}</DashboardShell>
}