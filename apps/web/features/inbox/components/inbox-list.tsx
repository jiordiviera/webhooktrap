'use client'

import Link from 'next/link'
import { useRouter } from 'next/navigation'
import { useState } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import { IconCopy, IconExternalLink, IconPlus } from '@tabler/icons-react'
import { Button } from '@workspace/ui/components/button'
import { CreateInboxDialog } from '@/app/components/dashboard/create-inbox-dialog'
import { DataTable } from '@/features/data-table/components/data-table'
import { type InboxSummary } from '@/lib/inboxes'

function InboxActionsCell({
  inbox,
  onCopy,
  copiedId,
}: {
  inbox: InboxSummary
  onCopy: (id: string, url: string) => void
  copiedId: string | null
}) {
  const copied = copiedId === inbox.id

  return (
    <div className="flex items-center justify-end gap-1.5">
      <Button
        type="button"
        variant="outline"
        size="sm"
        onClick={(event) => {
          event.stopPropagation()
          void onCopy(inbox.id, inbox.ingestUrl)
        }}
        aria-label={copied ? 'URL copied' : `Copy ingest URL for ${inbox.name}`}
      >
        <IconCopy className="size-3.5" aria-hidden />
        <span className="sr-only sm:not-sr-only">{copied ? 'Copied' : 'Copy'}</span>
      </Button>
      <Button type="button" variant="ghost" size="sm" asChild>
        <Link href={`/i/${inbox.id}`} aria-label={`Open ${inbox.name}`}>
          <IconExternalLink className="size-3.5" aria-hidden />
          <span className="sr-only sm:not-sr-only">Open</span>
        </Link>
      </Button>
    </div>
  )
}

export function InboxList() {
  const router = useRouter()
  const queryClient = useQueryClient()
  const [createOpen, setCreateOpen] = useState(false)
  const [copiedId, setCopiedId] = useState<string | null>(null)

  function handleInboxCreated(inbox: InboxSummary) {
    void queryClient.invalidateQueries({ queryKey: ['data-table', 'inboxes'] })
    router.push(`/i/${inbox.id}`)
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
          <p className="mb-2 text-[0.6875rem] font-medium tracking-[0.14em] text-primary uppercase">
            Ingest endpoints
          </p>
          <h1
            id="inboxes-heading"
            className="text-[clamp(1.75rem,3vw,2.25rem)] font-semibold tracking-tight text-foreground"
          >
            Inboxes
          </h1>
          <p className="mt-2 max-w-xl text-[0.9375rem] leading-relaxed text-muted-foreground">
            Each inbox is a public URL. Point Stripe, GitHub, or curl at it, then inspect and replay
            every byte.
          </p>
        </div>

        <Button type="button" onClick={() => setCreateOpen(true)} className="shrink-0">
          <IconPlus className="size-4" aria-hidden />
          New inbox
        </Button>
      </div>

      <CreateInboxDialog
        open={createOpen}
        onOpenChangeAction={setCreateOpen}
        onCreatedAction={handleInboxCreated}
      />

      <div className="mt-6 md:mt-8">
        <DataTable
          model="inboxes"
          cellRenderers={{
            actions: (inbox) => (
              <InboxActionsCell inbox={inbox} onCopy={handleCopy} copiedId={copiedId} />
            ),
          }}
          emptyState={
            <div className="space-y-2">
              <p className="text-base font-medium text-foreground">No inboxes yet</p>
              <p className="text-sm text-muted-foreground">
                Create an inbox to get a public ingest URL.
              </p>
              <Button type="button" className="mt-2" onClick={() => setCreateOpen(true)}>
                <IconPlus className="size-4" aria-hidden />
                Create inbox
              </Button>
            </div>
          }
        />
      </div>
    </section>
  )
}