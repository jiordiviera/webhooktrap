'use client'

import Link from 'next/link'
import { useRouter } from 'next/navigation'
import { useCallback, useEffect, useMemo, useState } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import {
  IconArrowLeft,
  IconCheck,
  IconCopy,
  IconPencil,
  IconPlayerPlay,
  IconRefresh,
  IconTrash,
  IconX,
} from '@tabler/icons-react'
import { Button } from '@workspace/ui/components/button'
import { Input } from '@workspace/ui/components/input'
import { Loader } from '@workspace/ui/components/loader'
import { Separator } from '@workspace/ui/components/separator'
import { Skeleton } from '@workspace/ui/components/skeleton'
import { cn } from '@workspace/ui/lib/utils'
import {
  EventInspectorSkeleton,
  ReplayPanelSkeleton,
} from '@/app/components/inbox/inbox-detail-skeleton'
import { DataTable } from '@/features/data-table/components/data-table'
import {
  eventReplaysQueryKey,
  useEventDetailQuery,
  useEventReplaysQuery,
} from '@/features/inbox/hooks/use-event-queries'
import { inboxQueryKey, useInboxQuery } from '@/features/inbox/hooks/use-inbox-query'
import { ApiError } from '@/lib/api'
import { useConfirm } from '@/contexts/confirm-context'
import { useInboxPageTitle } from '@/features/inbox/context/inbox-page-context'
import { buildEventCurl, buildEventJson } from '@/lib/event-copy'
import { replayEvent } from '@/lib/events'
import {
  deleteInbox,
  formatRelativeTime,
  updateInbox,
} from '@/lib/inboxes'

const POLL_INTERVAL_MS = 3000

function MethodBadge({ method }: { method: string }) {
  return (
    <span className="rounded-md bg-primary/10 px-1.5 py-0.5 font-mono text-[0.6875rem] font-semibold tracking-wide text-primary">
      {method}
    </span>
  )
}

function JsonBlock({ value }: { value: unknown }) {
  const text = useMemo(() => JSON.stringify(value, null, 2), [value])

  return (
    <pre className="max-h-80 overflow-auto rounded-lg border border-border bg-muted/30 p-3 font-mono text-xs leading-relaxed text-foreground">
      {text}
    </pre>
  )
}

function ReplayStatus({ replay }: { replay: { statusCode: number | null; errorCode: string | null } }) {
  if (replay.statusCode) {
    const isSuccess = replay.statusCode >= 200 && replay.statusCode < 300
    const isError = replay.statusCode >= 400

    return (
      <span
        className={cn(
          'font-mono text-sm font-semibold tabular-nums',
          isError ? 'text-destructive' : isSuccess ? 'text-signal' : 'text-foreground'
        )}
      >
        {replay.statusCode}
      </span>
    )
  }

  return (
    <span className="font-mono text-sm font-semibold text-destructive">
      {replay.errorCode ?? 'ERROR'}
    </span>
  )
}

function SectionPanel({
  title,
  description,
  children,
  actions,
}: {
  title: string
  description?: string
  children: React.ReactNode
  actions?: React.ReactNode
}) {
  return (
    <div className="overflow-hidden rounded-xl border border-border bg-card">
      <div className="flex flex-wrap items-start justify-between gap-3 border-b border-border px-4 py-3">
        <div className="min-w-0">
          <h2 className="font-ui text-sm font-semibold text-foreground">{title}</h2>
          {description ? (
            <p className="mt-0.5 text-sm text-muted-foreground">{description}</p>
          ) : null}
        </div>
        {actions ? <div className="flex shrink-0 gap-2">{actions}</div> : null}
      </div>
      {children}
    </div>
  )
}

function InboxHeaderSkeleton() {
  return (
    <div className="min-w-0 space-y-2" aria-busy="true">
      <Skeleton className="h-8 w-24 rounded-lg" />
      <Skeleton className="h-8 w-48 max-w-full rounded-lg" />
      <Skeleton className="h-4 w-32 rounded-md" />
    </div>
  )
}

