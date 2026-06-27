'use client'

import Link from 'next/link'
import { useCallback, useEffect, useMemo, useState } from 'react'
import {
  IconArrowRight,
  IconAntenna,
  IconBolt,
  IconInbox,
  IconPlus,
  type TablerIcon,
} from '@tabler/icons-react'
import { Button } from '@workspace/ui/components/button'
import { Skeleton } from '@workspace/ui/components/skeleton'
import { CreateInboxDialog } from '@/app/components/dashboard/create-inbox-dialog'
import { ApiError } from '@/lib/api'
import {
  fetchInboxes,
  formatRelativeTime,
  type InboxSummary,
} from '@/lib/inboxes'

type LoadState = 'loading' | 'ready' | 'error'

function StatCard({
  label,
  value,
  hint,
  icon: Icon,
}: {
  label: string
  value: string
  hint: string
  icon: TablerIcon
}) {
  return (
    <div className="rounded-2xl border border-border bg-card p-5">
      <div className="mb-4 flex items-start justify-between gap-3">
        <p className="text-[0.6875rem] font-medium tracking-[0.12em] text-muted-foreground uppercase">
          {label}
        </p>
        <span className="flex size-8 items-center justify-center rounded-lg bg-primary/10 text-primary">
          <Icon className="size-4" aria-hidden />
        </span>
      </div>
      <p className="text-3xl font-semibold tracking-tight text-foreground tabular-nums">{value}</p>
      <p className="mt-1.5 text-sm text-muted-foreground">{hint}</p>
    </div>
  )
}

