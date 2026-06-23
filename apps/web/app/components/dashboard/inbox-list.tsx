'use client'

import Link from 'next/link'
import { useCallback, useEffect, useState } from 'react'
import { Copy, ExternalLink, Plus } from 'lucide-react'
import { Button } from '@workspace/ui/components/button'
import { Loader } from '@workspace/ui/components/loader'
import { Skeleton } from '@workspace/ui/components/skeleton'
import { cn } from '@workspace/ui/lib/utils'
import { ApiError } from '@/lib/api'
import {
  createInbox,
  fetchInboxes,
  formatRelativeTime,
  inboxPublicUrl,
  type InboxSummary,
} from '@/lib/inboxes'

type LoadState = 'loading' | 'ready' | 'error' | 'empty'

function InboxRow({
  inbox,
  onCopy,
  copiedId,
}: {
  inbox: InboxSummary
  onCopy: (id: string, url: string) => void
  copiedId: string | null
}) {
  const ingestUrl = inboxPublicUrl(inbox.ingestUrl)
  const copied = copiedId === inbox.id

  return (
    <article
      className={cn(
        'group grid gap-3 border-b border-border px-5 py-4 transition-colors last:border-b-0 md:grid-cols-[minmax(0,1.35fr)_4.5rem_6rem_minmax(0,1.5fr)_auto] md:items-center md:gap-5 md:py-5',
        'hover:bg-muted/40'
      )}
    >
      <div className="min-w-0 pl-0.5 md:pl-1">
        <Link
          href={inbox.ingestUrl}
          className="text-[0.9375rem] font-medium text-foreground underline-offset-4 transition-colors hover:text-primary hover:underline"
        >
          {inbox.name}
        </Link>
        <p className="mt-0.5 font-mono text-xs text-muted-foreground">/i/{inbox.id}</p>
      </div>

      <div className="text-sm tabular-nums text-foreground md:text-right">
        <span className="text-muted-foreground md:hidden">Events </span>
        {inbox.eventsCount}
      </div>

      <div className="text-sm text-muted-foreground md:text-right">
        <span className="md:hidden">Last </span>
        {formatRelativeTime(inbox.lastEventAt)}
      </div>

      <div className="min-w-0">
        <code className="block truncate rounded-md bg-muted/50 px-2 py-1 font-mono text-[0.8125rem] text-foreground/85">
          {ingestUrl}
        </code>
      </div>

      <div className="flex items-center gap-1.5 md:justify-end">
        <Button
          type="button"
          variant="outline"
          size="sm"
          onClick={() => onCopy(inbox.id, ingestUrl)}
          aria-label={copied ? 'URL copied' : `Copy ingest URL for ${inbox.name}`}
        >
          <Copy className="size-3.5" aria-hidden />
          <span className="sr-only sm:not-sr-only">{copied ? 'Copied' : 'Copy'}</span>
        </Button>
        <Button type="button" variant="ghost" size="sm" asChild>
          <Link href={inbox.ingestUrl} aria-label={`Open ${inbox.name}`}>
            <ExternalLink className="size-3.5" aria-hidden />
            <span className="sr-only sm:not-sr-only">Open</span>
          </Link>
        </Button>
      </div>
    </article>
  )
}

function InboxSkeleton() {
  return (
    <div className="border-b border-border py-5 last:border-b-0">
      <div className="grid gap-3 md:grid-cols-[minmax(0,1.35fr)_4.5rem_6rem_minmax(0,1.5fr)_auto] md:items-center md:gap-5">
        <div className="space-y-2">
          <Skeleton className="h-4 w-36" />
          <Skeleton className="h-3 w-28" />
        </div>
        <Skeleton className="hidden h-4 w-8 justify-self-end md:block" />
        <Skeleton className="hidden h-4 w-14 justify-self-end md:block" />
        <Skeleton className="hidden h-8 w-full md:block" />
        <Skeleton className="hidden h-8 w-24 justify-self-end md:block" />
      </div>
    </div>
  )
}

