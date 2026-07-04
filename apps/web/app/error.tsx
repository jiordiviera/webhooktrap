'use client'

import { useEffect } from 'react'
import Link from 'next/link'
import { Button } from '@workspace/ui/components/button'

export default function Error({
  error,
  reset,
}: {
  error: Error & { digest?: string }
  reset: () => void
}): React.ReactElement {
  useEffect(() => {
    console.error(error)
  }, [error])

  return (
    <div className="flex flex-col items-center justify-center min-h-screen px-4 py-20 text-center">
      <div className="max-w-sm space-y-8">
        <div className="space-y-3">
          <div className="text-5xl font-semibold text-destructive">Error</div>
          <h1 className="text-xl font-semibold text-foreground">Something went wrong</h1>
          <p className="text-sm text-muted-foreground leading-relaxed">
            An unexpected error occurred. Try refreshing the page or return to your inboxes.
          </p>
        </div>

        <div className="flex flex-col gap-2">
          <Button className="w-full h-9" onClick={() => reset()}>
            Try again
          </Button>
          <Link href="/inboxes" className="w-full">
            <Button variant="outline" className="w-full h-9">
              Go to inboxes
            </Button>
          </Link>
        </div>

        <div className="pt-4 border-t border-border">
          <p className="text-xs text-muted-foreground">
            Need help?{' '}
            <a
              href="https://docs.webhooktrap.dev"
              target="_blank"
              rel="noopener noreferrer"
              className="text-primary font-medium hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring rounded px-1"
            >
              Read the docs
            </a>
          </p>
        </div>
      </div>
    </div>
  )
}
