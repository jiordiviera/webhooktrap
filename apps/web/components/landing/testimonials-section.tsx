'use client'

import { useEffect, useState } from 'react'

const workflows = [
  {
    quote:
      'Stripe sent a checkout event I could not reproduce locally. Hookscope captured it, I replayed to localhost, and saw the 422 my handler returned.',
    context: 'Payment integration',
    metric: 'Found the bug in one replay',
  },
  {
    quote:
      'GitHub App webhooks were hitting staging with the wrong secret. I compared the raw signature header against what my server expected.',
    context: 'GitHub App setup',
    metric: 'Headers preserved as received',
  },
  {
    quote:
      'I shared a read-only inbox link with a teammate. They inspected the payload without creating an account or touching production.',
    context: 'Team debugging',
    metric: 'No account required to inspect',
  },
  {
    quote:
      'Anonymous inbox for a quick Shopify test, then signed in to keep it when the integration took longer than a day.',
    context: 'Commerce webhook',
    metric: '48h anonymous, then saved',
  },
]

export function TestimonialsSection() {
  const [activeIndex, setActiveIndex] = useState(0)
  const [isAnimating, setIsAnimating] = useState(false)

  useEffect(() => {
    const interval = setInterval(() => {
      setIsAnimating(true)
      window.setTimeout(() => {
        setActiveIndex((prev) => (prev + 1) % workflows.length)
        setIsAnimating(false)
      }, 300)
    }, 5000)
    return () => clearInterval(interval)
  }, [])

  const active = workflows[activeIndex] ?? workflows[0]!

  return (
    <section className="relative border-t border-border py-32 lg:py-40">
      <div className="mx-auto max-w-7xl px-6 lg:px-12">
        <div className="mb-16 flex items-center gap-4">
          <span className="font-ui text-xs tracking-widest text-muted-foreground uppercase">
            Developer workflows
          </span>
          <div className="h-px flex-1 bg-border" />
          <span className="font-ui text-xs text-muted-foreground">
            {String(activeIndex + 1).padStart(2, '0')} / {String(workflows.length).padStart(2, '0')}
          </span>
        </div>

        <div className="grid gap-12 lg:grid-cols-12 lg:gap-20">
          <div className="lg:col-span-8">
            <blockquote
              className={`transition-all duration-300 ${
                isAnimating ? 'translate-y-4 opacity-0' : 'translate-y-0 opacity-100'
              }`}
            >
              <p className="font-heading text-4xl leading-[1.1] tracking-tight text-foreground md:text-5xl lg:text-6xl">
                &ldquo;{active.quote}&rdquo;
              </p>
            </blockquote>
            <div
              className={`mt-10 transition-all duration-300 ${
                isAnimating ? 'opacity-0' : 'opacity-100'
              }`}
            >
              <p className="font-ui text-sm text-primary">{active.context}</p>
              <p className="mt-2 text-muted-foreground">{active.metric}</p>
            </div>
          </div>

          <div className="flex flex-col justify-between gap-8 lg:col-span-4">
            <div className="space-y-3">
              {workflows.map((workflow, index) => (
                <button
                  key={workflow.context}
                  type="button"
                  onClick={() => setActiveIndex(index)}
                  className={`block w-full rounded-lg px-3 py-2 text-left transition-colors ${
                    activeIndex === index
                      ? 'bg-accent text-foreground'
                      : 'text-muted-foreground hover:bg-muted/50 hover:text-foreground'
                  }`}
                >
                  <span className="font-ui text-sm">{workflow.context}</span>
                </button>
              ))}
            </div>
          </div>
        </div>

        <div className="mt-20 overflow-hidden">
          <div className="marquee flex items-center gap-16">
            {[...Array(2)].map((_, i) => (
              <div key={i} className="flex shrink-0 items-center gap-16">
                {['Stripe', 'GitHub', 'Shopify', 'Twilio', 'Linear', 'Vercel', 'curl'].map((name) => (
                  <span
                    key={`${name}-${i}`}
                    className="font-heading text-xl whitespace-nowrap text-foreground/25 transition-colors duration-300 hover:text-primary md:text-2xl"
                  >
                    {name}
                  </span>
                ))}
              </div>
            ))}
          </div>
        </div>
      </div>
    </section>
  )
}