export function InboxList({ token }: { token: string }) {
  const [inboxes, setInboxes] = useState<InboxSummary[]>([])
  const [state, setState] = useState<LoadState>('loading')
  const [creating, setCreating] = useState(false)
  const [copiedId, setCopiedId] = useState<string | null>(null)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)

  const loadInboxes = useCallback(async () => {
    setState('loading')
    setErrorMessage(null)

    try {
      const next = await fetchInboxes(token)
      setInboxes(next)
      setState(next.length === 0 ? 'empty' : 'ready')
    } catch (error) {
      setErrorMessage(
        error instanceof ApiError ? error.message : 'Could not load your inboxes. Try again.'
      )
      setState('error')
    }
  }, [token])

  useEffect(() => {
    void loadInboxes()
  }, [loadInboxes])

  async function handleCreate() {
    setCreating(true)
    setErrorMessage(null)

    try {
      const inbox = await createInbox(token)
      setInboxes((current) => [inbox, ...current])
      setState('ready')
    } catch (error) {
      setErrorMessage(
        error instanceof ApiError ? error.message : 'Could not create an inbox. Try again.'
      )
    } finally {
      setCreating(false)
    }
  }

  async function handleCopy(id: string, url: string) {
    await navigator.clipboard.writeText(url)
    setCopiedId(id)
    window.setTimeout(() => setCopiedId(null), 2000)
  }

  return (
    <section aria-labelledby="inboxes-heading" className="mx-auto w-full max-w-5xl">
      <div className="flex flex-wrap items-start justify-between gap-4 md:gap-6">
        <div className="min-w-0">
          <p className="font-ui mb-2 text-[0.6875rem] font-medium tracking-[0.14em] text-primary uppercase">
            Ingest endpoints
          </p>
          <h1
            id="inboxes-heading"
            className="font-heading text-[clamp(1.75rem,3vw,2.25rem)] font-semibold tracking-wide text-foreground"
          >
            Inboxes
          </h1>
          <p className="mt-2 max-w-xl text-[0.9375rem] leading-relaxed text-muted-foreground">
            Each inbox is a public URL. Point Stripe, GitHub, or curl at it, then inspect and replay
            every byte.
          </p>
        </div>

        <Button
          type="button"
          onClick={() => void handleCreate()}
          disabled={creating || state === 'loading'}
          className="shrink-0"
        >
          {creating ? (
            <>
              <Loader size="sm" tone="inherit" />
              Creating…
            </>
          ) : (
            <>
              <Plus className="size-4" aria-hidden />
              New inbox
            </>
          )}
        </Button>
      </div>

      {errorMessage && (
        <p className="mt-4 text-sm text-destructive" role="alert">
          {errorMessage}
        </p>
      )}

      <div className="mt-6 overflow-hidden rounded-2xl border border-border bg-card md:mt-8">
        {state === 'loading' && (
          <div aria-busy="true" aria-label="Loading inboxes">
            <InboxSkeleton />
            <InboxSkeleton />
            <InboxSkeleton />
          </div>
        )}

        {state === 'empty' && (
          <div className="flex flex-col items-center border-b border-dashed border-border py-16 text-center">
            <p className="text-base font-medium text-foreground">No inboxes yet</p>
            <p className="mt-2 max-w-sm text-sm text-muted-foreground">
              Create an inbox to get a public ingest URL. Events wait here until you replay them.
            </p>
            <Button type="button" className="mt-6" onClick={() => void handleCreate()} disabled={creating}>
              {creating ? (
                <>
                  <Loader size="sm" tone="inherit" />
                  Creating…
                </>
              ) : (
                <>
                  <Plus className="size-4" aria-hidden />
                  Create inbox
                </>
              )}
            </Button>
          </div>
        )}

        {state === 'ready' && (
          <>
            <div
              className={cn(
                'hidden border-b border-border bg-muted/30 px-5 py-3 text-[0.6875rem] font-medium tracking-wider text-muted-foreground uppercase md:grid',
                'md:grid-cols-[minmax(0,1.35fr)_4.5rem_6rem_minmax(0,1.5fr)_auto] md:gap-5'
              )}
            >
              <span className="pl-1">Inbox</span>
              <span className="text-right">Events</span>
              <span className="text-right">Last seen</span>
              <span>Ingest URL</span>
              <span className="text-right">Actions</span>
            </div>

            {inboxes.map((inbox) => (
              <InboxRow key={inbox.id} inbox={inbox} onCopy={handleCopy} copiedId={copiedId} />
            ))}
          </>
        )}

        {state === 'error' && (
          <div className="flex flex-col items-center gap-3 py-12">
            <p className="text-sm text-muted-foreground">We could not reach your inboxes.</p>
            <Button type="button" variant="outline" onClick={() => void loadInboxes()}>
              Retry
            </Button>
          </div>
        )}
      </div>
    </section>
  )
}