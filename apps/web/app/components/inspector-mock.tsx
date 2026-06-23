const EVENTS = [
  { method: 'POST', label: 'checkout.session.completed', active: true, time: 'just now' },
  { method: 'POST', label: 'payment_intent.succeeded', active: false, time: '4 min ago' },
  { method: 'GET', label: 'challenge?abc123', active: false, time: '12 min ago' },
] as const

export function InspectorMock() {
  return (
    <div
      className="relative rotate-[0.6deg] overflow-hidden rounded-2xl border border-border bg-card shadow-[0_24px_60px_oklch(0.35_0.04_48/0.08)] max-md:rotate-0"
      aria-hidden="true"
    >
      <div className="flex items-center gap-2 border-b border-border bg-secondary/60 px-4 py-3.5">
        <span className="size-2 rounded-full bg-primary" />
        <span className="size-2 rounded-full bg-border" />
        <span className="size-2 rounded-full bg-border" />
        <span className="font-ui ml-2 text-[0.8125rem] text-muted-foreground">
          Stripe Integration
        </span>
        <span className="font-ui ml-auto rounded-full bg-signal-soft px-2 py-1 text-[0.6875rem] tracking-widest text-signal uppercase">
          Live
        </span>
      </div>

      <div className="grid min-h-72 md:grid-cols-[0.42fr_0.58fr]">
        <div className="border-border bg-muted/50 p-4 md:border-r">
          <p className="font-ui mb-3 text-[0.6875rem] tracking-widest text-muted-foreground uppercase">
            Events
          </p>
          <ul className="flex flex-col gap-1.5">
            {EVENTS.map((event) => (
              <li
                key={event.label}
                className={`grid grid-cols-[auto_1fr] grid-rows-[auto_auto] gap-x-2 gap-y-0.5 rounded-lg px-2.5 py-2 ${event.active ? 'bg-accent' : ''}`}
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
            <span className="font-ui text-xs font-medium text-foreground">Body</span>
            <span className="font-ui text-xs text-muted-foreground">Headers</span>
            <span className="font-ui text-xs text-muted-foreground">Replay</span>
          </div>

          <pre className="font-ui flex-1 overflow-hidden rounded-lg bg-muted/60 p-3 text-[0.6875rem] leading-relaxed text-muted-foreground">
            <code>{`{
  "type": "checkout.session.completed",
  "data": {
    "object": {
      "id": "cs_live_a1B2c3",
      "amount_total": 4900,
      "currency": "eur"
    }
  }
}`}</code>
          </pre>

          <div className="grid grid-cols-[1fr_auto] gap-x-3 gap-y-1 border-t border-border pt-2">
            <span className="font-ui col-span-2 text-[0.6875rem] text-muted-foreground">
              Replay destination
            </span>
            <span className="font-ui text-xs text-foreground">localhost:3000/webhooks/stripe</span>
            <span className="font-ui text-xs font-medium text-signal">200 OK · 142ms</span>
          </div>
        </div>
      </div>

      <div className="absolute top-14 left-4 size-2 animate-pulse rounded-full bg-signal opacity-0 [animation-duration:2.8s]" />
    </div>
  )
}