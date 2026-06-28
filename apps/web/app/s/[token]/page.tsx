'use client'

import { use, useState } from 'react'
import Link from 'next/link'
import { useQuery } from '@tanstack/react-query'
import { IconCopy, IconExternalLink } from '@tabler/icons-react'
import { Button } from '@workspace/ui/components/button'
import { Skeleton } from '@workspace/ui/components/skeleton'
import { cn } from '@workspace/ui/lib/utils'
import { fetchSharedEvent } from '@/lib/shared-event'
import type { ReplayRecord } from '@/lib/shared-event'
import { formatRelativeTime } from '@/lib/inboxes'

function MethodBadge({ method }: { method: string }) {
  return (
    <span className="rounded-md bg-primary/10 px-1.5 py-0.5 font-mono text-[0.6875rem] font-semibold tracking-wide text-primary">
      {method}
    </span>
  )
}

function JsonBlock({ value }: { value: unknown }) {
  const text = JSON.stringify(value, null, 2)

  return (
    <pre className="max-h-80 overflow-auto rounded-lg border border-border bg-muted/30 p-3 font-mono text-xs leading-relaxed text-foreground">
      {text}
    </pre>
  )
}

function buildEventJson(event: { method: string; path: string; headers: Record<string, unknown>; query: Record<string, unknown>; bodyJson: unknown; bodyText: string | null }) {
  return JSON.stringify(
    {
      method: event.method,
      path: event.path,
      headers: event.headers,
      query: event.query,
      body: event.bodyJson ?? event.bodyText,
    },
    null,
    2
  )
}

function buildEventCurl(event: { method: string; path: string; headers: Record<string, unknown>; bodyJson: unknown; bodyText: string | null }) {
  const url = `https://hookscope.dev${event.path}`
  const parts = [`curl -X ${event.method} '${url}'`]

  for (const [key, value] of Object.entries(event.headers)) {
    if (key.toLowerCase() === 'authorization') continue
    const val = Array.isArray(value) ? value[0] ?? '' : value
    parts.push(`  -H '${key}: ${val}'`)
  }

  const body = event.bodyJson ?? event.bodyText
  if (body) {
    parts.push(`  -d '${typeof body === 'string' ? body : JSON.stringify(body)}'`)
  }

  return parts.join(' \\\n')
}

function ReplayRow({ replay }: { replay: ReplayRecord }) {
  const statusOk = replay.statusCode && replay.statusCode >= 200 && replay.statusCode < 300
  const statusErr = replay.statusCode && replay.statusCode >= 400
  const duration = replay.durationMs != null ? `${replay.durationMs}ms` : null

  return (
    <div className="rounded-lg border border-border px-4 py-3">
      <div className="flex items-center gap-3">
        {replay.statusCode ? (
          <span
            className={cn(
              'inline-flex items-center gap-1.5 font-mono text-sm font-semibold tabular-nums',
              statusErr ? 'text-destructive' : statusOk ? 'text-signal' : 'text-foreground'
            )}
          >
            <span
              className={cn(
                'size-1.5 rounded-full',
                statusErr ? 'bg-destructive' : statusOk ? 'bg-signal' : 'bg-muted-foreground'
              )}
              aria-hidden
            />
            {replay.statusCode}
          </span>
        ) : (
          <span className="inline-flex items-center gap-1.5 font-mono text-sm font-semibold text-destructive">
            <span className="size-1.5 rounded-full bg-destructive" aria-hidden />
            {replay.errorCode ?? 'ERR'}
          </span>
        )}
        <span className="min-w-0 truncate text-xs text-muted-foreground">
          {replay.targetUrl}
        </span>
        {duration ? (
          <span className="ml-auto whitespace-nowrap font-mono text-xs tabular-nums text-muted-foreground">
            {duration}
          </span>
        ) : null}
      </div>
      <div className="mt-1.5 flex items-center gap-3 text-xs text-muted-foreground">
        <span>{formatRelativeTime(replay.createdAt)}</span>
        {replay.responseBody ? (
          <button
            type="button"
            className="font-medium text-primary underline-offset-2 hover:underline"
            onClick={() => {
              const el = document.getElementById(`replay-body-${replay.id}`)
              el?.classList.toggle('hidden')
            }}
          >
            View response
          </button>
        ) : null}
      </div>
      {replay.responseBody ? (
        <pre
          id={`replay-body-${replay.id}`}
          className="mt-2 hidden max-h-40 overflow-auto rounded-md border border-border bg-muted/30 p-2 font-mono text-[0.6875rem] leading-relaxed text-foreground"
        >
          {replay.responseBody}
        </pre>
      ) : null}
    </div>
  )
}

function SharedEventSkeleton() {
  return (
    <div className="mx-auto flex w-full max-w-4xl flex-col gap-6 px-4 py-16" aria-busy="true">
      <Skeleton className="h-3 w-20 rounded-md" />
      <div className="space-y-3">
        <Skeleton className="h-5 w-48 rounded-md" />
        <div className="flex gap-2">
          <Skeleton className="h-8 w-20 rounded-lg" />
          <Skeleton className="h-8 w-28 rounded-lg" />
        </div>
      </div>
      <Skeleton className="h-48 rounded-xl border border-border" />
      <Skeleton className="h-64 rounded-xl border border-border" />
    </div>
  )
}

