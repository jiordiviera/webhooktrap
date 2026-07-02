'use client'

import { useEffect, useRef, useState } from 'react'

const metrics = [
  { value: 'Inspect', label: 'Read every header and byte as received' },
  { value: 'Replay', label: 'Fire the same event to localhost or staging' },
  { value: 'Respond', label: 'See status, latency, and response body' },
]

export function MetricsSection() {
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
    <section
      id="studio"
      ref={sectionRef}
      className="relative border-y border-border bg-secondary/40 py-24 lg:py-32"
    >
      <div className="mx-auto max-w-[1400px] px-6 lg:px-12">
        <div className="mb-16 flex flex-col gap-8 lg:mb-24 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <span className="mb-6 inline-flex items-center gap-3 text-sm text-muted-foreground">
              <span className="h-px w-8 bg-primary/40" />
              Tagline
            </span>
            <h2
              className={`font-heading text-4xl tracking-tight transition-all duration-700 lg:text-6xl ${
                isVisible ? 'translate-y-0 opacity-100' : 'translate-y-4 opacity-0'
              }`}
            >
              Inspect.
              <br />
              Replay.
              <br />
              <span className="text-primary">See the response.</span>
            </h2>
          </div>
          <p className="max-w-sm text-muted-foreground">
            The Stripe CLI for every webhook provider. Not just payload viewing: replay plus
            response visibility.
          </p>
        </div>

        <div className="grid gap-8 md:grid-cols-3">
          {metrics.map((metric, index) => (
            <div
              key={metric.value}
              className={`rounded-2xl border border-border bg-card p-8 transition-all duration-700 ${
                isVisible ? 'translate-y-0 opacity-100' : 'translate-y-6 opacity-0'
              }`}
              style={{ transitionDelay: `${index * 120}ms` }}
            >
              <p className="font-heading mb-3 text-4xl text-primary lg:text-5xl">{metric.value}</p>
              <p className="text-muted-foreground">{metric.label}</p>
            </div>
          ))}
        </div>
      </div>
    </section>
  )
}