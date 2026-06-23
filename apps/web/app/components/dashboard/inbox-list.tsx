'use client'

import Link from 'next/link'
import { useCallback, useEffect, useState } from 'react'
import { Button } from '@workspace/ui/components/button'
import { Loader } from '@workspace/ui/components/loader'
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

  return (
    <article className="group grid gap-4 border-b border-border py-5 transition-colors last:border-b-0 md:grid-cols-[minmax(0,1.4fr)_5rem_6.5rem_minmax(0,1.6fr)_auto] md:items-center md:gap-6">
      <div className="min-w-0">
        <Link
          href={inbox.ingestUrl}
          className="font-ui text-[0.9375rem] font-medium text-foreground transition-colors hover:text-primary"
        >
          {inbox.name}
        </Link>
        <p className="font-ui mt-0.5 text-xs text-muted-foreground">/{inbox.id}</p>
      </div>

      <div className="font-ui text-sm tabular-nums text-foreground md:text-right">
        <span className="md:hidden text-muted-foreground">Events </span>
        {inbox.eventsCount}
      </div>

      <div className="font-ui text-sm text-muted-foreground md:text-right">
        <span className="md:hidden">Last </span>
        {formatRelativeTime(inbox.lastEventAt)}
      </div>

      <div className="min-w-0">
        <code className="font-ui block truncate text-[0.8125rem] text-foreground/80">
          {ingestUrl}
        </code>
      </div>

      <div className="flex items-center gap-2 md:justify-end">
        <Button
          type="button"
          variant="outline"
          size="sm"
          className="font-ui"
          onClick={() => onCopy(inbox.id, ingestUrl)}
        >
          {copiedId === inbox.id ? 'Copied' : 'Copy URL'}
        </Button>
        <Button type="button" variant="ghost" size="sm" className="font-ui" asChild>
          <Link href={inbox.ingestUrl}>Open</Link>
        </Button>
      </div>
    </article>
  )
}

function InboxSkeleton() {
  return (
    <div className="animate-pulse border-b border-border py-5 last:border-b-0">
      <div className="grid gap-3 md:grid-cols-[minmax(0,1.4fr)_5rem_6.5rem_minmax(0,1.6fr)_auto] md:items-center md:gap-6">
        <div className="space-y-2">
          <div className="h-4 w-36 rounded bg-muted" />
          <div className="h-3 w-24 rounded bg-muted/80" />
        </div>
        <div className="hidden h-4 rounded bg-muted md:block" />
        <div className="hidden h-4 rounded bg-muted md:block" />
        <div className="hidden h-4 rounded bg-muted md:block" />
        <div className="hidden h-8 w-28 rounded bg-muted md:block" />
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
    <section aria-labelledby="inboxes-heading">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <h1 id="inboxes-heading" className="font-ui text-2xl font-semibold tracking-tight text-foreground">
            Your inboxes
          </h1>
          <p className="font-ui mt-1 max-w-xl text-sm text-muted-foreground">
            Saved endpoints for webhook capture. Copy an ingest URL, point a provider at it, then
            open the inbox to inspect and replay.
          </p>
        </div>

        <Button
          type="button"
          className="font-ui"
          onClick={() => void handleCreate()}
          disabled={creating || state === 'loading'}
        >
          {creating ? (
            <>
              <Loader size="sm" tone="inherit" />
              Creating…
            </>
          ) : (
            'New inbox'
          )}
        </Button>
      </div>

      {errorMessage && (
        <p className="font-ui mt-4 text-sm text-destructive" role="alert">
          {errorMessage}
        </p>
      )}

      <div className="mt-8 border-t border-border">
        {state === 'loading' && (
          <div aria-busy="true" aria-label="Loading inboxes">
            <InboxSkeleton />
            <InboxSkeleton />
            <InboxSkeleton />
          </div>
        )}

        {state === 'empty' && (
          <div className="py-16 text-center">
            <p className="font-ui text-base font-medium text-foreground">No inboxes yet</p>
            <p className="font-ui mx-auto mt-2 max-w-md text-sm text-muted-foreground">
              Create one inbox to get an ingest URL. Events stay here until you replay them to your
              local server.
            </p>
            <Button type="button" className="font-ui mt-6" onClick={() => void handleCreate()} disabled={creating}>
              {creating ? (
                <>
                  <Loader size="sm" tone="inherit" />
                  Creating…
                </>
              ) : (
                'Create your first inbox'
              )}
            </Button>
          </div>
        )}

        {state === 'ready' && (
          <>
            <div
              className={cn(
                'font-ui hidden border-b border-border py-3 text-xs tracking-wide text-muted-foreground uppercase md:grid',
                'md:grid-cols-[minmax(0,1.4fr)_5rem_6.5rem_minmax(0,1.6fr)_auto] md:gap-6'
              )}
            >
              <span>Inbox</span>
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
          <div className="py-12 text-center">
            <Button type="button" variant="outline" className="font-ui" onClick={() => void loadInboxes()}>
              Retry
            </Button>
          </div>
        )}
      </div>
    </section>
  )
}