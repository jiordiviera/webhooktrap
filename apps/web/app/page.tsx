import Image from 'next/image'
import Link from 'next/link'
import { AuthNav } from './components/auth-nav'
import { CreateInboxCta } from './components/create-inbox-cta'
import { LiveWebhookDemo } from './components/landing/live-webhook-demo'
import { ProviderTicker } from './components/landing/provider-ticker'
import { ScrollReveal } from './components/landing/scroll-reveal'
import { SignalField } from './components/landing/signal-field'
import './landing.css'

const FLOW = [
  {
    step: '01',
    title: 'Receive',
    body: 'Drop the ingest URL into Stripe, GitHub, Shopify, or curl. Every method, header, and byte lands in one inbox.',
  },
  {
    step: '02',
    title: 'Inspect',
    body: 'Read the payload as it arrived. Copy JSON, rebuild cURL, share a read-only link with your teammate.',
  },
  {
    step: '03',
    title: 'Replay',
    body: 'Send the same event to localhost. See status, headers, latency, and the response body in one pass.',
  },
] as const

export default function Home() {
  return (
    <div className="flex min-h-svh flex-col">
      <section className="relative flex min-h-svh flex-col overflow-hidden">
        <SignalField />

        <header className="relative z-10 flex animate-in items-center justify-between px-6 py-5 duration-700 fade-in slide-in-from-bottom-4 md:px-16">
          <Link href="/" className="flex items-center">
            <Image
              src="/logo.png"
              alt="Hookscope"
              width={148}
              height={40}
              priority
              className="h-auto w-[clamp(7.5rem,18vw,9.25rem)]"
            />
          </Link>
          <nav className="font-ui flex items-center gap-5 text-[0.9375rem]" aria-label="Primary">
            <Link href="#demo" className="transition-colors hover:text-primary">
              Demo
            </Link>
            <Link href="#flow" className="transition-colors hover:text-primary">
              Flow
            </Link>
            <AuthNav />
          </nav>
        </header>

        <div className="relative z-10 grid flex-1 items-center gap-12 px-6 pb-20 md:grid-cols-2 md:gap-16 md:px-16 md:pb-28">
          <div className="max-w-xl">
            <p
              className="font-ui mb-5 animate-in text-[0.8125rem] tracking-[0.14em] text-muted-foreground uppercase duration-700 fade-in slide-in-from-bottom-3"
              style={{ animationDelay: '80ms', animationFillMode: 'both' }}
            >
              Webhook debugger
            </p>
            <h1
              className="mb-6 animate-in text-[clamp(2.75rem,6.5vw,5rem)] leading-[1.02] font-semibold tracking-wide text-foreground duration-700 fade-in slide-in-from-bottom-4"
              style={{ animationDelay: '160ms', animationFillMode: 'both' }}
            >
              Catch the hook.
              <span className="block font-medium text-primary">Replay the proof.</span>
            </h1>
            <p
              className="mb-9 max-w-[36ch] animate-in text-[clamp(1.0625rem,1.6vw,1.1875rem)] leading-relaxed text-muted-foreground duration-700 fade-in slide-in-from-bottom-4"
              style={{ animationDelay: '240ms', animationFillMode: 'both' }}
            >
              Hookscope shows every webhook as it lands, then fires it back to your local server.
              No tunnel setup. No dashboard maze.
            </p>
            <div
              className="animate-in duration-700 fade-in slide-in-from-bottom-4"
              style={{ animationDelay: '320ms', animationFillMode: 'both' }}
            >
              <CreateInboxCta />
            </div>
          </div>

          <div
            className="animate-in duration-700 fade-in slide-in-from-bottom-6 max-md:order-first max-md:max-w-xl"
            style={{ animationDelay: '400ms', animationFillMode: 'both' }}
          >
            <LiveWebhookDemo />
          </div>
        </div>

        <div
          className="relative z-10 flex animate-in justify-center pb-8 duration-700 fade-in motion-reduce:hidden"
          style={{ animationDelay: '600ms', animationFillMode: 'both' }}
          aria-hidden
        >
          <div className="flex flex-col items-center gap-2 text-muted-foreground/60">
            <span className="font-ui text-[0.625rem] tracking-widest uppercase">Scroll</span>
            <div className="h-8 w-px bg-linear-to-b from-primary/40 to-transparent" />
          </div>
        </div>
      </section>

      <ProviderTicker />

      <section id="demo" className="scroll-mt-20 px-6 py-20 md:px-16 md:py-28">
        <ScrollReveal>
          <div className="mb-12 max-w-2xl">
            <p className="font-ui mb-3 text-[0.8125rem] tracking-[0.14em] text-muted-foreground uppercase">
              Live demo
            </p>
            <h2 className="mb-4 text-[clamp(2rem,4.5vw,3.25rem)] leading-tight font-semibold">
              Watch a Stripe webhook land, then replay it.
            </h2>
            <p className="max-w-[48ch] text-muted-foreground">
              The loop below runs continuously: listen, capture, inspect, replay. That is the whole
              product in under ten seconds.
            </p>
          </div>
        </ScrollReveal>

        <ScrollReveal delay={120}>
          <LiveWebhookDemo large className="mx-auto max-w-4xl" />
        </ScrollReveal>
      </section>

      <section
        id="flow"
        className="scroll-mt-20 border-t border-border bg-linear-to-b from-background to-secondary/80 px-6 py-20 md:px-16 md:py-28"
      >
        <ScrollReveal>
          <div className="mb-14 max-w-md">
            <h2 className="mb-4 text-[clamp(2rem,4vw,2.75rem)] leading-tight font-semibold">
              Receive, inspect, replay
            </h2>
            <p className="max-w-[42ch] text-muted-foreground">
              Three moves. Built for the afternoon before your demo, not for enterprise
              procurement.
            </p>
          </div>
        </ScrollReveal>

        <ol className="max-w-3xl">
          {FLOW.map((item, index) => (
            <ScrollReveal key={item.step} delay={index * 100}>
              <li className="grid items-start gap-4 border-b border-border py-8 first:border-t md:grid-cols-[4.5rem_1fr] md:gap-6 md:py-12">
                <span className="text-[clamp(2rem,4vw,2.75rem)] leading-none font-semibold text-primary/55 transition-colors duration-500 hover:text-primary">
                  {item.step}
                </span>
                <div>
                  <h3 className="mb-2 text-[clamp(1.5rem,2.5vw,1.875rem)] font-semibold">
                    {item.title}
                  </h3>
                  <p className="max-w-[52ch] text-muted-foreground">{item.body}</p>
                </div>
              </li>
            </ScrollReveal>
          ))}
        </ol>
      </section>

      <section className="relative overflow-hidden px-6 py-20 md:px-16 md:py-28">
        <div className="landing-signal-orb pointer-events-none absolute -right-16 top-1/2 size-64 -translate-y-1/2 rounded-full bg-primary/8 blur-3xl" />
        <ScrollReveal>
          <div className="relative max-w-xl">
            <h2 className="mb-4 text-[clamp(2rem,4.5vw,3rem)] leading-tight font-semibold">
              Your next webhook is already on its way.
            </h2>
            <p className="mb-8 max-w-[40ch] text-muted-foreground">
              Open an inbox in seconds. Anonymous inboxes last 48 hours. Sign in later to keep
              them.
            </p>
            <CreateInboxCta showSecondaryLink={false} />
          </div>
        </ScrollReveal>
      </section>

      <footer className="mt-auto flex flex-wrap items-baseline gap-x-8 gap-y-3 border-t border-border px-6 py-8 md:px-16">
        <span className="text-xl font-semibold tracking-widest text-wordmark">Hookscope</span>
        <p className="font-ui text-[0.8125rem] text-muted-foreground">
          Developer-first debugging. Receive → Replay → Response.
        </p>
      </footer>
    </div>
  )
}