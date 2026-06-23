'use client'

import { useEffect, useRef, useState } from 'react'
import { Eye, FileCheck, Lock, Shield } from 'lucide-react'

const securityFeatures = [
  {
    icon: Shield,
    title: 'Signature visibility',
    description: 'Inspect provider signatures alongside the raw payload before you replay to your handler.',
  },
  {
    icon: Lock,
    title: 'TLS in transit',
    description: 'Ingest URLs are served over HTTPS. Payloads stay encrypted between provider and inbox.',
  },
  {
    icon: Eye,
    title: 'Read-only shares',
    description: 'Send a link to a teammate without granting write access or account creation.',
  },
  {
    icon: FileCheck,
    title: 'Account persistence',
    description: 'Sign in to keep inboxes past the 48-hour anonymous window. Delete when you are done.',
  },
]

const tags = ['HTTPS ingest', 'Auth-gated dashboard', 'Read-only links', '48h anonymous TTL']

export function SecuritySection() {
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
      id="security"
      ref={sectionRef}
      className="relative scroll-mt-24 overflow-hidden bg-muted/30 py-24 lg:py-32"
    >
      <div className="mx-auto max-w-[1400px] px-6 lg:px-12">
        <div className="grid gap-16 lg:grid-cols-2 lg:gap-24">
          <div
            className={`transition-all duration-700 ${
              isVisible ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0'
            }`}
          >
            <span className="font-ui mb-6 inline-flex items-center gap-3 text-sm text-muted-foreground">
              <span className="h-px w-8 bg-primary/40" />
              Trust
            </span>
            <h2 className="font-display mb-8 text-4xl tracking-tight lg:text-6xl">
              Debug safely.
              <br />
              Share deliberately.
            </h2>
            <p className="mb-12 text-xl leading-relaxed text-muted-foreground">
              Hookscope is a debugger, not production webhook infrastructure. Design around
              inspection, replay, and controlled sharing.
            </p>
            <div className="flex flex-wrap gap-3">
              {tags.map((tag, index) => (
                <span
                  key={tag}
                  className={`font-ui border border-border px-4 py-2 text-sm transition-all duration-500 ${
                    isVisible ? 'translate-y-0 opacity-100' : 'translate-y-4 opacity-0'
                  }`}
                  style={{ transitionDelay: `${index * 50 + 200}ms` }}
                >
                  {tag}
                </span>
              ))}
            </div>
          </div>

          <div className="grid gap-4 sm:grid-cols-2">
            {securityFeatures.map((feature, index) => {
              const Icon = feature.icon
              return (
                <div
                  key={feature.title}
                  className={`border border-border bg-card p-6 transition-all duration-700 ${
                    isVisible ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0'
                  }`}
                  style={{ transitionDelay: `${index * 100}ms` }}
                >
                  <Icon className="mb-4 size-5 text-primary" />
                  <h3 className="mb-2 font-medium text-foreground">{feature.title}</h3>
                  <p className="text-sm leading-relaxed text-muted-foreground">{feature.description}</p>
                </div>
              )
            })}
          </div>
        </div>
      </div>
    </section>
  )
}