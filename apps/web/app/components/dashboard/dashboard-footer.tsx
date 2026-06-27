import Link from 'next/link'

export function DashboardFooter() {
  return (
    <footer className="border-t border-border px-4 py-4 sm:px-6">
      <div className="flex flex-col items-center justify-between gap-2 sm:flex-row">
        <p className="text-[0.6875rem] tracking-wide text-muted-foreground">
          &copy; {new Date().getFullYear()}{' '}
          <Link href="/" className="font-medium text-foreground hover:text-foreground/80">
            Hookscope
          </Link>
          . All rights reserved.
        </p>

        <div className="flex items-center gap-4">
          <Link
            href="https://hookscope.dev"
            className="text-[0.6875rem] tracking-wide text-muted-foreground hover:text-foreground"
          >
            hookscope.dev
          </Link>
          <span className="text-[0.625rem] text-muted-foreground/40">&middot;</span>
          <span className="text-[0.6875rem] tracking-wide text-muted-foreground">
            Webhook debugging, simplified.
          </span>
        </div>
      </div>
    </footer>
  )
}
