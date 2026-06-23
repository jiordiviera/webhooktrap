import type { ComponentType, SVGProps } from 'react'
import { GithubLight } from '@workspace/ui/components/svgs/githubLight'
import { ClerkIconLight } from '@workspace/ui/components/ui/svgs/clerkIconLight'
import { Google } from '@workspace/ui/components/ui/svgs/google'
import { Linear } from '@workspace/ui/components/ui/svgs/linear'
import { Spotify } from '@workspace/ui/components/ui/svgs/spotify'
import { Stripe } from '@workspace/ui/components/ui/svgs/stripe'
import { Twilio } from '@workspace/ui/components/ui/svgs/twilio'
import { Vercel } from '@workspace/ui/components/ui/svgs/vercel'

type Provider = {
  name: string
  Icon: ComponentType<SVGProps<SVGSVGElement>>
  iconClassName?: string
}

const PROVIDERS: Provider[] = [
  { name: 'Stripe', Icon: Stripe, iconClassName: 'size-7' },
  { name: 'GitHub', Icon: GithubLight, iconClassName: 'size-6' },
  { name: 'Twilio', Icon: Twilio, iconClassName: 'size-6' },
  { name: 'Linear', Icon: Linear, iconClassName: 'size-6' },
  { name: 'Vercel', Icon: Vercel, iconClassName: 'size-5 fill-foreground' },
  { name: 'Clerk', Icon: ClerkIconLight, iconClassName: 'size-6' },
  { name: 'Google', Icon: Google, iconClassName: 'size-6' },
  { name: 'Spotify', Icon: Spotify, iconClassName: 'size-6' },
]

function ProviderLogo({ name, Icon, iconClassName }: Provider) {
  return (
    <div className="flex shrink-0 items-center gap-3 px-1">
      <Icon className={iconClassName} aria-hidden />
      <span className="font-ui text-sm tracking-wide text-muted-foreground">{name}</span>
    </div>
  )
}

export function ProviderTicker() {
  const items = [...PROVIDERS, ...PROVIDERS]

  return (
    <section aria-label="Webhook providers" className="relative border-y border-border bg-secondary/40 py-5">
      <div className="pointer-events-none absolute inset-y-0 left-0 z-10 w-20 bg-linear-to-r from-secondary/90 to-transparent" />
      <div className="pointer-events-none absolute inset-y-0 right-0 z-10 w-20 bg-linear-to-l from-secondary/90 to-transparent" />

      <div className="landing-ticker flex w-max items-center gap-14 px-8 motion-reduce:transform-none">
        {items.map((provider, index) => (
          <ProviderLogo
            key={`${provider.name}-${index}`}
            name={provider.name}
            Icon={provider.Icon}
            iconClassName={provider.iconClassName}
          />
        ))}
      </div>
    </section>
  )
}