'use client'

import Link from 'next/link'
import { IconArrowRight, IconCheck } from '@tabler/icons-react'
import { Button } from '@workspace/ui/components/button'

const plans = [
  {
    name: 'Anonymous',
    description: 'Try Hookvane before you sign in',
    price: 'Free',
    features: [
      'Instant ingest URL',
      '48-hour retention',
      'Inspect payloads',
      'Replay to localhost',
      'No credit card',
    ],
    cta: 'Open inbox',
    ctaHref: '#cta',
    popular: false,
  },
  {
    name: 'Saved',
    description: 'For integrations you return to',
    price: 'Free',
    features: [
      'Persistent inboxes',
      'Dashboard workspace',
      'Replay history',
      'Read-only share links',
      'Named integrations',
    ],
    cta: 'Create account',
    ctaHref: '/register',
    popular: true,
  },
]

export function PricingSection() {
  return (
    <section id="pricing" className="relative scroll-mt-24 border-t border-border py-32 lg:py-40">
      <div className="mx-auto max-w-7xl px-6 lg:px-12">
        <div className="mb-20 max-w-3xl">
          <span className="font-ui mb-6 block text-xs tracking-widest text-muted-foreground uppercase">
            Pricing
          </span>
          <h2 className="font-heading mb-6 text-5xl tracking-tight text-foreground md:text-6xl lg:text-7xl">
            Start free.
            <br />
            <span className="text-stroke">Stay when it sticks.</span>
          </h2>
          <p className="max-w-xl text-lg text-muted-foreground">
            Anonymous inboxes for quick tests. Sign in when you need persistence. No procurement
            maze.
          </p>
        </div>

        <div className="grid gap-8 lg:grid-cols-2">
          {plans.map((plan) => (
            <div
              key={plan.name}
              className={`relative flex flex-col border p-8 lg:p-10 ${
                plan.popular ? 'border-primary bg-accent/30' : 'border-border bg-card'
              }`}
            >
              {plan.popular && (
                <span className="font-ui absolute -top-3 left-8 bg-primary px-3 py-1 text-xs text-primary-foreground">
                  Recommended
                </span>
              )}
              <h3 className="font-heading mt-2 text-3xl text-foreground">{plan.name}</h3>
              <p className="mt-2 text-muted-foreground">{plan.description}</p>
              <p className="font-heading mt-8 text-5xl text-foreground lg:text-6xl">{plan.price}</p>
              <ul className="mt-8 flex-1 space-y-4">
                {plan.features.map((feature) => (
                  <li key={feature} className="flex items-start gap-3 text-sm text-foreground/85">
                    <IconCheck className="mt-0.5 size-4 shrink-0 text-primary" />
                    {feature}
                  </li>
                ))}
              </ul>
              <Button className="mt-10 h-12 rounded-full" variant={plan.popular ? 'default' : 'outline'} asChild>
                <Link href={plan.ctaHref}>
                  {plan.cta}
                  <IconArrowRight className="ml-2 size-4" />
                </Link>
              </Button>
            </div>
          ))}
        </div>
      </div>
    </section>
  )
}