'use client'

import { IconBell, IconBuilding, IconSettings } from '@tabler/icons-react'
import { useAuth } from '@/contexts/auth-context'

function formatDate(dateStr?: string): string {
  if (!dateStr) return '—'
  return new Intl.DateTimeFormat('en', {
    month: 'long',
    year: 'numeric',
  }).format(new Date(dateStr))
}

export function SettingsPage() {
  const { user } = useAuth()

  if (!user) return null

  return (
    <div className="mx-auto w-full max-w-3xl">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight text-foreground">Settings</h1>
        <p className="mt-1 text-sm text-muted-foreground">
          Manage your account, notification preferences, and workspace settings.
        </p>
      </div>

      <div className="mt-8 space-y-6">
        <section className="rounded-2xl border border-border bg-card">
          <div className="border-b border-border px-6 py-5">
            <h2 className="flex items-center gap-2 text-sm font-semibold text-foreground">
              <IconBuilding className="size-4" stroke={1.8} />
              Account
            </h2>
            <p className="mt-1 text-sm text-muted-foreground">
              Your account details and region.
            </p>
          </div>
          <dl className="grid gap-5 px-6 py-5 sm:grid-cols-2">
            <div>
              <dt className="text-[0.6875rem] font-medium tracking-[0.12em] text-muted-foreground uppercase">
                Email
              </dt>
              <dd className="mt-1 text-sm text-foreground">{user.email}</dd>
            </div>
            <div>
              <dt className="text-[0.6875rem] font-medium tracking-[0.12em] text-muted-foreground uppercase">
                Member since
              </dt>
              <dd className="mt-1 text-sm text-foreground">{formatDate(user.createdAt)}</dd>
            </div>
            <div>
              <dt className="text-[0.6875rem] font-medium tracking-[0.12em] text-muted-foreground uppercase">
                User ID
              </dt>
              <dd className="mt-1 font-mono text-xs text-foreground">{user.id}</dd>
            </div>
          </dl>
        </section>

        <section className="rounded-2xl border border-border bg-card">
          <div className="border-b border-border px-6 py-5">
            <h2 className="flex items-center gap-2 text-sm font-semibold text-foreground">
              <IconBell className="size-4" stroke={1.8} />
              Notifications
            </h2>
            <p className="mt-1 text-sm text-muted-foreground">
              Configure how and when you hear from Hookscope.
            </p>
          </div>
          <div className="px-6 py-10 text-center">
            <IconSettings className="mx-auto mb-3 size-8 text-muted-foreground" stroke={1.5} />
            <p className="text-sm text-muted-foreground">Coming soon.</p>
          </div>
        </section>
      </div>
    </div>
  )
}
