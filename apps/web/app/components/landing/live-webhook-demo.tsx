'use client'

import { useEffect, useState } from 'react'
import { cn } from '@workspace/ui/lib/utils'

type DemoPhase = 'listen' | 'incoming' | 'captured' | 'replay' | 'done'

const EVENTS = [
  { method: 'POST', label: 'payment_intent.succeeded', time: '6 min ago' },
  { method: 'GET', label: 'challenge?abc123', time: '14 min ago' },
] as const

const JSON_LINES = [
  '{',
  '  "type": "checkout.session.completed",',
  '  "data": {',
  '    "object": {',
  '      "id": "cs_live_a1B2c3",',
  '      "amount_total": 4900,',
  '      "currency": "eur"',
  '    }',
  '  }',
  '}',
] as const

const PHASE_LABELS: Record<DemoPhase, string> = {
  listen: 'Inbox listening',
  incoming: 'Webhook incoming',
  captured: 'Payload captured',
  replay: 'Replaying to localhost',
  done: 'Response received',
}

const PHASE_DURATION: Record<DemoPhase, number> = {
  listen: 2200,
  incoming: 2000,
  captured: 2800,
  replay: 2400,
  done: 1800,
}

const PHASE_ORDER: DemoPhase[] = ['listen', 'incoming', 'captured', 'replay', 'done']

type LiveWebhookDemoProps = {
  className?: string
  large?: boolean
}

