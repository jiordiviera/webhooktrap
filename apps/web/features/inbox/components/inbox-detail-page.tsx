'use client'

import Link from 'next/link'
import { useRouter } from 'next/navigation'
import { useCallback, useEffect, useState } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import {
  IconArrowLeft,
  IconCheck,
  IconCopy,
  IconPencil,
  IconRefresh,
  IconTrash,
  IconX,
} from '@tabler/icons-react'
import { Button } from '@workspace/ui/components/button'
import { Input } from '@workspace/ui/components/input'
import { Loader } from '@workspace/ui/components/loader'

import { Skeleton } from '@workspace/ui/components/skeleton'

import { DataTable } from '@/features/data-table/components/data-table'
import { inboxQueryKey, useInboxQuery } from '@/features/inbox/hooks/use-inbox-query'
import { EventDetailSheet } from '@/features/inbox/components/event-detail-sheet'
import { ApiError } from '@/lib/api'
import { useConfirm } from '@/contexts/confirm-context'
import { useInboxPageTitle } from '@/features/inbox/context/inbox-page-context'
import { deleteInbox, updateInbox } from '@/lib/inboxes'

const POLL_INTERVAL_MS = 3000

function InboxHeaderSkeleton() {
  return (
    <div className="min-w-0 space-y-2" aria-busy="true">
      <Skeleton className="h-8 w-24 rounded-lg" />
      <Skeleton className="h-8 w-48 max-w-full rounded-lg" />
      <Skeleton className="h-4 w-32 rounded-md" />
    </div>
  )
}

export function InboxDetailPage({ inboxId }: { inboxId: string }) {
  const router = useRouter()
  const queryClient = useQueryClient()
  const confirm = useConfirm()
  const inboxPage = useInboxPageTitle()

  const inboxQuery = useInboxQuery(inboxId)
  const inbox = inboxQuery.data ?? null

  const [selectedEventId, setSelectedEventId] = useState<string | null>(null)
  const [eventsTotal, setEventsTotal] = useState(0)
  const [actionError, setActionError] = useState<string | null>(null)
  const [copied, setCopied] = useState(false)
  const [editingName, setEditingName] = useState(false)
  const [nameDraft, setNameDraft] = useState('')
  const [savingName, setSavingName] = useState(false)
  const [deleting, setDeleting] = useState(false)

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
  }, [inboxId, inboxQuery, queryClient])

  async function handleCopyUrl() {
    if (!ingestUrl) return
    await navigator.clipboard.writeText(ingestUrl)
    setCopied(true)
    window.setTimeout(() => setCopied(false), 2000)
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
      const updated = await updateInbox(inbox.id, { name: trimmed })
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
      await deleteInbox(inbox.id)
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

      <section aria-labelledby="events-heading">
        <div className="mb-3 px-1">
          <h2 id="events-heading" className="font-ui text-sm font-semibold text-foreground">
            Events
            <span className="ml-1.5 font-normal tabular-nums text-muted-foreground">
              ({eventCount})
            </span>
          </h2>
        </div>
        <div className="">
          <DataTable
            model="inbox-events"
            context={{ inboxId }}
            refetchInterval={POLL_INTERVAL_MS}
            selectedRowId={selectedEventId}
            onRowClickAction={(event) => setSelectedEventId(event.id)}
            showPagination
            onDataChangeAction={({ total }) => {
              setEventsTotal(total)
            }}
          />
        </div>
      </section>

      <EventDetailSheet
        inboxId={inboxId}
        selectedEventId={selectedEventId}
        onClose={() => setSelectedEventId(null)}
      />
    </div>
  )
}