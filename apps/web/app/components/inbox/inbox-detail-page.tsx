'use client'

import Link from 'next/link'
import { useRouter } from 'next/navigation'
import { useCallback, useEffect, useMemo, useState } from 'react'
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
import { cn } from '@workspace/ui/lib/utils'
import { ApiError } from '@/lib/api'
import { useConfirm } from '@/contexts/confirm-context'
import { useInboxPageTitle } from '@/features/inbox/context/inbox-page-context'
import { buildEventCurl, buildEventJson } from '@/lib/event-copy'
import {
  fetchEvent,
  fetchEventReplays,
  fetchInboxEvents,
  replayEvent,
  type EventDetail,
  type EventSummary,
  type ReplayRecord,
} from '@/lib/events'
import {
  deleteInbox,
  fetchInbox,
  formatRelativeTime,
  inboxPublicUrl,
  updateInbox,
  type InboxSummary,
} from '@/lib/inboxes'

const POLL_INTERVAL_MS = 3000

function formatBytes(bytes: number) {
  if (bytes < 1024) return `${bytes} B`
  return `${(bytes / 1024).toFixed(1)} KB`
}

function EventListItem({
  event,
  selected,
  onSelect,
}: {
  event: EventSummary
  selected: boolean
  onSelect: () => void
}) {
  return (
    <button
      type="button"
      onClick={onSelect}
      className={cn(
        'flex w-full flex-col gap-1 border-b border-border px-4 py-3 text-left transition-colors last:border-b-0',
        selected ? 'bg-muted/60' : 'hover:bg-muted/40'
      )}
    >
      <div className="flex items-center gap-2">
        <span className="rounded bg-primary/10 px-1.5 py-0.5 font-mono text-[0.6875rem] font-semibold text-primary">
          {event.method}
        </span>
        <span className="truncate text-sm text-foreground">{event.path}</span>
      </div>
      <span className="text-xs text-muted-foreground">
        {formatRelativeTime(event.receivedAt)} · {formatBytes(event.sizeBytes)}
      </span>
    </button>
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

export function InboxDetailPage({ inboxId, token }: { inboxId: string; token: string }) {
  const router = useRouter()
  const confirm = useConfirm()
  const inboxPage = useInboxPageTitle()
  const [inbox, setInbox] = useState<InboxSummary | null>(null)
  const [events, setEvents] = useState<EventSummary[]>([])
  const [selectedEventId, setSelectedEventId] = useState<string | null>(null)
  const [eventDetail, setEventDetail] = useState<EventDetail | null>(null)
  const [replays, setReplays] = useState<ReplayRecord[]>([])
  const [replayUrl, setReplayUrl] = useState('')
  const [loading, setLoading] = useState(true)
  const [replaying, setReplaying] = useState(false)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)
  const [copied, setCopied] = useState(false)
  const [copiedAction, setCopiedAction] = useState<'json' | 'curl' | null>(null)
  const [editingName, setEditingName] = useState(false)
  const [nameDraft, setNameDraft] = useState('')
  const [savingName, setSavingName] = useState(false)
  const [deleting, setDeleting] = useState(false)

  const ingestUrl = inbox ? inboxPublicUrl(inbox.ingestUrl) : ''

  const loadInbox = useCallback(async () => {
    const next = await fetchInbox(token, inboxId)
    setInbox(next)
    setReplayUrl(next.defaultReplayUrl ?? '')
    return next
  }, [inboxId, token])

  const loadEvents = useCallback(async () => {
    const next = await fetchInboxEvents(token, inboxId)
    setEvents(next)
    return next
  }, [inboxId, token])

  const refresh = useCallback(async () => {
    try {
      await Promise.all([loadInbox(), loadEvents()])
      setErrorMessage(null)
    } catch (error) {
      setErrorMessage(
        error instanceof ApiError ? error.message : 'Could not load inbox. Try again.'
      )
    }
  }, [loadEvents, loadInbox])

  useEffect(() => {
    let cancelled = false

    async function init() {
      setLoading(true)
      try {
        const [, eventList] = await Promise.all([loadInbox(), loadEvents()])
        if (cancelled) return
        setErrorMessage(null)
        if (eventList[0]) {
          setSelectedEventId(eventList[0].id)
        }
      } catch (error) {
        if (!cancelled) {
          setErrorMessage(
            error instanceof ApiError ? error.message : 'Could not load inbox. Try again.'
          )
        }
      } finally {
        if (!cancelled) setLoading(false)
      }
    }

    void init()

    const interval = window.setInterval(() => {
      void loadEvents().catch(() => undefined)
    }, POLL_INTERVAL_MS)

    return () => {
      cancelled = true
      window.clearInterval(interval)
    }
  }, [loadEvents, loadInbox])

  useEffect(() => {
    if (!inboxPage || !inbox) return

    inboxPage.setTitle(inbox.name)

    return () => {
      inboxPage.setTitle(null)
    }
  }, [inbox, inboxPage])

  useEffect(() => {
    if (!selectedEventId) {
      setEventDetail(null)
      setReplays([])
      return
    }

    let cancelled = false

    async function loadDetail(eventId: string) {
      try {
        const [detail, replayList] = await Promise.all([
          fetchEvent(token, eventId),
          fetchEventReplays(token, eventId),
        ])
        if (!cancelled) {
          setEventDetail(detail)
          setReplays(replayList)
        }
      } catch {
        if (!cancelled) {
          setEventDetail(null)
          setReplays([])
        }
      }
    }

    void loadDetail(selectedEventId)

    return () => {
      cancelled = true
    }
  }, [selectedEventId, token])

  async function handleSaveReplayUrl() {
    if (!inbox) return

    try {
      const updated = await updateInbox(token, inbox.id, {
        defaultReplayUrl: replayUrl.trim() || null,
      })
      setInbox(updated)
      setReplayUrl(updated.defaultReplayUrl ?? '')
    } catch (error) {
      setErrorMessage(
        error instanceof ApiError ? error.message : 'Could not save replay URL.'
      )
    }
  }

  async function handleReplay() {
    if (!selectedEventId) return

    setReplaying(true)
    setErrorMessage(null)

    try {
      const replay = await replayEvent(token, selectedEventId, {
        targetUrl: replayUrl.trim() || undefined,
      })
      setReplays((current) => [replay, ...current])
    } catch (error) {
      setErrorMessage(error instanceof ApiError ? error.message : 'Replay failed.')
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
      setErrorMessage('Inbox name cannot be empty.')
      return
    }

    if (trimmed === inbox.name) {
      cancelEditingName()
      return
    }

    setSavingName(true)
    setErrorMessage(null)

    try {
      const updated = await updateInbox(token, inbox.id, { name: trimmed })
      setInbox(updated)
      inboxPage?.setTitle(updated.name)
      cancelEditingName()
    } catch (error) {
      setErrorMessage(
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
    setErrorMessage(null)

    try {
      await deleteInbox(token, inbox.id)
      router.push('/inboxes')
    } catch (error) {
      setErrorMessage(
        error instanceof ApiError ? error.message : 'Could not delete inbox.'
      )
    } finally {
      setDeleting(false)
    }
  }

  if (loading) {
    return (
      <div className="flex min-h-[40vh] items-center justify-center">
        <Loader layout="centered" label="Loading inbox" />
      </div>
    )
  }

  if (!inbox) {
    return (
      <div className="mx-auto max-w-lg py-16 text-center">
        <p className="text-sm text-muted-foreground">{errorMessage ?? 'Inbox not found.'}</p>
        <Button variant="outline" className="mt-4" asChild>
          <Link href="/inboxes">Back to inboxes</Link>
        </Button>
      </div>
    )
  }

  return (
    <div className="mx-auto flex w-full max-w-6xl flex-col gap-6">
      <div className="flex flex-wrap items-start justify-between gap-4">
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
                  className="h-9 max-w-sm text-lg font-semibold"
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
                <h1 className="text-2xl font-semibold tracking-tight text-foreground">
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

        <div className="flex flex-wrap items-center gap-2">
          <code className="max-w-md truncate rounded-md border border-border bg-muted/40 px-2 py-1 font-mono text-xs">
            {ingestUrl}
          </code>
          <Button type="button" variant="outline" size="sm" onClick={() => void handleCopyUrl()}>
            <IconCopy className="size-3.5" aria-hidden />
            {copied ? 'Copied' : 'Copy URL'}
          </Button>
          <Button type="button" variant="outline" size="sm" onClick={() => void refresh()}>
            <IconRefresh className="size-3.5" aria-hidden />
            Refresh
          </Button>
          <Button
            type="button"
            variant="outline"
            size="sm"
            className="text-destructive hover:text-destructive"
            disabled={deleting}
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
      </div>

      {errorMessage ? (
        <p className="text-sm text-destructive" role="alert">
          {errorMessage}
        </p>
      ) : null}

      <div className="grid gap-4 lg:grid-cols-[minmax(0,18rem)_minmax(0,1fr)] lg:gap-6">
        <section className="overflow-hidden rounded-2xl border border-border bg-card">
          <div className="border-b border-border px-4 py-3">
            <h2 className="text-sm font-semibold text-foreground">Events ({events.length})</h2>
          </div>
          {events.length === 0 ? (
            <div className="px-4 py-10 text-center text-sm text-muted-foreground">
              Waiting for the first webhook. Send a POST to the ingest URL above.
            </div>
          ) : (
            <div className="max-h-128 overflow-y-auto">
              {events.map((event) => (
                <EventListItem
                  key={event.id}
                  event={event}
                  selected={event.id === selectedEventId}
                  onSelect={() => setSelectedEventId(event.id)}
                />
              ))}
            </div>
          )}
        </section>

        <section className="flex flex-col gap-4">
          <div className="overflow-hidden rounded-2xl border border-border bg-card">
            <div className="border-b border-border px-5 py-4">
              <h2 className="text-sm font-semibold text-foreground">Event detail</h2>
            </div>

            {!eventDetail ? (
              <p className="px-5 py-10 text-sm text-muted-foreground">
                Select an event to inspect headers and body.
              </p>
            ) : (
              <div className="space-y-5 px-5 py-5">
                <div className="flex flex-wrap items-center justify-between gap-3">
                  <div className="flex flex-wrap gap-3 text-sm">
                    <span className="font-mono font-semibold text-primary">{eventDetail.method}</span>
                    <span className="font-mono text-foreground">{eventDetail.path}</span>
                    <span className="text-muted-foreground">
                      {formatRelativeTime(eventDetail.receivedAt)}
                    </span>
                  </div>
                  <div className="flex gap-2">
                    <Button
                      type="button"
                      variant="outline"
                      size="sm"
                      onClick={() => void handleCopyEvent('json')}
                    >
                      <IconCopy className="size-3.5" aria-hidden />
                      {copiedAction === 'json' ? 'Copied' : 'Copy JSON'}
                    </Button>
                    <Button
                      type="button"
                      variant="outline"
                      size="sm"
                      onClick={() => void handleCopyEvent('curl')}
                    >
                      <IconCopy className="size-3.5" aria-hidden />
                      {copiedAction === 'curl' ? 'Copied' : 'Copy cURL'}
                    </Button>
                  </div>
                </div>

                <div>
                  <h3 className="mb-2 text-xs font-medium tracking-wider text-muted-foreground uppercase">
                    Headers
                  </h3>
                  <JsonBlock value={eventDetail.headers} />
                </div>

                <div>
                  <h3 className="mb-2 text-xs font-medium tracking-wider text-muted-foreground uppercase">
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
          </div>

          <div className="overflow-hidden rounded-2xl border border-border bg-card">
            <div className="border-b border-border px-5 py-4">
              <h2 className="text-sm font-semibold text-foreground">Replay</h2>
              <p className="mt-1 text-sm text-muted-foreground">
                Fire the same request to your local server or staging endpoint.
              </p>
            </div>

            <div className="space-y-4 px-5 py-5">
              <div className="flex flex-col gap-3 sm:flex-row">
                <Input
                  value={replayUrl}
                  onChange={(event) => setReplayUrl(event.target.value)}
                  placeholder="http://localhost:3000/webhooks/stripe"
                  className="font-mono text-sm"
                />
                <div className="flex gap-2">
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
                  <h3 className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                    History
                  </h3>
                  {replays.map((replay) => (
                    <div
                      key={replay.id}
                      className="rounded-lg border border-border bg-muted/20 px-3 py-3 text-sm"
                    >
                      <div className="flex flex-wrap items-center gap-2">
                        {replay.statusCode ? (
                          <span
                            className={cn(
                              'font-mono font-semibold',
                              replay.statusCode >= 400 ? 'text-destructive' : 'text-primary'
                            )}
                          >
                            {replay.statusCode}
                          </span>
                        ) : (
                          <span className="font-mono font-semibold text-destructive">
                            {replay.errorCode ?? 'ERROR'}
                          </span>
                        )}
                        {replay.durationMs !== null ? (
                          <span className="text-muted-foreground">{replay.durationMs}ms</span>
                        ) : null}
                        <span className="truncate font-mono text-xs text-muted-foreground">
                          {replay.targetUrl}
                        </span>
                      </div>
                      {replay.errorMessage ? (
                        <p className="mt-2 text-destructive">{replay.errorMessage}</p>
                      ) : null}
                      {replay.responseHeaders ? (
                        <div className="mt-2">
                          <p className="mb-1 text-xs font-medium tracking-wider text-muted-foreground uppercase">
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
                    </div>
                  ))}
                </div>
              ) : null}
            </div>
          </div>
        </section>
      </div>
    </div>
  )
}