export function LiveWebhookDemo({ className, large }: LiveWebhookDemoProps) {
  const [phase, setPhase] = useState<DemoPhase>('listen')
  const [visibleLines, setVisibleLines] = useState(0)
  const [latency, setLatency] = useState(0)
  const [packetKey, setPacketKey] = useState(0)

  useEffect(() => {
    const duration = PHASE_DURATION[phase]
    const timer = window.setTimeout(() => {
      const index = PHASE_ORDER.indexOf(phase)
      const next = PHASE_ORDER[(index + 1) % PHASE_ORDER.length] ?? 'listen'
      setPhase(next)
      if (next === 'incoming') setPacketKey((k) => k + 1)
      if (next === 'captured') setVisibleLines(0)
      if (next === 'replay') setLatency(0)
    }, duration)

    return () => window.clearTimeout(timer)
  }, [phase])

  useEffect(() => {
    if (phase !== 'captured') return

    if (visibleLines >= JSON_LINES.length) return

    const timer = window.setTimeout(() => {
      setVisibleLines((n) => n + 1)
    }, 180)

    return () => window.clearTimeout(timer)
  }, [phase, visibleLines])

  useEffect(() => {
    if (phase !== 'replay' && phase !== 'done') return
    if (latency >= 142) return

    const timer = window.setTimeout(() => {
      setLatency((n) => Math.min(n + 14, 142))
    }, 40)

    return () => window.clearTimeout(timer)
  }, [phase, latency])

  const showActiveEvent = phase === 'captured' || phase === 'replay' || phase === 'done'
  const showReplay = phase === 'replay' || phase === 'done'
  const replaySuccess = phase === 'done'

  return (
    <div className={cn('relative', className)}>
      <div
        className={cn(
          'landing-demo-glow relative overflow-hidden rounded-2xl border border-border bg-card',
          large ? 'shadow-[0_32px_80px_oklch(0.35_0.04_48/0.12)]' : 'shadow-[0_24px_60px_oklch(0.35_0.04_48/0.08)]',
          'transition-transform duration-700 ease-[cubic-bezier(0.16,1,0.3,1)] hover:rotate-0',
          large ? '' : 'rotate-[0.4deg] max-md:rotate-0'
        )}
      >
        <div className="flex items-center gap-2 border-b border-border bg-secondary/60 px-4 py-3.5">
          <span
            className={cn(
              'size-2 rounded-full transition-colors duration-500',
              phase === 'listen' ? 'bg-muted-foreground/40' : 'bg-signal'
            )}
          />
          <span className="size-2 rounded-full bg-border" />
          <span className="size-2 rounded-full bg-border" />
          <span className="font-ui ml-2 text-[0.8125rem] text-muted-foreground">
            Stripe Integration
          </span>
          <span
            className={cn(
              'font-ui ml-auto rounded-full px-2 py-1 text-[0.6875rem] tracking-widest uppercase transition-colors duration-500',
              phase === 'listen'
                ? 'bg-muted text-muted-foreground'
                : 'bg-signal-soft text-signal'
            )}
          >
            {phase === 'listen' ? 'Idle' : 'Live'}
          </span>
        </div>

        <div className="relative border-b border-border bg-muted/30 px-4 py-2.5">
          <div className="font-ui flex items-center justify-between text-[0.6875rem]">
            <span className="tracking-widest text-muted-foreground uppercase">
              {PHASE_LABELS[phase]}
            </span>
            <span className="text-primary transition-opacity duration-300">
              {phase === 'listen' ? '/i/xK9m2pQ7nR4a' : 'POST /i/xK9m2pQ7nR4a'}
            </span>
          </div>

          {phase === 'incoming' && (
            <svg
              key={packetKey}
              className="pointer-events-none absolute inset-0 size-full overflow-visible"
              viewBox="0 0 340 48"
              aria-hidden
            >
              <circle
                r="5"
                fill="oklch(0.55 0.12 145)"
                className="landing-packet"
              />
            </svg>
          )}
        </div>

        <div className={cn('grid', large ? 'min-h-80 md:grid-cols-[0.38fr_0.62fr]' : 'min-h-72 md:grid-cols-[0.42fr_0.58fr]')}>
          <div className="border-border bg-muted/40 p-4 md:border-r">
            <p className="font-ui mb-3 text-[0.6875rem] tracking-widest text-muted-foreground uppercase">
              Events
            </p>
            <ul className="flex flex-col gap-1.5">
              {showActiveEvent && (
                <li className="grid animate-in grid-cols-[auto_1fr] grid-rows-[auto_auto] gap-x-2 gap-y-0.5 rounded-lg bg-accent px-2.5 py-2 duration-500 fade-in slide-in-from-left-3">
                  <span className="font-ui row-span-2 self-center text-[0.625rem] font-semibold tracking-wider text-primary">
                    POST
                  </span>
                  <span className="font-ui truncate text-xs text-foreground">
                    checkout.session.completed
                  </span>
                  <span className="font-ui text-[0.6875rem] text-signal">just now</span>
                </li>
              )}
              {EVENTS.map((event, i) => (
                <li
                  key={event.label}
                  className="grid grid-cols-[auto_1fr] grid-rows-[auto_auto] gap-x-2 gap-y-0.5 rounded-lg px-2.5 py-2 opacity-60"
                  style={{ transitionDelay: `${i * 80}ms` }}
                >
                  <span className="font-ui row-span-2 self-center text-[0.625rem] font-semibold tracking-wider text-primary">
                    {event.method}
                  </span>
                  <span className="font-ui truncate text-xs text-foreground">{event.label}</span>
                  <span className="font-ui text-[0.6875rem] text-muted-foreground">{event.time}</span>
                </li>
              ))}
            </ul>
          </div>

          <div className="flex flex-col gap-3 p-4">
            <div className="flex items-center gap-2">
              <span className="font-ui text-[0.625rem] font-semibold tracking-wider text-primary">
                POST
              </span>
              <span className="font-ui text-xs text-muted-foreground">/i/xK9m2pQ7nR4a</span>
            </div>

            <div className="flex gap-4 border-b border-border pb-2">
              <span
                className={cn(
                  'font-ui text-xs transition-colors duration-300',
                  phase === 'captured' || showReplay ? 'font-medium text-foreground' : 'text-muted-foreground'
                )}
              >
                Body
              </span>
              <span className="font-ui text-xs text-muted-foreground">Headers</span>
              <span
                className={cn(
                  'font-ui text-xs transition-colors duration-300',
                  showReplay ? 'font-medium text-foreground' : 'text-muted-foreground'
                )}
              >
                Replay
              </span>
            </div>

            <pre className="font-ui min-h-36 flex-1 overflow-hidden rounded-lg bg-muted/60 p-3 text-[0.6875rem] leading-relaxed text-muted-foreground">
              <code>
                {phase === 'listen' || phase === 'incoming' ? (
                  <span className="text-muted-foreground/50">Waiting for payload…</span>
                ) : (
                  JSON_LINES.slice(0, visibleLines).map((line, i) => (
                    <span
                      key={line}
                      className="block animate-in duration-300 fade-in slide-in-from-bottom-1"
                      style={{ animationDelay: `${i * 30}ms` }}
                    >
                      {line}
                    </span>
                  ))
                )}
              </code>
            </pre>

            <div
              className={cn(
                'grid transition-[grid-template-rows,opacity] duration-500 ease-[cubic-bezier(0.16,1,0.3,1)]',
                showReplay ? 'grid-rows-[1fr] opacity-100' : 'grid-rows-[0fr] opacity-0'
              )}
            >
              <div className="overflow-hidden border-t border-border pt-2">
                <div className="grid grid-cols-[1fr_auto] gap-x-3 gap-y-1">
                  <span className="font-ui col-span-2 text-[0.6875rem] text-muted-foreground">
                    Replay destination
                  </span>
                  <span className="font-ui text-xs text-foreground">
                    localhost:3000/webhooks/stripe
                  </span>
                  <span
                    className={cn(
                      'font-ui text-xs font-medium',
                      replaySuccess ? 'text-signal landing-count-up' : 'text-primary landing-replay-active'
                    )}
                  >
                    {replaySuccess ? `200 OK · ${latency}ms` : `Sending… ${latency}ms`}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div className="font-ui mt-4 flex flex-wrap gap-2">
        {PHASE_ORDER.map((step) => (
          <span
            key={step}
            className={cn(
              'rounded-full border px-2.5 py-1 text-[0.6875rem] tracking-wide uppercase transition-all duration-500',
              phase === step
                ? 'border-primary/30 bg-primary/10 text-primary'
                : 'border-border text-muted-foreground/60'
            )}
          >
            {step}
          </span>
        ))}
      </div>
    </div>
  )
}