import Link from 'next/link'
import { productName, productTagline } from '@/lib/config'

export function DashboardFooter() {
  return (
    <footer className="border-t border-border px-4 py-4 sm:px-6">
      <div className="flex flex-col items-center justify-between gap-2 sm:flex-row">
        <p className="text-[0.6875rem] tracking-wide text-muted-foreground">
          &copy; {new Date().getFullYear()}{' '}
          <Link href="/" className="font-medium text-foreground hover:text-foreground/80">
            {productName}
          </Link>
          . All rights reserved.
        </p>

        <div className="flex items-center gap-4">
          <span className="text-[0.6875rem] tracking-wide text-muted-foreground">
            {productTagline}
          </span>
        </div>
      </div>
    </footer>
  )
}
