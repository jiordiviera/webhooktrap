'use client'

import Link from 'next/link'
import { useEffect, useState } from 'react'
import { IconArrowRight } from '@tabler/icons-react'
import { Button } from '@workspace/ui/components/button'
import { CreateInboxCta } from '@/app/components/create-inbox-cta'
import { LiveWebhookDemo } from '@/app/components/landing/live-webhook-demo'
import { productName } from '@/lib/config'

const words = ['receive', 'inspect', 'replay', 'respond']

const stats = [
  { value: '<15 min', label: 'to first Stripe replay', company: 'MVP goal' },
  { value: '100%', label: 'payload fidelity', company: 'bytes + headers' },
  { value: '1 URL', label: 'any HTTP provider', company: 'no vendor lock-in' },
  { value: '0', label: 'tunnel setup', company: 'before first event' },
]

export function HeroSection() {
  const [isVisible, setIsVisible] = useState(false)
  const [wordIndex, setWordIndex] = useState(0)

  useEffect(() => {
    setIsVisible(true)
  }, [])

  useEffect(() => {
    const interval = setInterval(() => {
      setWordIndex((prev) => (prev + 1) % words.length)
    }, 2500)
    return () => clearInterval(interval)
  }, [])

  return (
    <section className="relative flex min-h-screen flex-col justify-center overflow-hidden">
      <div className="pointer-events-none absolute inset-0 overflow-hidden opacity-30">
        {[...Array(8)].map((_, i) => (
          <div
            key={`h-${i}`}
            className="absolute right-0 left-0 h-px bg-foreground/10"
            style={{ top: `${12.5 * (i + 1)}%` }}
          />
        ))}
        {[...Array(12)].map((_, i) => (
          <div
            key={`v-${i}`}
            className="absolute top-0 bottom-0 w-px bg-foreground/10"
            style={{ left: `${8.33 * (i + 1)}%` }}
          />
        ))}
      </div>

      <div className="relative z-10 mx-auto grid max-w-[1400px] items-center gap-12 px-6 py-32 lg:grid-cols-2 lg:gap-16 lg:px-12 lg:py-40">
        <div>
          <div
            className={`mb-8 transition-all duration-700 ${
              isVisible ? 'translate-y-0 opacity-100' : 'translate-y-4 opacity-0'
            }`}
          >
            <span className="font-ui inline-flex items-center gap-3 text-sm text-muted-foreground">
              <span className="h-px w-8 bg-primary/40" />
              Webhook debugger
            </span>
          </div>

          <div className="mb-10">
            <h1
              className={`font-heading text-[clamp(2.75rem,9vw,6.5rem)] leading-[0.95] tracking-tight transition-all duration-1000 ${
                isVisible ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0'
              }`}
            >
              <span className="block">Catch the hook.</span>
              <span className="block">
                <span className="text-primary">Then </span>
                <span className="relative inline-block">
                  <span key={wordIndex} className="inline-flex">
                    {(words[wordIndex] ?? 'receive').split('').map((char, i) => (
                      <span
                        key={`${wordIndex}-${i}`}
                        className="animate-char-in inline-block"
                        style={{ animationDelay: `${i * 50}ms` }}
                      >
                        {char}
                      </span>
                    ))}
                  </span>
                  <span className="absolute right-0 -bottom-2 left-0 h-3 bg-primary/10" />
                </span>
                <span className="text-primary">.</span>
              </span>
            </h1>
          </div>

          <p
            className={`mb-8 max-w-xl text-xl leading-relaxed text-muted-foreground transition-all duration-700 delay-200 lg:text-2xl ${
              isVisible ? 'translate-y-0 opacity-100' : 'translate-y-4 opacity-0'
            }`}
          >
            {productName} shows every webhook as it lands, then fires it back to your local server.
            No tunnel maze. No dashboard bloat.
          </p>

          <div
            className={`transition-all duration-700 delay-300 ${
              isVisible ? 'translate-y-0 opacity-100' : 'translate-y-4 opacity-0'
            }`}
          >
            <CreateInboxCta />
            <div className="mt-4 flex flex-wrap items-center gap-4">
              <Button variant="outline" className="rounded-full" asChild>
                <Link href="#how-it-works">
                  See how it works
                  <IconArrowRight className="ml-2 size-4" />
                </Link>
              </Button>
            </div>
          </div>
        </div>

        <div
          className={`transition-all duration-1000 delay-200 ${
            isVisible ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0'
          }`}
        >
          <LiveWebhookDemo large />
        </div>
      </div>

      <div
        className={`absolute right-0 bottom-20 left-0 transition-all duration-700 delay-500 ${
          isVisible ? 'opacity-100' : 'opacity-0'
        }`}
      >
        <div className="marquee flex gap-16 whitespace-nowrap">
          {[...Array(2)].map((_, i) => (
            <div key={i} className="flex gap-16">
              {stats.map((stat) => (
                <div key={`${stat.company}-${i}`} className="flex items-baseline gap-4">
                  <span className="font-heading text-3xl text-foreground lg:text-4xl">
                    {stat.value}
                  </span>
                  <span className="text-sm text-muted-foreground">
                    {stat.label}
                    <span className="font-ui mt-1 block text-xs tracking-wide uppercase">
                      {stat.company}
                    </span>
                  </span>
                </div>
              ))}
            </div>
          ))}
        </div>
      </div>
    </section>
  )
}