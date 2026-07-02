'use client'

import Link from 'next/link'
import {
  IconArrowLeft,
  IconCheck,
  IconPencil,
  IconRefresh,
  IconTrash,
  IconX,
} from '@tabler/icons-react'
import { Button } from '@workspace/ui/components/button'
import { Input } from '@workspace/ui/components/input'
import { Loader } from '@workspace/ui/components/loader'
import { Skeleton } from '@workspace/ui/components/skeleton'
import { InboxDTO } from '@workspace/types'

function InboxHeaderSkeleton() {
  return (
    <div className="min-w-0 space-y-2" aria-busy="true">
      <Skeleton className="h-8 w-24 rounded-lg" />
      <Skeleton className="h-8 w-48 max-w-full rounded-lg" />
      <Skeleton className="h-4 w-32 rounded-md" />
    </div>
  )
}

interface InboxHeaderProps {
  inbox: InboxDTO | null
  inboxLoading: boolean
  editingName: boolean
  nameDraft: string
  savingName: boolean
  deleting: boolean
  onNameDraftChange: (value: string) => void
  onSaveName: () => void
  onCancelEdit: () => void
  onStartEdit: () => void
  onDelete: () => void
  onRefresh: () => void
}

export function InboxHeader({
  inbox,
  inboxLoading,
  editingName,
  nameDraft,
  savingName,
  deleting,
  onNameDraftChange,
  onSaveName,
  onCancelEdit,
  onStartEdit,
  onDelete,
  onRefresh,
}: InboxHeaderProps) {
  if (inboxLoading || !inbox) {
    return <InboxHeaderSkeleton />
  }

  return (
    <>
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
                onChange={(event) => onNameDraftChange(event.target.value)}
                className="h-9 max-w-sm text-lg font-semibold"
                autoFocus
                onKeyDown={(event) => {
                  if (event.key === 'Enter') onSaveName()
                  if (event.key === 'Escape') onCancelEdit()
                }}
              />
              <Button type="button" size="sm" disabled={savingName} onClick={onSaveName}>
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
                onClick={onCancelEdit}
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
                onClick={onStartEdit}
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
        <Button type="button" variant="outline" size="sm" onClick={onRefresh}>
          <IconRefresh className="size-3.5" aria-hidden />
          Refresh
        </Button>
        <Button
          type="button"
          variant="outline"
          size="sm"
          className="text-destructive hover:text-destructive"
          disabled={deleting}
          onClick={onDelete}
        >
          {deleting ? (
            <Loader size="sm" tone="inherit" />
          ) : (
            <IconTrash className="size-3.5" aria-hidden />
          )}
          {deleting ? 'Deleting…' : 'Delete'}
        </Button>
      </div>
    </>
  )
}
