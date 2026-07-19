import type { Metadata } from 'next'
import { IconArrowUpRight, IconCoffee } from '@tabler/icons-react'
import { Button } from '@workspace/ui/components/button'
import { Navigation } from '@/components/landing/navigation'
import { FooterSection } from '@/components/landing/footer-section'
import { productName } from '@/lib/config'

const SPONSOR_URL = 'https://support.jiordiviera.me/prd_vdzyfncp'

export const metadata: Metadata = {
  title: 'Sponsor',
  description: `Support the development of ${productName}.`,
}

export default function SponsorPage() {
  return (
    <main className="relative">
      <Navigation />
      <div className="mx-auto min-h-screen max-w-3xl px-6 pt-40 pb-24 lg:px-12">
        <span className="mb-6 inline-flex items-center gap-3 text-sm text-muted-foreground">
          <span className="h-px w-8 bg-primary/40" />
          Sponsor
        </span>
        <h1 className="font-heading mb-6 text-4xl tracking-tight lg:text-6xl">
          Keep {productName} running.
        </h1>
        <p className="mb-10 max-w-xl text-base leading-relaxed text-muted-foreground sm:text-lg">
          {productName} is free to use and built in the open. If it saves you time debugging
          webhooks, a coffee goes a long way toward keeping it maintained and hosted.
        </p>
        <Button size="lg" className="rounded-full" asChild>
          <a href={SPONSOR_URL} target="_blank" rel="noopener noreferrer">
            <IconCoffee className="mr-2 size-4" aria-hidden />
            Buy me a coffee
            <IconArrowUpRight className="ml-2 size-4" aria-hidden />
          </a>
        </Button>
      </div>
      <FooterSection />
    </main>
  )
}
