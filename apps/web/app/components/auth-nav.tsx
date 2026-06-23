'use client'

import Link from 'next/link'
import { Button } from '@workspace/ui/components/button'
import { Loader } from '@workspace/ui/components/loader'
import { useAuth } from '@/contexts/auth-context'

export function AuthNav() {
  const { user, status, signOut, isAuthenticated } = useAuth()

  if (status === 'loading') {
    return <Loader size="sm" tone="muted" label="Checking session" />
  }

  if (isAuthenticated && user) {
    return (
      <div className="flex items-center gap-4">
        <span className="font-ui text-sm text-muted-foreground">
          {user.fullName ?? user.email}
        </span>
        <Button
          type="button"
          variant="ghost"
          size="sm"
          className="font-ui"
          onClick={() => void signOut()}
        >
          Sign out
        </Button>
      </div>
    )
  }

  return (
    <Link
      href="/login"
      className="text-muted-foreground transition-colors hover:text-primary"
    >
      Sign in
    </Link>
  )
}