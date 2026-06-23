'use client'

import Image from 'next/image'
import Link from 'next/link'
import { ArrowUpRight } from 'lucide-react'
import { DeveloperCredit } from '@/app/components/developer-credit'
import { GithubDark } from '@workspace/ui/components/svgs/githubDark'

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
                <Image src="/logo.png" alt="Hookscope" width={132} height={36} className="h-auto w-[8.25rem]" />
              </Link>
              <p className="mb-8 max-w-xs leading-relaxed text-muted-foreground">
                Developer-first webhook debugging. Receive, inspect, replay, see the response.
              </p>
              <a
                href="https://github.com/jiordiviera"
                target="_blank"
                rel="noopener noreferrer"
                aria-label="jiordiviera on GitHub"
                className="inline-flex items-center gap-2 text-sm text-muted-foreground transition-colors hover:text-primary"
              >
                <GithubDark className="size-4" />
                GitHub
                <ArrowUpRight className="size-3" />
              </a>
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
          <p className="text-sm text-muted-foreground">Hookscope. Inspect. Replay. Respond.</p>
          <DeveloperCredit />
        </div>
      </div>
    </footer>
  )
}