export default function SharedEventPage({
  params,
}: {
  params: Promise<{ token: string }>
}) {
  const { token } = use(params)

  const [copiedAction, setCopiedAction] = useState<'json' | 'curl' | null>(null)

  const { data, isLoading, isError } = useQuery({
    queryKey: ['shared-event', token],
    queryFn: () => fetchSharedEvent(token),
    retry: 1,
    staleTime: 30_000,
  })

  async function handleCopy(action: 'json' | 'curl') {
    if (!data) return

    const text =
      action === 'json' ? buildEventJson(data.event) : buildEventCurl(data.event)
    if (!text) return

    await navigator.clipboard.writeText(text)
    setCopiedAction(action)
    window.setTimeout(() => setCopiedAction(null), 2000)
  }

  if (isLoading) {
    return <SharedEventSkeleton />
  }

  if (isError || !data) {
    return (
      <div className="mx-auto flex min-h-[70vh] w-full max-w-lg flex-col items-center justify-center gap-6 px-4">
        <Link href="/" className="font-ui text-sm font-semibold text-primary">
          Hookscope
        </Link>
        <div className="flex flex-col items-center gap-2 text-center">
          <h1 className="font-ui text-xl font-semibold text-foreground">
            This link couldn&apos;t be found.
          </h1>
          <p className="text-sm text-muted-foreground">
            It may have expired or the link is incorrect. Ask the sender to
            generate a new one.
          </p>
        </div>
        <Link href="/">
          <Button type="button" variant="default">
            Create your own webhook endpoint
          </Button>
        </Link>
      </div>
    )
  }

  const { event, replays } = data

  return (
    <div className="mx-auto flex w-full max-w-4xl flex-col gap-8 px-4 py-16">
      {/* Brand mark */}
      <div>
        <Link
          href="/"
          className="font-ui text-sm font-semibold text-primary transition-colors hover:text-primary/80"
        >
          Hookscope
        </Link>
      </div>

      {/* Hero: event identity + copy actions */}
      <div className="space-y-4">
        <p className="font-ui text-[0.6875rem] font-medium tracking-[0.12em] text-muted-foreground uppercase">
          Shared event
        </p>
        <div className="flex flex-wrap items-center gap-3">
          <MethodBadge method={event.method} />
          <code className="truncate rounded-md bg-muted/40 px-1.5 py-0.5 font-mono text-sm text-foreground">
            {event.path}
          </code>
          <span className="text-xs text-muted-foreground">
            {formatRelativeTime(event.receivedAt)}
          </span>
        </div>
        <div className="flex gap-2">
          <Button
            type="button"
            variant="outline"
            size="sm"
            onClick={() => void handleCopy('json')}
          >
            <IconCopy className="size-3.5" aria-hidden />
            {copiedAction === 'json' ? 'Copied' : 'JSON'}
          </Button>
          <Button
            type="button"
            variant="outline"
            size="sm"
            onClick={() => void handleCopy('curl')}
          >
            <IconCopy className="size-3.5" aria-hidden />
            {copiedAction === 'curl' ? 'Copied' : 'cURL'}
          </Button>
        </div>
      </div>

      {/* Data panels */}
      <div className="flex flex-col gap-6">
        {event.headers && Object.keys(event.headers).length > 0 && (
          <div className="overflow-hidden rounded-xl border border-border bg-card">
            <div className="border-b border-border px-4 py-2.5">
              <h2 className="font-ui text-[0.6875rem] font-medium tracking-[0.12em] text-muted-foreground uppercase">
                Headers
              </h2>
            </div>
            <div className="p-4">
              <JsonBlock value={event.headers} />
            </div>
          </div>
        )}

        {event.query && Object.keys(event.query).length > 0 && (
          <div className="overflow-hidden rounded-xl border border-border bg-card">
            <div className="border-b border-border px-4 py-2.5">
              <h2 className="font-ui text-[0.6875rem] font-medium tracking-[0.12em] text-muted-foreground uppercase">
                Query
              </h2>
            </div>
            <div className="p-4">
              <JsonBlock value={event.query} />
            </div>
          </div>
        )}

        {(event.bodyJson !== null || event.bodyText !== null) && (
          <div className="overflow-hidden rounded-xl border-2 border-border bg-card">
            <div className="border-b border-border px-4 py-2.5">
              <h2 className="font-ui text-[0.6875rem] font-medium tracking-[0.12em] text-muted-foreground uppercase">
                Body
              </h2>
              {event.contentType ? (
                <p className="mt-0.5 text-xs text-muted-foreground">
                  {event.contentType}
                </p>
              ) : null}
            </div>
            <div className="p-4">
              <JsonBlock value={event.bodyJson ?? event.bodyText} />
            </div>
          </div>
        )}

        {replays.length > 0 && (
          <div className="overflow-hidden rounded-xl border border-border bg-card">
            <div className="border-b border-border px-4 py-2.5">
              <h2 className="font-ui text-[0.6875rem] font-medium tracking-[0.12em] text-muted-foreground uppercase">
                Replays
              </h2>
              <p className="mt-0.5 text-xs text-muted-foreground">
                {replays.length} replay{replays.length > 1 ? 's' : ''}
              </p>
            </div>
            <div className="space-y-2 p-4">
              {replays.map((replay) => (
                <ReplayRow key={replay.id} replay={replay} />
              ))}
            </div>
          </div>
        )}
      </div>

      {/* Footer */}
      <footer className="border-t border-border pt-8 text-center">
        <Link
          href="/"
          className="inline-flex items-center gap-1.5 font-ui text-sm text-muted-foreground transition-colors hover:text-foreground"
        >
          <IconExternalLink className="size-3.5" aria-hidden />
          Hookscope &middot; Webhook debugger
        </Link>
      </footer>
    </div>
  )
}
