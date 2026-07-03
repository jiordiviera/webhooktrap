'use client'

import { useState } from 'react'
import Link from 'next/link'
import { useRouter } from 'next/navigation'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@workspace/ui/components/dialog'
import { Loader } from '@workspace/ui/components/loader'
import { useAuth } from '@/contexts/auth-context'
import { apiFetch } from '@/lib/api'

type InboxResponse = {
  data: {
    inbox: {
      id: string
    }
  }
}

type CreateInboxChoiceDialogProps = {
  open: boolean
  onOpenChangeAction: (open: boolean) => void
}

export function CreateInboxChoiceDialog({ open, onOpenChangeAction }: CreateInboxChoiceDialogProps) {
  const router = useRouter()
  const { isAuthenticated } = useAuth()
  const [creating, setCreating] = useState(false)
  const [error, setError] = useState<string | null>(null)

  async function create(persist: boolean) {
    setError(null)
    setCreating(true)

    try {
      const body = await apiFetch<InboxResponse>('/inboxes', {
        method: 'POST',
        skipAuth: !persist,
        body: JSON.stringify({ name: 'My inbox' }),
      })
      router.push(`/i/${body.data.inbox.id}`)
    } catch {
      setError('Could not reach the API. Try again.')
      setCreating(false)
    }
  }

  return (
    <Dialog open={open} onOpenChange={(next) => !creating && onOpenChangeAction(next)}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Open an inbox</DialogTitle>
          <DialogDescription>Choose how long you want to keep it.</DialogDescription>
        </DialogHeader>

        <div className="grid gap-3 py-2">
          <button
            type="button"
            onClick={() => void create(false)}
            disabled={creating}
            className="rounded-xl border border-border p-4 text-left transition-colors hover:border-primary/40 hover:bg-muted/30 disabled:pointer-events-none disabled:opacity-60"
          >
            <p className="text-sm font-medium text-foreground">Just try it</p>
            <p className="mt-1 text-sm text-muted-foreground">
              No account needed. Expires in 48 hours.
            </p>
          </button>

          {isAuthenticated ? (
            <button
              type="button"
              onClick={() => void create(true)}
              disabled={creating}
              className="rounded-xl border border-border p-4 text-left transition-colors hover:border-primary/40 hover:bg-muted/30 disabled:pointer-events-none disabled:opacity-60"
            >
              <p className="text-sm font-medium text-foreground">Save to my account</p>
              <p className="mt-1 text-sm text-muted-foreground">Kept until you delete it.</p>
            </button>
          ) : (
            <Link
              href="/register"
              className="rounded-xl border border-border p-4 text-left transition-colors hover:border-primary/40 hover:bg-muted/30"
            >
              <p className="text-sm font-medium text-foreground">Save to an account</p>
              <p className="mt-1 text-sm text-muted-foreground">
                Create a free account, then it&apos;s kept until you delete it.
              </p>
            </Link>
          )}
        </div>

        {creating && (
          <div className="flex items-center justify-center gap-2 text-sm text-muted-foreground">
            <Loader size="sm" tone="inherit" />
            Opening inbox…
          </div>
        )}

        {error && (
          <p className="text-sm text-destructive" role="alert">
            {error}
          </p>
        )}
      </DialogContent>
    </Dialog>
  )
}
