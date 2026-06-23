import type { ComponentType, SVGProps } from 'react'
import { cn } from '@workspace/ui/lib/utils'
import { GithubWordmarkLight } from '@workspace/ui/components/svgs/githubWordmarkLight'
import { ClerkWordmarkLight } from '@workspace/ui/components/ui/svgs/clerkWordmarkLight'
import { GoogleWordmark } from '@workspace/ui/components/ui/svgs/googleWordmark'
import { Linear } from '@workspace/ui/components/ui/svgs/linear'
import { ResendWordmarkBlack } from '@workspace/ui/components/ui/svgs/resendWordmarkBlack'
import { ShopifyWordmarkLight } from '@workspace/ui/components/ui/svgs/shopifyWordmarkLight'
import { SpotifyWordmark } from '@workspace/ui/components/ui/svgs/spotifyWordmark'
import { StripeWordmark } from '@workspace/ui/components/ui/svgs/stripeWordmark'
import { Twilio } from '@workspace/ui/components/ui/svgs/twilio'
import { VercelWordmark } from '@workspace/ui/components/ui/svgs/vercelWordmark'
import { X } from '@workspace/ui/components/ui/svgs/x'

type Provider = {
  name: string
  Logo: ComponentType<SVGProps<SVGSVGElement>>
  logoClassName: string
  showLabel?: boolean
}

const PROVIDERS: Provider[] = [
  { name: 'Stripe', Logo: StripeWordmark, logoClassName: 'h-5 w-auto' },
  { name: 'GitHub', Logo: GithubWordmarkLight, logoClassName: 'h-5 w-auto' },
  { name: 'Shopify', Logo: ShopifyWordmarkLight, logoClassName: 'h-5 w-auto' },
  { name: 'Twilio', Logo: Twilio, logoClassName: 'size-7', showLabel: true },
  { name: 'Linear', Logo: Linear, logoClassName: 'size-6', showLabel: true },
  { name: 'Vercel', Logo: VercelWordmark, logoClassName: 'h-4 w-auto' },
  { name: 'Clerk', Logo: ClerkWordmarkLight, logoClassName: 'h-5 w-auto' },
  { name: 'Google', Logo: GoogleWordmark, logoClassName: 'h-5 w-auto' },
  { name: 'Spotify', Logo: SpotifyWordmark, logoClassName: 'h-5 w-auto' },
  { name: 'Resend', Logo: ResendWordmarkBlack, logoClassName: 'h-4 w-auto' },
  { name: 'X', Logo: X, logoClassName: 'size-5', showLabel: true },
]

function ProviderLogo({ Logo, logoClassName }: Pick<Provider, 'Logo' | 'logoClassName'>) {
  return (
    <div className="relative inline-flex items-center justify-center">
      <Logo
        className={cn(logoClassName, 'brightness-0 transition-opacity duration-300 group-hover:opacity-0')}
        aria-hidden
      />
      <div className="pointer-events-none absolute inset-0 flex items-center justify-center">
        <Logo
          className={cn(logoClassName, 'opacity-0 transition-opacity duration-300 group-hover:opacity-100')}
          aria-hidden
        />
      </div>
    </div>
  )
}

function ProviderItem({ name, Logo, logoClassName, showLabel }: Provider) {
  return (
    <div className="group flex shrink-0 cursor-pointer items-center gap-2.5 px-2">
      <ProviderLogo Logo={Logo} logoClassName={logoClassName} />
      {showLabel ? (
        <span className="font-ui text-sm tracking-wide text-foreground/70">{name}</span>
      ) : (
        <span className="sr-only">{name}</span>
      )}
    </div>
  )
}

export function ProviderTicker() {
  const items = [...PROVIDERS, ...PROVIDERS]

  return (
    <section aria-label="Webhook providers" className="relative border-y border-border bg-white py-6">
      <div className="pointer-events-none absolute inset-y-0 left-0 z-10 w-24 bg-linear-to-r from-white to-transparent" />
      <div className="pointer-events-none absolute inset-y-0 right-0 z-10 w-24 bg-linear-to-l from-white to-transparent" />

      <div className="landing-ticker-track overflow-hidden">
        <div className="landing-ticker flex w-max items-center gap-16 px-10 motion-reduce:transform-none">
        {items.map((provider, index) => (
          <ProviderItem key={`${provider.name}-${index}`} {...provider} />
        ))}
        </div>
      </div>
    </section>
  )
}