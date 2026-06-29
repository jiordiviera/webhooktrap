'use client'

import { IconCopy } from '@tabler/icons-react'
import { Button } from '@workspace/ui/components/button'
import { Skeleton } from '@workspace/ui/components/skeleton'

interface IngestUrlCardProps {
  ingestUrl: string
  inboxLoading: boolean
  copied: boolean
  onCopyUrl: () => void
}

export function IngestUrlCard({ ingestUrl, inboxLoading, copied, onCopyUrl }: IngestUrlCardProps) {
  return (
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
              onClick={onCopyUrl}
              aria-label={copied ? 'URL copied' : 'Copy ingest URL'}
            >
              <IconCopy className="size-3.5" aria-hidden />
              {copied ? 'Copied' : 'Copy URL'}
            </Button>
          </>
        )}
      </div>
    </section>
  )
}
