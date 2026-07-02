import Image from 'next/image'
import Link from 'next/link'
import type { ReactNode } from 'react'
import { DeveloperCredit } from '@/app/components/developer-credit'
import { productName } from '@/lib/config'

type AuthShellProps = {
  title: string
  description: string
  children: ReactNode
  footer: ReactNode
}

export function AuthShell({ title, description, children, footer }: AuthShellProps) {
  return (
    <div className="grid min-h-svh lg:grid-cols-[minmax(0,1fr)_minmax(0,0.9fr)]">
      <div className="flex flex-col justify-between px-6 py-8 md:px-12 md:py-10">
        <Link href="/" className="inline-flex w-fit">
          <Image
            src="/logo.png"
            alt={productName}
            width={132}
            height={36}
            className="h-auto w-18"
            priority
          />
        </Link>

        <div className="mx-auto w-full max-w-sm py-10">
          <div className="mb-8">
            <h1 className="font-heading mb-2 text-2xl font-semibold tracking-wide">{title}</h1>
            <p className="text-sm leading-relaxed text-muted-foreground">{description}</p>
          </div>
          {children}
          <div className="mt-6 text-center text-sm text-muted-foreground">{footer}</div>
        </div>

        <DeveloperCredit />
      </div>

      <aside className="relative hidden overflow-hidden border-l border-border bg-secondary/50 lg:flex lg:flex-col lg:justify-between lg:px-12 lg:py-10">
        <p className="font-ui text-xs tracking-[0.14em] text-muted-foreground uppercase">
          Webhook debugger
        </p>

        <div className="max-w-md">
          <p className="font-heading text-[clamp(2rem,3.5vw,2.75rem)] leading-tight font-semibold text-foreground">
            Keep your inboxes.
            <span className="block font-medium text-primary">Replay on your terms.</span>
          </p>
          <p className="mt-4 text-sm leading-relaxed text-muted-foreground">
            Sign in to save inboxes past 48 hours, name integrations, and share read-only event
            links with your team.
          </p>
        </div>

        <div className="font-ui grid gap-3 text-xs text-muted-foreground">
          <p>
            <span className="font-medium text-foreground">Anonymous</span> inboxes expire after 48
            hours.
          </p>
          <p>
            <span className="font-medium text-foreground">Signed in</span> inboxes stay until you
            delete them.
          </p>
        </div>
      </aside>
    </div>
  )
}