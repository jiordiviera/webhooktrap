'use client'

import { useEffect, useRef, useState } from 'react'

const steps = [
  {
    number: 'I',
    title: 'Receive',
    description: 'Drop the ingest URL into Stripe, GitHub, Shopify, or curl. Every byte lands in one inbox.',
    code: `curl -X POST https://hookscope.dev/i/xK9m2pQ7nR4a \\
  -H "Content-Type: application/json" \\
  -d '{"type":"checkout.session.completed"}'`,
  },
  {
    number: 'II',
    title: 'Inspect',
    description: 'Read the payload as it arrived. Copy JSON, rebuild cURL, share a read-only link.',
    code: `GET /i/xK9m2pQ7nR4a/events/evt_8f2a

{
  "method": "POST",
  "headers": { "stripe-signature": "..." },
  "body": { "type": "checkout.session.completed" }
}`,
  },
  {
    number: 'III',
    title: 'Replay',
    description: 'Send the same event to localhost. See status, headers, latency, and response body.',
    code: `POST /replay
  destination: http://localhost:3000/webhooks/stripe

→ 200 OK · 142ms
← { "received": true }`,
  },
]

export function HowItWorksSection() {
  const [activeStep, setActiveStep] = useState(0)
  const [isVisible, setIsVisible] = useState(false)
  const sectionRef = useRef<HTMLDivElement>(null)

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
      setActiveStep((prev) => (prev + 1) % steps.length)
    }, 5000)
    return () => clearInterval(interval)
  }, [])

  return (
    <section
      id="how-it-works"
      ref={sectionRef}
      className="relative scroll-mt-24 overflow-hidden bg-primary py-24 text-primary-foreground lg:py-32"
    >
      <div className="pointer-events-none absolute inset-0 opacity-[0.04]">
        <div
          className="absolute inset-0"
          style={{
            backgroundImage: `repeating-linear-gradient(-45deg, transparent, transparent 40px, currentColor 40px, currentColor 41px)`,
          }}
        />
      </div>

      <div className="relative z-10 mx-auto max-w-[1400px] px-6 lg:px-12">
        <div className="mb-16 lg:mb-24">
          <span className="font-ui mb-6 inline-flex items-center gap-3 text-sm text-primary-foreground/60">
            <span className="h-px w-8 bg-primary-foreground/30" />
            Process
          </span>
          <h2
            className={`font-heading text-4xl tracking-tight transition-all duration-700 lg:text-6xl ${
              isVisible ? 'translate-y-0 opacity-100' : 'translate-y-4 opacity-0'
            }`}
          >
            Three moves.
            <br />
            <span className="text-primary-foreground/55">The whole product.</span>
          </h2>
        </div>

        <div className="grid gap-16 lg:grid-cols-2 lg:gap-24">
          <div className="space-y-0">
            {steps.map((step, index) => (
              <button
                key={step.number}
                type="button"
                onClick={() => setActiveStep(index)}
                className={`w-full border-b border-primary-foreground/15 py-8 text-left transition-all duration-500 ${
                  activeStep === index ? 'opacity-100' : 'opacity-45 hover:opacity-70'
                }`}
              >
                <div className="flex items-start gap-6">
                  <span className="font-heading text-3xl text-primary-foreground/30">{step.number}</span>
                  <div className="flex-1">
                    <h3 className="font-heading mb-3 text-2xl lg:text-3xl">{step.title}</h3>
                    <p className="leading-relaxed text-primary-foreground/70">{step.description}</p>
                    {activeStep === index && (
                      <div className="mt-4 h-px overflow-hidden bg-primary-foreground/20">
                        <div
                          className="h-full w-0 bg-primary-foreground"
                          style={{ animation: 'landing-progress 5s linear forwards' }}
                        />
                      </div>
                    )}
                  </div>
                </div>
              </button>
            ))}
          </div>

          <div className="lg:sticky lg:top-32 lg:self-start">
            <div className="overflow-hidden border border-primary-foreground/15">
              <div className="flex items-center justify-between border-b border-primary-foreground/15 px-6 py-4">
                <div className="flex gap-2">
                  <div className="size-3 rounded-full bg-primary-foreground/20" />
                  <div className="size-3 rounded-full bg-primary-foreground/20" />
                  <div className="size-3 rounded-full bg-primary-foreground/20" />
                </div>
                <span className="font-ui text-xs text-primary-foreground/45">inbox.ts</span>
              </div>
              <div className="min-h-[280px] p-8 font-mono text-sm">
                <pre className="text-primary-foreground/75">
                  {(steps[activeStep] ?? steps[0]!).code.split('\n').map((line, lineIndex) => (
                    <div key={`${activeStep}-${lineIndex}`} className="leading-loose">
                      <span className="inline-block w-8 select-none text-primary-foreground/25">
                        {lineIndex + 1}
                      </span>
                      {line}
                    </div>
                  ))}
                </pre>
              </div>
              <div className="flex items-center gap-3 border-t border-primary-foreground/15 px-6 py-4">
                <span className="size-2 animate-pulse rounded-full bg-signal" />
                <span className="font-ui text-xs text-primary-foreground/45">Live capture</span>
              </div>
            </div>
          </div>
        </div>
      </div>

    </section>
  )
}