export function DashboardHome() {
  const [inboxes, setInboxes] = useState<InboxSummary[]>([])
  const [state, setState] = useState<LoadState>('loading')
  const [createOpen, setCreateOpen] = useState(false)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)

  const loadInboxes = useCallback(async () => {
    setState('loading')
    setErrorMessage(null)

    try {
      const next = await fetchInboxes()
      setInboxes(next)
      setState('ready')
    } catch (error) {
      setErrorMessage(
        error instanceof ApiError ? error.message : 'Could not load your workspace. Try again.'
      )
      setState('error')
    }
  }, [])

  useEffect(() => {
    void loadInboxes()
  }, [loadInboxes])

  const stats = useMemo(() => {
    const totalEvents = inboxes.reduce((sum, inbox) => sum + inbox.eventsCount, 0)
    const activeInboxes = inboxes.filter((inbox) => inbox.eventsCount > 0).length
    const latest = inboxes
      .map((inbox) => inbox.lastEventAt)
      .filter(Boolean)
      .sort()
      .at(-1)

    return { totalEvents, activeInboxes, latest: latest ?? null }
  }, [inboxes])

  function handleInboxCreated(inbox: InboxSummary) {
    setInboxes((current) => [inbox, ...current])
    setState('ready')
    setErrorMessage(null)
  }

  const recent = inboxes.slice(0, 4)

  return (
    <div className="mx-auto w-full max-w-5xl">
      <section className="mb-10 flex flex-wrap items-end justify-between gap-6">
        <div className="max-w-2xl">
          <h1 className="font-heading text-[clamp(2rem,4vw,2.75rem)] leading-tight font-semibold tracking-wide text-foreground">
            Your webhook bench is ready.
          </h1>
          <p className="mt-3 max-w-xl text-[0.9375rem] leading-relaxed text-muted-foreground">
            Capture payloads from any provider, inspect them as received, and replay to localhost
            with full response visibility.
          </p>
        </div>

        <div className="flex flex-wrap gap-2">
          <Button type="button" onClick={() => setCreateOpen(true)}>
            <IconPlus className="size-4" aria-hidden />
            New inbox
          </Button>
          <Button type="button" variant="outline" asChild>
            <Link href="/inboxes">
              All inboxes
              <IconArrowRight className="size-4" aria-hidden />
            </Link>
          </Button>
        </div>
      </section>

      {errorMessage && (
        <p className="mb-6 text-sm text-destructive" role="alert">
          {errorMessage}
        </p>
      )}

      <section className="mb-10 grid gap-4 sm:grid-cols-3">
        {state === 'loading' ? (
          <>
            <Skeleton className="h-36 rounded-2xl" />
            <Skeleton className="h-36 rounded-2xl" />
            <Skeleton className="h-36 rounded-2xl" />
          </>
        ) : (
          <>
            <StatCard
              label="Inboxes"
              value={String(inboxes.length)}
              hint={inboxes.length === 0 ? 'Create your first ingest endpoint' : 'Saved in your account'}
              icon={IconInbox}
            />
            <StatCard
              label="Events captured"
              value={String(stats.totalEvents)}
              hint={
                stats.activeInboxes === 0
                  ? 'Waiting for your first webhook'
                  : `${stats.activeInboxes} inbox${stats.activeInboxes === 1 ? '' : 'es'} with traffic`
              }
              icon={IconAntenna}
            />
            <StatCard
              label="Last activity"
              value={stats.latest ? formatRelativeTime(stats.latest) : '—'}
              hint={stats.latest ? 'Most recent webhook received' : 'No events yet'}
              icon={IconBolt}
            />
          </>
        )}
      </section>

      <section aria-labelledby="recent-inboxes-heading">
        <div className="mb-4 flex items-center justify-between gap-4">
          <h2 id="recent-inboxes-heading" className="text-lg font-semibold text-foreground">
            Recent inboxes
          </h2>
          <Link
            href="/inboxes"
            className="text-sm text-primary underline-offset-4 hover:underline"
          >
            View all
          </Link>
        </div>

        {state === 'loading' && (
          <div className="space-y-3" aria-busy="true">
            <Skeleton className="h-20 rounded-xl" />
            <Skeleton className="h-20 rounded-xl" />
          </div>
        )}

        {state === 'ready' && recent.length === 0 && (
          <div className="rounded-2xl border border-dashed border-border px-6 py-12 text-center">
            <p className="text-base font-medium text-foreground">No inboxes yet</p>
            <p className="mx-auto mt-2 max-w-md text-sm text-muted-foreground">
              Open an inbox, paste the ingest URL into Stripe or GitHub, and your first event lands
              here in seconds.
            </p>
            <Button type="button" className="mt-6" onClick={() => setCreateOpen(true)}>
              <IconPlus className="size-4" aria-hidden />
              Create inbox
            </Button>
          </div>
        )}

        {state === 'ready' && recent.length > 0 && (
          <ul className="divide-y divide-border rounded-2xl border border-border bg-card">
            {recent.map((inbox) => (
              <li key={inbox.id}>
                <Link
                  href={`/i/${inbox.id}`}
                  className="flex flex-wrap items-center justify-between gap-4 px-5 py-4 transition-colors hover:bg-muted/40"
                >
                  <div className="min-w-0">
                    <p className="font-medium text-foreground">{inbox.name}</p>
                    <p className="mt-0.5 font-mono text-xs text-muted-foreground">
                      {inbox.ingestUrl}
                    </p>
                  </div>
                  <div className="text-right text-sm">
                    <p className="font-medium tabular-nums text-foreground">{inbox.eventsCount} events</p>
                    <p className="text-muted-foreground">{formatRelativeTime(inbox.lastEventAt)}</p>
                  </div>
                </Link>
              </li>
            ))}
          </ul>
        )}

        {state === 'error' && (
          <div className="rounded-2xl border border-border px-6 py-10 text-center">
            <p className="text-sm text-muted-foreground">We could not load your workspace.</p>
            <Button type="button" variant="outline" className="mt-4" onClick={() => void loadInboxes()}>
              Retry
            </Button>
          </div>
        )}
      </section>

      <CreateInboxDialog
        open={createOpen}
        onOpenChange={setCreateOpen}
        onCreated={handleInboxCreated}
      />
    </div>
  )
}