export function InboxDetailPage({ inboxId, token }: { inboxId: string; token: string }) {
  const router = useRouter()
  const queryClient = useQueryClient()
  const confirm = useConfirm()
  const inboxPage = useInboxPageTitle()

  const inboxQuery = useInboxQuery(token, inboxId)
  const inbox = inboxQuery.data ?? null

  const [selectedEventId, setSelectedEventId] = useState<string | null>(null)
  const [eventsTotal, setEventsTotal] = useState(0)
  const [replayUrl, setReplayUrl] = useState('')
  const [replaying, setReplaying] = useState(false)
  const [actionError, setActionError] = useState<string | null>(null)
  const [copied, setCopied] = useState(false)
  const [copiedAction, setCopiedAction] = useState<'json' | 'curl' | null>(null)
  const [editingName, setEditingName] = useState(false)
  const [nameDraft, setNameDraft] = useState('')
  const [savingName, setSavingName] = useState(false)
  const [deleting, setDeleting] = useState(false)

  const eventQuery = useEventDetailQuery(token, selectedEventId)
  const replaysQuery = useEventReplaysQuery(token, selectedEventId)

  const eventDetail = eventQuery.data ?? null
  const replays = replaysQuery.data ?? []
  const detailLoading = eventQuery.isLoading && !eventDetail
  const replaysLoading = replaysQuery.isLoading && replays.length === 0 && Boolean(selectedEventId)

  const ingestUrl = inbox?.ingestUrl ?? ''
  const eventCount = inbox?.eventsCount || eventsTotal
  const inboxLoading = inboxQuery.isLoading
  const loadError =
    actionError ??
    (inboxQuery.isError
      ? inboxQuery.error instanceof ApiError
        ? inboxQuery.error.message
        : 'Could not load inbox. Try again.'
      : null)

  useEffect(() => {
    if (!inbox) return
    setReplayUrl(inbox.defaultReplayUrl ?? '')
  }, [inbox?.id, inbox?.defaultReplayUrl])

  useEffect(() => {
    if (!inboxPage || !inbox) return

    inboxPage.setTitle(inbox.name)

    return () => {
      inboxPage.setTitle(null)
    }
  }, [inbox, inboxPage])

  const refresh = useCallback(() => {
    setActionError(null)
    void inboxQuery.refetch()
    void queryClient.invalidateQueries({
      queryKey: ['data-table', 'inbox-events', { inboxId }],
    })
    if (selectedEventId) {
      void eventQuery.refetch()
      void replaysQuery.refetch()
    }
  }, [inboxId, inboxQuery, queryClient, selectedEventId, eventQuery, replaysQuery])

  async function handleSaveReplayUrl() {
    if (!inbox) return

    try {
      const updated = await updateInbox(token, inbox.id, {
        defaultReplayUrl: replayUrl.trim() || null,
      })
      queryClient.setQueryData(inboxQueryKey(inbox.id), updated)
      setReplayUrl(updated.defaultReplayUrl ?? '')
      setActionError(null)
    } catch (error) {
      setActionError(
        error instanceof ApiError ? error.message : 'Could not save replay URL.'
      )
    }
  }

  async function handleReplay() {
    if (!selectedEventId) return

    setReplaying(true)
    setActionError(null)

    try {
      const replay = await replayEvent(token, selectedEventId, {
        targetUrl: replayUrl.trim() || undefined,
      })
      queryClient.setQueryData(eventReplaysQueryKey(selectedEventId), (current: typeof replays | undefined) => [
        replay,
        ...(current ?? []),
      ])
    } catch (error) {
      setActionError(error instanceof ApiError ? error.message : 'Replay failed.')
    } finally {
      setReplaying(false)
    }
  }

  async function handleCopyUrl() {
    if (!ingestUrl) return
    await navigator.clipboard.writeText(ingestUrl)
    setCopied(true)
    window.setTimeout(() => setCopied(false), 2000)
  }

  async function handleCopyEvent(action: 'json' | 'curl') {
    if (!eventDetail) return

    const text = action === 'json' ? buildEventJson(eventDetail) : buildEventCurl(eventDetail)
    if (!text) return

    await navigator.clipboard.writeText(text)
    setCopiedAction(action)
    window.setTimeout(() => setCopiedAction(null), 2000)
  }

  function startEditingName() {
    if (!inbox) return
    setNameDraft(inbox.name)
    setEditingName(true)
  }

  function cancelEditingName() {
    setEditingName(false)
    setNameDraft('')
  }

  async function handleSaveName() {
    if (!inbox) return

    const trimmed = nameDraft.trim()
    if (!trimmed) {
      setActionError('Inbox name cannot be empty.')
      return
    }

    if (trimmed === inbox.name) {
      cancelEditingName()
      return
    }

    setSavingName(true)
    setActionError(null)

    try {
      const updated = await updateInbox(token, inbox.id, { name: trimmed })
      queryClient.setQueryData(inboxQueryKey(inbox.id), updated)
      inboxPage?.setTitle(updated.name)
      cancelEditingName()
    } catch (error) {
      setActionError(
        error instanceof ApiError ? error.message : 'Could not rename inbox.'
      )
    } finally {
      setSavingName(false)
    }
  }

  async function handleDeleteInbox() {
    if (!inbox) return

    const confirmed = await confirm({
      title: 'Delete inbox',
      description: (
        <>
          This permanently deletes <strong>{inbox.name}</strong> and all captured events. This
          action cannot be undone.
        </>
      ),
      confirmLabel: 'Delete inbox',
      destructive: true,
    })

    if (!confirmed) return

    setDeleting(true)
    setActionError(null)

    try {
      await deleteInbox(token, inbox.id)
      router.push('/inboxes')
    } catch (error) {
      setActionError(
        error instanceof ApiError ? error.message : 'Could not delete inbox.'
      )
    } finally {
      setDeleting(false)
    }
  }

  if (!inboxLoading && !inbox) {
    return (
      <div className="mx-auto max-w-lg py-16 text-center">
        <p className="text-sm text-muted-foreground">{loadError ?? 'Inbox not found.'}</p>
        <Button variant="outline" className="mt-4" asChild>
          <Link href="/inboxes">Back to inboxes</Link>
        </Button>
      </div>
    )
  }

  return (
    <div className="mx-auto flex w-full max-w-6xl flex-col gap-6">
      <header className="flex flex-wrap items-start justify-between gap-4">
        {inboxLoading || !inbox ? (
          <InboxHeaderSkeleton />
        ) : (
          <div className="min-w-0 space-y-2">
            <Button variant="ghost" size="sm" className="-ml-2 h-8 px-2" asChild>
              <Link href="/inboxes">
                <IconArrowLeft className="size-4" aria-hidden />
                Inboxes
              </Link>
            </Button>
            <div>
              {editingName ? (
                <div className="flex flex-wrap items-center gap-2">
                  <Input
                    value={nameDraft}
                    onChange={(event) => setNameDraft(event.target.value)}
                    className="h-9 max-w-sm font-ui text-lg font-semibold"
                    autoFocus
                    onKeyDown={(event) => {
                      if (event.key === 'Enter') void handleSaveName()
                      if (event.key === 'Escape') cancelEditingName()
                    }}
                  />
                  <Button
                    type="button"
                    size="sm"
                    disabled={savingName}
                    onClick={() => void handleSaveName()}
                  >
                    {savingName ? (
                      <Loader size="sm" tone="inherit" />
                    ) : (
                      <IconCheck className="size-4" aria-hidden />
                    )}
                    Save
                  </Button>
                  <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    disabled={savingName}
                    onClick={cancelEditingName}
                  >
                    <IconX className="size-4" aria-hidden />
                    Cancel
                  </Button>
                </div>
              ) : (
                <div className="flex items-center gap-2">
                  <h1 className="font-ui text-2xl font-semibold tracking-tight text-foreground">
                    {inbox.name}
                  </h1>
                  <Button
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    onClick={startEditingName}
                    aria-label="Rename inbox"
                  >
                    <IconPencil className="size-4" aria-hidden />
                  </Button>
                </div>
              )}
              <p className="mt-1 font-mono text-sm text-muted-foreground">/i/{inbox.id}</p>
            </div>
          </div>
        )}

        <div className="flex flex-wrap items-center gap-2">
          <Button type="button" variant="outline" size="sm" onClick={refresh}>
            <IconRefresh className="size-3.5" aria-hidden />
            Refresh
          </Button>
          <Button
            type="button"
            variant="outline"
            size="sm"
            className="text-destructive hover:text-destructive"
            disabled={deleting || !inbox}
            onClick={() => void handleDeleteInbox()}
          >
            {deleting ? (
              <Loader size="sm" tone="inherit" />
            ) : (
              <IconTrash className="size-3.5" aria-hidden />
            )}
            {deleting ? 'Deleting…' : 'Delete'}
          </Button>
        </div>
      </header>

      <section
        aria-labelledby="ingest-url-heading"
        className="rounded-xl border border-border bg-card p-4 sm:p-5"
      >
        <p
          id="ingest-url-heading"
          className="mb-3 text-[0.6875rem] font-medium tracking-[0.14em] text-primary uppercase"
        >
          Your ingest URL
        </p>
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
          {inboxLoading ? (
            <>
              <Skeleton className="h-10 flex-1 rounded-lg" />
              <Skeleton className="h-9 w-28 shrink-0 rounded-lg" />
            </>
          ) : (
            <>
              <code className="min-w-0 flex-1 truncate rounded-lg border border-border bg-muted/30 px-3 py-2.5 font-mono text-sm text-foreground">
                {ingestUrl}
              </code>
              <Button
                type="button"
                variant="outline"
                size="sm"
                className="shrink-0"
                onClick={() => void handleCopyUrl()}
                aria-label={copied ? 'URL copied' : 'Copy ingest URL'}
              >
                <IconCopy className="size-3.5" aria-hidden />
                {copied ? 'Copied' : 'Copy URL'}
              </Button>
            </>
          )}
        </div>
      </section>

      {loadError ? (
        <p className="text-sm text-destructive" role="alert">
          {loadError}
        </p>
      ) : null}

      <div className="grid gap-4 lg:grid-cols-[minmax(0,18rem)_minmax(0,1fr)] lg:gap-6">
        <section aria-labelledby="events-heading">
          <div className="mb-3 px-1">
            <h2 id="events-heading" className="font-ui text-sm font-semibold text-foreground">
              Events
              <span className="ml-1.5 font-normal tabular-nums text-muted-foreground">
                ({eventCount})
              </span>
            </h2>
          </div>
          <div className="max-h-128 overflow-y-auto rounded-xl">
            <DataTable
              model="inbox-events"
              token={token}
              context={{ inboxId }}
              refetchInterval={POLL_INTERVAL_MS}
              selectedRowId={selectedEventId}
              onRowClickAction={(event) => setSelectedEventId(event.id)}
              showPagination
              onDataChangeAction={({ rows, total }) => {
                setEventsTotal(total)
                setSelectedEventId((current) => current ?? rows[0]?.id ?? null)
              }}
            />
          </div>
        </section>

        <section className="flex min-w-0 flex-col gap-4">
          {detailLoading ? (
            <EventInspectorSkeleton />
          ) : (
            <SectionPanel
              title="Event detail"
              actions={
                eventDetail ? (
                  <>
                    <Button
                      type="button"
                      variant="outline"
                      size="sm"
                      onClick={() => void handleCopyEvent('json')}
                    >
                      <IconCopy className="size-3.5" aria-hidden />
                      {copiedAction === 'json' ? 'Copied' : 'JSON'}
                    </Button>
                    <Button
                      type="button"
                      variant="outline"
                      size="sm"
                      onClick={() => void handleCopyEvent('curl')}
                    >
                      <IconCopy className="size-3.5" aria-hidden />
                      {copiedAction === 'curl' ? 'Copied' : 'cURL'}
                    </Button>
                  </>
                ) : undefined
              }
            >
              {!eventDetail ? (
                <p className="px-4 py-10 text-sm text-muted-foreground">
                  {selectedEventId
                    ? 'Could not load this event. Select another or refresh.'
                    : 'Select an event to inspect headers and body.'}
                </p>
              ) : (
                <div className="space-y-5 p-4">
                  <div className="flex flex-wrap items-center gap-3 text-sm">
                    <MethodBadge method={eventDetail.method} />
                    <span className="min-w-0 truncate font-mono text-foreground">
                      {eventDetail.path}
                    </span>
                    <span className="text-muted-foreground">
                      {formatRelativeTime(eventDetail.receivedAt)}
                    </span>
                  </div>

                  <div>
                    <h3 className="mb-2 text-[0.6875rem] font-medium tracking-[0.12em] text-muted-foreground uppercase">
                      Headers
                    </h3>
                    <JsonBlock value={eventDetail.headers} />
                  </div>

                  <div>
                    <h3 className="mb-2 text-[0.6875rem] font-medium tracking-[0.12em] text-muted-foreground uppercase">
                      Body
                    </h3>
                    {eventDetail.bodyJson ? (
                      <JsonBlock value={eventDetail.bodyJson} />
                    ) : (
                      <pre className="max-h-80 overflow-auto rounded-lg border border-border bg-muted/30 p-3 font-mono text-xs whitespace-pre-wrap text-foreground">
                        {eventDetail.bodyText ?? '(empty)'}
                      </pre>
                    )}
                  </div>
                </div>
              )}
            </SectionPanel>
          )}

          {replaysLoading ? (
            <ReplayPanelSkeleton />
          ) : (
            <SectionPanel
              title="Replay"
              description="Fire the same request to your local server or staging endpoint."
            >
              <div className="space-y-4 p-4">
                <div className="flex flex-col gap-3 sm:flex-row">
                  <Input
                    value={replayUrl}
                    onChange={(event) => setReplayUrl(event.target.value)}
                    placeholder="http://localhost:3000/webhooks/stripe"
                    className="font-mono text-sm"
                  />
                  <div className="flex shrink-0 gap-2">
                    <Button type="button" variant="outline" onClick={() => void handleSaveReplayUrl()}>
                      Save
                    </Button>
                    <Button
                      type="button"
                      disabled={!selectedEventId || replaying}
                      onClick={() => void handleReplay()}
                    >
                      {replaying ? (
                        <>
                          <Loader size="sm" tone="inherit" />
                          Replaying…
                        </>
                      ) : (
                        <>
                          <IconPlayerPlay className="size-4" aria-hidden />
                          Replay
                        </>
                      )}
                    </Button>
                  </div>
                </div>

                {replays.length > 0 ? (
                  <div className="space-y-3">
                    <Separator />
                    <h3 className="text-[0.6875rem] font-medium tracking-[0.12em] text-muted-foreground uppercase">
                      History
                    </h3>
                    <ul className="divide-y divide-border rounded-lg border border-border">
                      {replays.map((replay) => (
                        <li key={replay.id} className="px-3 py-3 text-sm">
                          <div className="flex flex-wrap items-center gap-2">
                            <ReplayStatus replay={replay} />
                            {replay.durationMs !== null ? (
                              <span className="text-muted-foreground tabular-nums">
                                {replay.durationMs}ms
                              </span>
                            ) : null}
                            <span className="min-w-0 truncate font-mono text-xs text-muted-foreground">
                              {replay.targetUrl}
                            </span>
                          </div>
                          {replay.errorMessage ? (
                            <p className="mt-2 text-destructive">{replay.errorMessage}</p>
                          ) : null}
                          {replay.responseHeaders ? (
                            <div className="mt-3">
                              <p className="mb-1.5 text-[0.6875rem] font-medium tracking-[0.12em] text-muted-foreground uppercase">
                                Response headers
                              </p>
                              <JsonBlock value={replay.responseHeaders} />
                            </div>
                          ) : null}
                          {replay.responseBody ? (
                            <pre className="mt-2 max-h-40 overflow-auto font-mono text-xs whitespace-pre-wrap text-foreground">
                              {replay.responseBody}
                            </pre>
                          ) : null}
                        </li>
                      ))}
                    </ul>
                  </div>
                ) : null}
              </div>
            </SectionPanel>
          )}
        </section>
      </div>
    </div>
  )
}