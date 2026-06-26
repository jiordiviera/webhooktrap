'use client'

import { useState } from 'react'
import Link from 'next/link'
import { Button } from '@workspace/ui/components/button'
import { Loader } from '@workspace/ui/components/loader'
import { API_URL, apiFetch } from '@/lib/api'

type InboxResponse = {
  data: {
    inbox: {
      id: string
      ingestUrl: string
    }
  }
}

export function CreateInboxCta({ showSecondaryLink = true }: { showSecondaryLink?: boolean }) {
  const [status, setStatus] = useState<'idle' | 'loading' | 'ready' | 'error'>('idle')
  const [ingestUrl, setIngestUrl] = useState('')
  const [copied, setCopied] = useState(false)

  async function createInbox() {
    setStatus('loading')
    setCopied(false)

    try {
      const body = await apiFetch<InboxResponse>('/api/v1/inboxes', {
        method: 'POST',
        body: JSON.stringify({ name: 'My inbox' }),
      })
      const url = body.data.inbox.ingestUrl.startsWith('http')
        ? body.data.inbox.ingestUrl
        : `${API_URL}${body.data.inbox.ingestUrl}`
      setIngestUrl(url)
      setStatus('ready')
    } catch {
      setStatus('error')
    }
  }

  async function copyUrl() {
    if (!ingestUrl) return
    await navigator.clipboard.writeText(ingestUrl)
    setCopied(true)
    window.setTimeout(() => setCopied(false), 2000)
  }

  return (
    <div className="flex flex-col gap-5">
      <div className="flex flex-wrap items-center gap-4">
        <Button
          size="lg"
          className="h-11 rounded-full px-6 text-[0.9375rem]"
          onClick={createInbox}
          disabled={status === 'loading'}
        >
          {status === 'loading' ? (
            <>
              <Loader size="sm" tone="inherit" />
              Opening inbox…
            </>
          ) : (
            'Open a free inbox'
          )}
        </Button>

        {showSecondaryLink && (
          <Button variant="link" asChild className="text-muted-foreground">
            <Link href="#flow">See how it works</Link>
          </Button>
        )}
      </div>

      {status === 'ready' && (
        <div
          className="animate-in fade-in slide-in-from-bottom-2 rounded-xl border border-border bg-card p-4 shadow-[0_12px_40px_oklch(0.35_0.04_48/0.08)] duration-500"
          role="status"
        >
          <p className="font-ui mb-2 text-xs tracking-widest text-muted-foreground uppercase">
            Your ingest URL
          </p>
          <div className="flex flex-wrap items-center gap-3">
            <code className="font-ui min-w-48 flex-1 text-[0.8125rem] break-all text-foreground">
              {ingestUrl}
            </code>
            <Button variant="outline" size="sm" className="rounded-full" onClick={copyUrl}>
              {copied ? 'Copied' : 'Copy'}
            </Button>
          </div>
          <p className="mt-2.5 text-sm text-muted-foreground">
            Point Stripe, GitHub, or any provider at this URL. Events appear in your inbox.
          </p>
        </div>
      )}

      {status === 'error' && (
        <p className="font-ui text-sm text-destructive" role="alert">
          Could not reach the API. Start the server on port 3333 and try again.
        </p>
      )}
    </div>
  )
}