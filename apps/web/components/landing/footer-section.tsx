'use client'

import Image from 'next/image'
import Link from 'next/link'
import { IconArrowUpRight } from '@tabler/icons-react'
import { DeveloperCredit } from '@/app/components/developer-credit'
import { ThemeToggle } from '@/app/components/theme-toggle'
import { GithubDark } from '@workspace/ui/components/svgs/githubDark'
import { GithubLight } from '@workspace/ui/components/svgs/githubLight'
import { X as XIcon } from '@workspace/ui/components/ui/svgs/x'
import { XDark } from '@workspace/ui/components/ui/svgs/xDark'
import { productName } from '@/lib/config'

const X_URL = 'https://x.com/jiordiviera'
const GITHUB_URL = 'https://github.com/jiordiviera'

const footerLinks = {
  Product: [
    { name: 'Features', href: '#features' },
    { name: 'How it works', href: '#how-it-works' },
    { name: 'Integrations', href: '#integrations' },
    { name: 'Pricing', href: '#pricing' },
  ],
  Developers: [
    { name: 'API flows', href: '#developers' },
    { name: 'Open inbox', href: '#cta' },
    { name: 'Dashboard', href: '/dashboard' },
    { name: 'Inboxes', href: '/inboxes' },
  ],
  Account: [
    { name: 'Sign in', href: '/login' },
    { name: 'Register', href: '/register' },
    { name: 'Security', href: '#security' },
  ],
}

export function FooterSection() {
  return (
    <footer className="relative border-t border-border">
      <div className="relative z-10 mx-auto max-w-[1400px] px-6 lg:px-12">
        <div className="py-16 lg:py-24">
          <div className="grid grid-cols-2 gap-12 md:grid-cols-6 lg:gap-8">
            <div className="col-span-2">
              <Link href="/" className="mb-6 inline-flex items-center">
                <Image src="/logo.png" alt={productName} width={132} height={36} className="h-auto max-w-10" />
              </Link>
              <p className="mb-8 max-w-xs leading-relaxed text-muted-foreground">
                Developer-first webhook debugging. Receive, inspect, replay, see the response.
              </p>
              <div className="flex flex-wrap items-center gap-5">
                <a
                  href={GITHUB_URL}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="inline-flex items-center gap-2 text-sm text-muted-foreground transition-colors hover:text-primary"
                >
                  <GithubLight className="size-4 dark:hidden" aria-hidden />
                  <GithubDark className="hidden size-4 dark:block" aria-hidden />
                  GitHub
                  <IconArrowUpRight className="size-3" aria-hidden />
                </a>
                <a
                  href={X_URL}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="inline-flex items-center gap-2 text-sm text-muted-foreground transition-colors hover:text-primary"
                >
                  <XIcon className="size-4 dark:hidden" aria-hidden />
                  <XDark className="hidden size-4 dark:block" aria-hidden />
                  <IconArrowUpRight className="size-3" aria-hidden />
                </a>
              </div>
            </div>

            {Object.entries(footerLinks).map(([title, links]) => (
              <div key={title}>
                <h3 className="mb-6 text-sm font-medium">{title}</h3>
                <ul className="space-y-4">
                  {links.map((link) => (
                    <li key={link.name}>
                      <Link
                        href={link.href}
                        className="text-sm text-muted-foreground transition-colors hover:text-primary"
                      >
                        {link.name}
                      </Link>
                    </li>
                  ))}
                </ul>
              </div>
            ))}
          </div>
        </div>

        <div className="flex flex-col items-center justify-between gap-4 border-t border-border py-8 md:flex-row">
          <p className="text-sm text-muted-foreground">{productName}. Inspect. Replay. Respond.</p>
          <div className="flex flex-wrap items-center justify-center gap-4">
            <ThemeToggle />
            <DeveloperCredit />
          </div>
        </div>
      </div>
    </footer>
  )
}