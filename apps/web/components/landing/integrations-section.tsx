'use client'

import { useEffect, useRef, useState } from 'react'
import { productName } from '@/lib/config'

const integrations = [
  { name: 'Stripe', category: 'Payments' },
  { name: 'GitHub', category: 'Version control' },
  { name: 'Shopify', category: 'Commerce' },
  { name: 'Twilio', category: 'Messaging' },
  { name: 'Linear', category: 'Issues' },
  { name: 'Vercel', category: 'Deploy hooks' },
  { name: 'Clerk', category: 'Auth events' },
  { name: 'Resend', category: 'Email events' },
  { name: 'Google', category: 'Cloud pub/sub' },
  { name: 'Spotify', category: 'Webhooks' },
  { name: 'curl', category: 'Any HTTP client' },
  { name: 'Your API', category: 'Custom provider' },
]

export function IntegrationsSection() {
  const [isVisible, setIsVisible] = useState(false)
  const sectionRef = useRef<HTMLElement>(null)

  useEffect(() => {
    const observer = new IntersectionObserver(
      ([entry]) => {
        if (entry?.isIntersecting) setIsVisible(true)
      },
      { threshold: 0.1 }
    )
    if (sectionRef.current) observer.observe(sectionRef.current)
    return () => observer.disconnect()
  }, [])

  return (
    <section id="integrations" ref={sectionRef} className="relative scroll-mt-24 overflow-hidden py-24 lg:py-32">
      <div className="mx-auto max-w-[1400px] px-6 lg:px-12">
        <div
          className={`mx-auto mb-16 max-w-3xl text-center transition-all duration-700 lg:mb-24 ${
            isVisible ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0'
          }`}
        >
          <span className="mb-6 inline-flex items-center gap-3 text-sm text-muted-foreground">
            <span className="h-px w-8 bg-primary/40" />
            Integrations
            <span className="h-px w-8 bg-primary/40" />
          </span>
          <h2 className="font-heading mb-6 text-4xl tracking-tight lg:text-6xl">
            One ingest URL.
            <br />
            Every provider you already use.
          </h2>
          <p className="text-xl text-muted-foreground">
            Point any webhook sender at {productName}. No vendor-specific SDK required.
          </p>
        </div>
      </div>

      <div className="mb-6 w-full">
        <div className="marquee flex gap-6">
          {[...Array(2)].map((_, setIndex) => (
            <div key={setIndex} className="flex shrink-0 gap-6">
              {integrations.map((integration) => (
                <div
                  key={`${integration.name}-${setIndex}`}
                  className="shrink-0 border border-border px-8 py-6 transition-all duration-300 hover:border-primary/30 hover:bg-accent/40"
                >
                  <div className="text-lg font-medium">{integration.name}</div>
                  <div className="text-sm text-muted-foreground">{integration.category}</div>
                </div>
              ))}
            </div>
          ))}
        </div>
      </div>

      <div className="w-full">
        <div className="marquee-reverse flex gap-6">
          {[...Array(2)].map((_, setIndex) => (
            <div key={setIndex} className="flex shrink-0 gap-6">
              {[...integrations].reverse().map((integration) => (
                <div
                  key={`${integration.name}-reverse-${setIndex}`}
                  className="shrink-0 border border-border px-8 py-6 transition-all duration-300 hover:border-primary/30 hover:bg-accent/40"
                >
                  <div className="text-lg font-medium">{integration.name}</div>
                  <div className="text-sm text-muted-foreground">{integration.category}</div>
                </div>
              ))}
            </div>
          ))}
        </div>
      </div>
    </section>
  )
}