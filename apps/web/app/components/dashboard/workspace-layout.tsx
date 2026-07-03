'use client'

import { useEffect } from 'react'
import Link from 'next/link'
import { usePathname, useRouter } from 'next/navigation'
import { Loader } from '@workspace/ui/components/loader'
import { DashboardShell } from '@/app/components/dashboard/dashboard-shell'
import { WorkspaceSkeleton } from '@/app/components/dashboard/workspace-skeleton'
import { useAuth } from '@/contexts/auth-context'
import { productName } from '@/lib/config'

// Anonymous inboxes are core to the product ("minutes to value, no signup
// required") — this route must stay reachable without an account, unlike
// the rest of the workspace.
function isPublicWorkspacePath(pathname: string) {
  return pathname.startsWith('/i/')
}

function PublicInboxShell({ children }: { children: React.ReactNode }) {
  return (
    <div className="bg-background min-h-svh">
      <header className="border-b border-border px-4 py-4 sm:px-6">
        <Link
          href="/"
          className="text-sm font-semibold text-primary transition-colors hover:text-primary/80"
        >
          {productName}
        </Link>
      </header>
      <main className="p-4 sm:p-6">{children}</main>
    </div>
  )
}

export function WorkspaceLayout({ children }: { children: React.ReactNode }) {
  const router = useRouter()
  const pathname = usePathname()
  const { status, isAuthenticated } = useAuth()
  const isPublicPath = isPublicWorkspacePath(pathname)

  useEffect(() => {
    if (status === 'unauthenticated' && !isPublicPath) {
      router.replace('/login?returnTo=/dashboard')
    }
  }, [status, isPublicPath, router])

  if (status === 'loading') {
    return isPublicPath ? <PublicInboxShell>{children}</PublicInboxShell> : <WorkspaceSkeleton />
  }

  if (!isAuthenticated) {
    if (isPublicPath) {
      return <PublicInboxShell>{children}</PublicInboxShell>
    }

    return (
      <div className="bg-background flex min-h-svh items-center justify-center">
        <Loader layout="centered" label="Redirecting to sign in" />
      </div>
    )
  }

  return <DashboardShell>{children}</DashboardShell>
}
