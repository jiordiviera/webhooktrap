import Link from 'next/link'
import { Button } from '@workspace/ui/components/button'

export default function NotFound(): React.ReactElement {
  return (
    <div className="flex flex-col items-center justify-center min-h-screen px-4 py-20 text-center">
      <div className="max-w-sm space-y-8">
        <div className="space-y-3">
          <div className="text-5xl font-semibold text-primary">404</div>
          <h1 className="text-xl font-semibold text-foreground">Not found</h1>
          <p className="text-sm text-muted-foreground leading-relaxed">
            This route doesn&apos;t have a webhook inbox or has expired. Check the URL and try again.
          </p>
        </div>

        <div className="flex flex-col gap-2">
          <Link href="/" className="w-full">
            <Button className="w-full h-9">Create an inbox</Button>
          </Link>
          <Link href="/inboxes" className="w-full">
            <Button variant="outline" className="w-full h-9">
              View your inboxes
            </Button>
          </Link>
        </div>

        <div className="pt-4 border-t border-border">
          <p className="text-xs text-muted-foreground">
            Lost? Read the{' '}
            <a
              href="https://docs.webhooktrap.dev"
              target="_blank"
              rel="noopener noreferrer"
              className="text-primary font-medium hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring rounded px-1"
            >
              getting started guide
            </a>
          </p>
        </div>
      </div>
    </div>
  )
}
