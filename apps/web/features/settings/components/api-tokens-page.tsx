'use client'

import { useCallback, useEffect, useState } from 'react'
import { IconKey, IconTrash } from '@tabler/icons-react'
import { Button } from '@workspace/ui/components/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@workspace/ui/components/dialog'
import { Field, FieldError, FieldGroup, FieldLabel } from '@workspace/ui/components/field'
import { Input } from '@workspace/ui/components/input'
import { Loader } from '@workspace/ui/components/loader'
import { Tooltip, TooltipContent, TooltipTrigger } from '@workspace/ui/components/tooltip'
import { ApiError } from '@/lib/api'
import { type ApiToken, type CreatedApiToken, createApiToken, fetchApiTokens, revokeApiToken } from '@/lib/api-tokens'

function formatDate(dateStr: string | null | undefined): string {
  if (!dateStr) return '—'
  return new Intl.DateTimeFormat('en', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  }).format(new Date(dateStr))
}

export function ApiTokensPage() {
  const [tokens, setTokens] = useState<ApiToken[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [creating, setCreating] = useState(false)
  const [newTokenName, setNewTokenName] = useState('')
  const [createError, setCreateError] = useState<string | null>(null)
  const [createdToken, setCreatedToken] = useState<CreatedApiToken | null>(null)
  const [confirmRevoke, setConfirmRevoke] = useState<number | null>(null)

  const loadTokens = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const result = await fetchApiTokens()
      setTokens(result)
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Failed to load tokens')
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    void loadTokens()
  }, [loadTokens])

  async function handleCreate(e: React.FormEvent) {
    e.preventDefault()
    if (!newTokenName.trim()) return

    setCreating(true)
    setCreateError(null)
    setCreatedToken(null)

    try {
      const token = await createApiToken(newTokenName.trim())
      setCreatedToken(token)
      setNewTokenName('')
      void loadTokens()
    } catch (err) {
      setCreateError(err instanceof ApiError ? err.message : 'Failed to create token')
    } finally {
      setCreating(false)
    }
  }

  async function handleRevoke(id: number) {
    try {
      await revokeApiToken(id)
      setConfirmRevoke(null)
      void loadTokens()
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Failed to revoke token')
    }
  }

  return (
    <div className="mx-auto w-full max-w-3xl">
      <div className="mb-8">
        <h1 className="text-2xl font-semibold tracking-tight text-foreground">API Tokens</h1>
        <p className="mt-1 text-sm text-muted-foreground">
          Personal access tokens for programmatic access to your inboxes and events.
        </p>
      </div>

      <section className="mb-8 rounded-2xl border border-border bg-card">
        <div className="border-b border-border px-6 py-5">
          <h2 className="text-sm font-semibold text-foreground">Create new token</h2>
          <p className="mt-1 text-sm text-muted-foreground">
            Give your token a name so you can identify it later.
          </p>
        </div>

        {createdToken ? (
          <div className="px-6 py-6">
            <p className="mb-1.5 text-sm font-medium text-primary">Token created</p>
            <p className="mb-3 text-sm text-muted-foreground">
              Copy this token now — you will not be able to see it again.
            </p>
            <div className="flex items-center gap-2">
              <code className="flex-1 rounded-lg border border-border bg-muted px-3 py-2 font-mono text-sm break-all select-all">
                {createdToken.token}
              </code>
              <Button
                variant="outline"
                size="sm"
                onClick={() => { void navigator.clipboard.writeText(createdToken.token) }}
              >
                Copy
              </Button>
            </div>
            <Button
              className="mt-4"
              variant="ghost"
              size="sm"
              onClick={() => setCreatedToken(null)}
            >
              Create another
            </Button>
          </div>
        ) : (
          <form onSubmit={handleCreate} className="px-6 py-6">
            <FieldGroup>
              <Field>
                <FieldLabel htmlFor="tokenName">Token name</FieldLabel>
                <div className="flex items-start gap-3">
                  <div className="flex-1">
                    <Input
                      id="tokenName"
                      placeholder="e.g. CLI, CI pipeline, staging"
                      value={newTokenName}
                      onChange={(e) => setNewTokenName(e.target.value)}
                    />
                    <FieldError>{createError}</FieldError>
                  </div>
                  <Button type="submit" disabled={creating || !newTokenName.trim()}>
                    {creating ? 'Creating…' : 'Generate'}
                  </Button>
                </div>
              </Field>
            </FieldGroup>
          </form>
        )}
      </section>

      <section className="rounded-2xl border border-border bg-card">
        <div className="border-b border-border px-6 py-5">
          <h2 className="text-sm font-semibold text-foreground">Active tokens</h2>
          <p className="mt-1 text-sm text-muted-foreground">
            Tokens that have access to your account. Revoke any you do not recognize.
          </p>
        </div>

        {loading ? (
          <div className="flex justify-center px-6 py-12">
            <Loader className="size-5 text-muted-foreground" />
          </div>
        ) : error ? (
          <div className="px-6 py-8 text-center">
            <p className="text-sm text-destructive">{error}</p>
            <Button variant="outline" size="sm" className="mt-3" onClick={loadTokens}>
              Retry
            </Button>
          </div>
        ) : tokens.length === 0 ? (
          <div className="px-6 py-12 text-center">
            <IconKey className="mx-auto mb-3 size-8 text-muted-foreground" stroke={1.5} />
            <p className="text-sm font-medium text-foreground">No tokens yet</p>
            <p className="mt-1 text-sm text-muted-foreground">
              Create a token above to get started.
            </p>
          </div>
        ) : (
          <ul className="divide-y divide-border">
            {tokens.map((token) => (
              <li
                key={token.id}
                className="flex items-center justify-between px-6 py-4"
              >
                <div className="min-w-0">
                  <p className="truncate text-sm font-medium text-foreground">
                    {token.name ?? 'Unnamed'}
                  </p>
                  <p className="mt-0.5 text-xs text-muted-foreground">
                    Created {formatDate(token.createdAt)}
                    {token.lastUsedAt ? ` · Last used ${formatDate(token.lastUsedAt)}` : ' · Never used'}
                  </p>
                </div>
                <Tooltip>
                  <TooltipTrigger asChild>
                    <Button
                      variant="ghost"
                      size="icon"
                      className="size-8 shrink-0 text-muted-foreground hover:text-destructive"
                      onClick={() => setConfirmRevoke(token.id)}
                    >
                      <IconTrash className="size-4" stroke={1.8} />
                    </Button>
                  </TooltipTrigger>
                  <TooltipContent>Revoke token</TooltipContent>
                </Tooltip>
              </li>
            ))}
          </ul>
        )}
      </section>

      <Dialog
        open={confirmRevoke !== null}
        onOpenChange={(open) => { if (!open) setConfirmRevoke(null) }}
      >
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Revoke token</DialogTitle>
            <DialogDescription>
              This will immediately revoke this token. Any service using it will lose access.
            </DialogDescription>
          </DialogHeader>
          <p className="text-sm text-muted-foreground">
            Are you sure you want to revoke{' '}
            <span className="font-medium text-foreground">
              {tokens.find((t) => t.id === confirmRevoke)?.name ?? 'this token'}
            </span>
            ?
          </p>
          <DialogFooter>
            <Button variant="outline" onClick={() => setConfirmRevoke(null)}>
              Cancel
            </Button>
            <Button
              variant="destructive"
              onClick={() => confirmRevoke !== null && handleRevoke(confirmRevoke)}
            >
              Revoke
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
