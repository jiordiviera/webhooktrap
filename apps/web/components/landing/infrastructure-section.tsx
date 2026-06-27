'use client'

import { useEffect, useRef, useState } from 'react'

const events = [
  { method: 'POST', type: 'checkout.session.completed', source: 'Stripe', time: '2s ago' },
  { method: 'POST', type: 'pull_request.opened', source: 'GitHub', time: '4m ago' },
  { method: 'POST', type: 'orders/create', source: 'Shopify', time: '12m ago' },
  { method: 'GET', type: 'challenge', source: 'Webhook verify', time: '18m ago' },
]

export function InfrastructureSection() {
  const [isVisible, setIsVisible] = useState(false)
  const [activeEvent, setActiveEvent] = useState(0)
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

  useEffect(() => {
    const interval = setInterval(() => {
      setActiveEvent((prev) => (prev + 1) % events.length)
    }, 2200)
    return () => clearInterval(interval)
  }, [])

  return (
    <section ref={sectionRef} className="relative py-24 lg:py-32">
      <div className="mx-auto max-w-[1400px] px-6 lg:px-12">
        <div className="grid items-center gap-16 lg:grid-cols-2 lg:gap-24">
          <div
            className={`transition-all duration-700 ${
              isVisible ? 'translate-x-0 opacity-100' : '-translate-x-8 opacity-0'
            }`}
          >
            <span className="font-ui mb-6 inline-flex items-center gap-3 text-sm text-muted-foreground">
              <span className="h-px w-8 bg-primary/40" />
              Capture pipeline
            </span>
            <h2 className="font-heading mb-8 text-4xl tracking-tight lg:text-6xl">
              Nothing lost
              <br />
              in translation.
            </h2>
            <p className="mb-12 text-xl leading-relaxed text-muted-foreground">
              Headers, query strings, raw body, and HTTP method preserved before you replay.
              Built for the afternoon before your demo, not enterprise procurement.
            </p>
            <div className="grid grid-cols-3 gap-8">
              <div>
                <div className="font-heading mb-2 text-4xl text-primary lg:text-5xl">3</div>
                <div className="text-sm text-muted-foreground">core moves</div>
              </div>
              <div>
                <div className="font-heading mb-2 text-4xl text-primary lg:text-5xl">48h</div>
                <div className="text-sm text-muted-foreground">anonymous retention</div>
              </div>
              <div>
                <div className="font-heading mb-2 text-4xl text-primary lg:text-5xl">∞</div>
                <div className="text-sm text-muted-foreground">providers supported</div>
              </div>
            </div>
          </div>

          <div
            className={`transition-all delay-200 duration-700 ${
              isVisible ? 'translate-x-0 opacity-100' : 'translate-x-8 opacity-0'
            }`}
          >
            <div className="border border-border bg-card">
              <div className="flex items-center justify-between border-b border-border px-6 py-4">
                <span className="font-ui text-sm text-muted-foreground">Inbox events</span>
                <span className="font-ui flex items-center gap-2 text-xs text-signal">
                  <span className="size-2 animate-pulse rounded-full bg-signal" />
                  Listening
                </span>
              </div>
              <ul>
                {events.map((event, index) => (
                  <li
                    key={event.type}
                    className={`border-b border-border px-6 py-4 transition-all duration-500 last:border-b-0 ${
                      activeEvent === index ? 'bg-accent/60' : 'opacity-60'
                    }`}
                  >
                    <div className="flex items-center justify-between gap-4">
                      <div>
                        <span className="font-ui mr-2 text-xs font-semibold text-primary">
                          {event.method}
                        </span>
                        <span className="font-ui text-sm text-foreground">{event.type}</span>
                        <p className="mt-1 text-xs text-muted-foreground">{event.source}</p>
                      </div>
                      <span className="font-ui text-xs text-muted-foreground">{event.time}</span>
                    </div>
                  </li>
                ))}
              </ul>
            </div>

            <div className="mt-8">
              <svg viewBox="0 0 400 80" className="w-full max-w-md text-primary" aria-label="Webhook flow diagram">
                <defs>
                  <marker id="infra-arrow" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="6" markerHeight="6" orient="auto">
                    <path d="M 0 0 L 10 5 L 0 10 z" fill="currentColor" />
                  </marker>
                </defs>
                <g className="transition-all duration-700" style={{ opacity: isVisible ? 1 : 0 }}>
                  <rect x="0" y="20" width="70" height="36" rx="6" fill="none" stroke="currentColor" strokeWidth="1.5" className="transition-all duration-500" style={{ opacity: isVisible ? 0.7 : 0 }} />
                  <text x="35" y="43" textAnchor="middle" className="font-ui" fontSize="9" fill="currentColor">Provider</text>

                  <line x1="72" y1="38" x2="110" y2="38" stroke="currentColor" strokeWidth="1.5" markerEnd="url(#infra-arrow)" className="transition-all duration-500" style={{ opacity: isVisible ? 0.35 : 0 }} />

                  <rect x="115" y="14" width="80" height="48" rx="8" fill="none" stroke="currentColor" strokeWidth="2" className="transition-all duration-500" style={{ opacity: isVisible ? 1 : 0 }}>
                    <animate attributeName="opacity" values="0.6;1;0.6" dur="3s" repeatCount="indefinite" />
                  </rect>
                  <text x="155" y="34" textAnchor="middle" className="font-ui" fontSize="9" fontWeight="600" fill="currentColor">Hookvane</text>
                  <text x="155" y="50" textAnchor="middle" className="font-ui" fontSize="7" fill="currentColor" opacity="0.6">/i/&hellip; inbox</text>

                  <line x1="197" y1="38" x2="235" y2="38" stroke="currentColor" strokeWidth="1.5" markerEnd="url(#infra-arrow)" className="transition-all duration-500" style={{ opacity: isVisible ? 0.35 : 0 }} />

                  <rect x="240" y="20" width="62" height="36" rx="6" fill="none" stroke="currentColor" strokeWidth="1.5" strokeDasharray="4 2" className="transition-all duration-500" style={{ opacity: isVisible ? 0.7 : 0 }} />
                  <text x="271" y="43" textAnchor="middle" className="font-ui" fontSize="9" fill="currentColor">Inspect</text>

                  <line x1="304" y1="38" x2="330" y2="38" stroke="currentColor" strokeWidth="1.5" markerEnd="url(#infra-arrow)" className="transition-all duration-500" style={{ opacity: isVisible ? 0.35 : 0 }} />

                  <rect x="335" y="20" width="58" height="36" rx="6" fill="none" stroke="currentColor" strokeWidth="1.5" className="transition-all duration-500" style={{ opacity: isVisible ? 0.7 : 0 }} />
                  <text x="364" y="43" textAnchor="middle" className="font-ui" fontSize="9" fill="currentColor">Replay</text>
                </g>
              </svg>
            </div>
          </div>
        </div>
      </div>
    </section>
  )
}