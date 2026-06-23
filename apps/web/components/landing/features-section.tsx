'use client'

import { useEffect, useRef, useState } from 'react'

const features = [
  {
    number: '01',
    title: 'Faithful capture',
    description:
      'Every method, header, and byte lands in your inbox exactly as the provider sent it. Nothing normalized away before you inspect.',
    visual: 'deploy',
  },
  {
    number: '02',
    title: 'Replay with response',
    description:
      'Send the same event to localhost or staging. See status code, latency, headers, and the body your app returned in one pass.',
    visual: 'ai',
  },
  {
    number: '03',
    title: 'Share read-only links',
    description:
      'Teammates inspect an event without an account. Copy JSON, rebuild cURL, move on.',
    visual: 'collab',
  },
  {
    number: '04',
    title: 'Minutes to value',
    description:
      'Anonymous inbox in one click. Sign in later to keep it past 48 hours. No procurement, no ceremony.',
    visual: 'security',
  },
]

function DeployVisual() {
  return (
    <svg viewBox="0 0 200 160" className="h-full w-full text-primary">
      <rect x="30" y="20" width="140" height="120" rx="4" fill="none" stroke="currentColor" strokeWidth="2" />
      {[0, 1, 2, 3, 4, 5].map((i) => (
        <rect
          key={i}
          x="40"
          y={35 + i * 16}
          width="120"
          height="10"
          rx="2"
          fill="currentColor"
          opacity="0.15"
        >
          <animate attributeName="opacity" values="0.15;0.8;0.15" dur="2s" begin={`${i * 0.15}s`} repeatCount="indefinite" />
          <animate attributeName="width" values="20;120;20" dur="2s" begin={`${i * 0.15}s`} repeatCount="indefinite" />
        </rect>
      ))}
    </svg>
  )
}

function AIVisual() {
  return (
    <svg viewBox="0 0 200 160" className="h-full w-full text-primary">
      <circle cx="100" cy="80" r="12" fill="currentColor">
        <animate attributeName="r" values="12;14;12" dur="2s" repeatCount="indefinite" />
      </circle>
      {[0, 1, 2, 3, 4, 5].map((i) => {
        const angle = i * 60 * (Math.PI / 180)
        const radius = 50
        return (
          <g key={i}>
            <line
              x1="100"
              y1="80"
              x2={100 + Math.cos(angle) * radius}
              y2={80 + Math.sin(angle) * radius}
              stroke="currentColor"
              strokeWidth="1"
              opacity="0.3"
            >
              <animate attributeName="opacity" values="0.3;0.8;0.3" dur="2s" begin={`${i * 0.3}s`} repeatCount="indefinite" />
            </line>
            <circle
              cx={100 + Math.cos(angle) * radius}
              cy={80 + Math.sin(angle) * radius}
              r="6"
              fill="none"
              stroke="currentColor"
              strokeWidth="2"
            />
          </g>
        )
      })}
    </svg>
  )
}

function CollabVisual() {
  return (
    <svg viewBox="0 0 200 160" className="h-full w-full text-primary">
      <rect x="30" y="50" width="50" height="60" rx="4" fill="none" stroke="currentColor" strokeWidth="2" />
      <rect x="120" y="50" width="50" height="60" rx="4" fill="none" stroke="currentColor" strokeWidth="2" />
      <line x1="80" y1="80" x2="120" y2="80" stroke="currentColor" strokeWidth="2" strokeDasharray="4 4">
        <animate attributeName="stroke-dashoffset" values="0;-8" dur="0.5s" repeatCount="indefinite" />
      </line>
    </svg>
  )
}

function SecurityVisual() {
  return (
    <svg viewBox="0 0 200 160" className="h-full w-full text-primary">
      <path
        d="M 100 20 L 150 40 L 150 90 Q 150 130 100 145 Q 50 130 50 90 L 50 40 Z"
        fill="none"
        stroke="currentColor"
        strokeWidth="2"
      />
      <rect x="85" y="70" width="30" height="25" rx="3" fill="currentColor" />
      <path
        d="M 90 70 L 90 60 Q 90 50 100 50 Q 110 50 110 60 L 110 70"
        fill="none"
        stroke="currentColor"
        strokeWidth="3"
        strokeLinecap="round"
      />
    </svg>
  )
}

function AnimatedVisual({ type }: { type: string }) {
  switch (type) {
    case 'ai':
      return <AIVisual />
    case 'collab':
      return <CollabVisual />
    case 'security':
      return <SecurityVisual />
    default:
      return <DeployVisual />
  }
}

function FeatureCard({ feature, index }: { feature: (typeof features)[0]; index: number }) {
  const [isVisible, setIsVisible] = useState(false)
  const cardRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    const observer = new IntersectionObserver(
      ([entry]) => {
        if (entry?.isIntersecting) setIsVisible(true)
      },
      { threshold: 0.2 }
    )
    if (cardRef.current) observer.observe(cardRef.current)
    return () => observer.disconnect()
  }, [])

  return (
    <div
      ref={cardRef}
      className={`transition-all duration-700 ${
        isVisible ? 'translate-y-0 opacity-100' : 'translate-y-12 opacity-0'
      }`}
      style={{ transitionDelay: `${index * 100}ms` }}
    >
      <div className="flex flex-col gap-8 border-b border-border py-12 lg:flex-row lg:gap-16 lg:py-20">
        <div className="shrink-0">
          <span className="font-ui text-sm text-primary">{feature.number}</span>
        </div>
        <div className="grid flex-1 items-center gap-8 lg:grid-cols-2">
          <div>
            <h3 className="font-display mb-4 text-3xl transition-transform duration-500 group-hover:translate-x-2 lg:text-4xl">
              {feature.title}
            </h3>
            <p className="text-lg leading-relaxed text-muted-foreground">{feature.description}</p>
          </div>
          <div className="flex justify-center lg:justify-end">
            <div className="h-40 w-48">
              <AnimatedVisual type={feature.visual} />
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}

export function FeaturesSection() {
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

  return (
    <section id="features" ref={sectionRef} className="relative scroll-mt-24 py-24 lg:py-32">
      <div className="mx-auto max-w-[1400px] px-6 lg:px-12">
        <div className="mb-16 lg:mb-24">
          <span className="font-ui mb-6 inline-flex items-center gap-3 text-sm text-muted-foreground">
            <span className="h-px w-8 bg-primary/40" />
            Capabilities
          </span>
          <h2
            className={`font-display text-4xl tracking-tight transition-all duration-700 lg:text-6xl ${
              isVisible ? 'translate-y-0 opacity-100' : 'translate-y-4 opacity-0'
            }`}
          >
            Payload first.
            <br />
            <span className="text-muted-foreground">Everything else follows.</span>
          </h2>
        </div>
        <div>
          {features.map((feature, index) => (
            <FeatureCard key={feature.number} feature={feature} index={index} />
          ))}
        </div>
      </div>
    </section>
  )
}