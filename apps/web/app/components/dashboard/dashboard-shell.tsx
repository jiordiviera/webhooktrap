'use client'

import Image from 'next/image'
import Link from 'next/link'
import { usePathname } from 'next/navigation'
import { Button } from '@workspace/ui/components/button'
import { Loader } from '@workspace/ui/components/loader'
import { cn } from '@workspace/ui/lib/utils'
import { useAuth } from '@/contexts/auth-context'

const NAV = [{ href: '/dashboard', label: 'Inboxes' }] as const

export function DashboardShell({ children }: { children: React.ReactNode }) {
  const pathname = usePathname()
  const { user, status, signOut } = useAuth()

  return (
    <div className="min-h-svh bg-background">
      <header className="sticky top-0 z-20 border-b border-border bg-background/95 backdrop-blur-sm">
        <div className="mx-auto flex h-14 max-w-6xl items-center justify-between gap-6 px-5 md:px-8">
          <div className="flex min-w-0 items-center gap-8">
            <Link href="/" className="shrink-0">
              <Image
                src="/logo.png"
                alt="Hookscope"
                width={120}
                height={32}
                className="h-7 w-auto"
                priority
              />
            </Link>

            <nav className="font-ui hidden items-center gap-1 sm:flex" aria-label="Dashboard">
              {NAV.map((item) => (
                <Link
                  key={item.href}
                  href={item.href}
                  className={cn(
                    'rounded-md px-3 py-1.5 text-sm transition-colors',
                    pathname === item.href
                      ? 'bg-secondary text-foreground'
                      : 'text-muted-foreground hover:text-foreground'
                  )}
                  aria-current={pathname === item.href ? 'page' : undefined}
                >
                  {item.label}
                </Link>
              ))}
            </nav>
          </div>

          <div className="font-ui flex items-center gap-3">
            {status === 'loading' ? (
              <Loader size="sm" tone="muted" label="Checking session" />
            ) : user ? (
              <>
                <span className="hidden max-w-40 truncate text-sm text-muted-foreground sm:inline">
                  {user.fullName ?? user.email}
                </span>
                <Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  onClick={() => void signOut()}
                >
                  Sign out
                </Button>
              </>
            ) : (
              <Button variant="outline" size="sm" asChild>
                <Link href="/login">Sign in</Link>
              </Button>
            )}
          </div>
        </div>
      </header>

      <main className="mx-auto max-w-6xl px-5 py-8 md:px-8 md:py-10">{children}</main>
    </div>
  )
}