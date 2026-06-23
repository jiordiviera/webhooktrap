import Image from 'next/image'
import Link from 'next/link'
import { CreateInboxCta } from './components/create-inbox-cta'
import { InspectorMock } from './components/inspector-mock'

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
      <header className="flex animate-in items-center justify-between px-6 py-5 duration-700 fade-in slide-in-from-bottom-4 md:px-16">
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
          <Link href="#flow" className="transition-colors hover:text-primary">
            Flow
          </Link>
          <a
            href="https://github.com"
            className="text-muted-foreground transition-colors hover:text-primary"
          >
            GitHub
          </a>
        </nav>
      </header>

      <main>
        <section className="grid items-center gap-10 px-6 pb-16 md:grid-cols-2 md:gap-20 md:px-16 md:pb-28">
          <div className="max-w-xl animate-in duration-700 fade-in slide-in-from-bottom-4 [animation-delay:100ms]">
            <p className="font-ui mb-5 text-[0.8125rem] tracking-[0.14em] text-muted-foreground uppercase">
              Webhook debugger
            </p>
            <h1 className="mb-6 text-[clamp(2.75rem,6.5vw,4.75rem)] leading-[1.05] font-semibold tracking-wide text-foreground">
              See every payload
              <span className="block font-medium text-primary">before it vanishes.</span>
            </h1>
            <p className="mb-9 max-w-[34ch] text-[clamp(1.0625rem,1.6vw,1.1875rem)] leading-relaxed text-muted-foreground">
              Hookscope catches incoming webhooks, shows you the full request, and replays it to
              your local server. No tunnels to configure, no bloated dashboard.
            </p>
            <CreateInboxCta />
          </div>

          <div className="animate-in duration-700 fade-in slide-in-from-bottom-4 [animation-delay:250ms] max-md:order-first max-md:max-w-xl">
            <InspectorMock />
          </div>
        </section>

        <section
          id="flow"
          className="border-t border-border bg-linear-to-b from-background to-secondary/80 px-6 py-16 md:px-16 md:py-24"
        >
          <div className="mb-10 max-w-md md:mb-16">
            <h2 className="mb-4 text-[clamp(2rem,4vw,2.75rem)] leading-tight font-semibold">
              Receive, inspect, replay
            </h2>
            <p className="max-w-[42ch] text-muted-foreground">
              Three moves. That is the whole product. Built for the afternoon before your demo, not
              for enterprise procurement.
            </p>
          </div>

          <ol className="max-w-3xl">
            {FLOW.map((item) => (
              <li
                key={item.step}
                className="grid items-start gap-4 border-b border-border py-7 first:border-t md:grid-cols-[4.5rem_1fr] md:gap-6 md:py-10"
              >
                <span className="text-[clamp(2rem,4vw,2.75rem)] leading-none font-semibold text-primary/55">
                  {item.step}
                </span>
                <div>
                  <h3 className="mb-2 text-[clamp(1.5rem,2.5vw,1.875rem)] font-semibold">
                    {item.title}
                  </h3>
                  <p className="max-w-[52ch] text-muted-foreground">{item.body}</p>
                </div>
              </li>
            ))}
          </ol>
        </section>

        <section className="max-w-xl px-6 py-16 md:px-16 md:py-28">
          <h2 className="mb-4 text-[clamp(2rem,4.5vw,3rem)] leading-tight font-semibold">
            Your next webhook is already on its way.
          </h2>
          <p className="mb-8 max-w-[40ch] text-muted-foreground">
            Open an inbox in seconds. Anonymous inboxes last 48 hours. Sign in later to keep them.
          </p>
          <CreateInboxCta showSecondaryLink={false} />
        </section>
      </main>

      <footer className="mt-auto flex flex-wrap items-baseline gap-x-8 gap-y-3 border-t border-border px-6 py-8 md:px-16">
        <span className="text-xl font-semibold tracking-widest text-wordmark">Hookscope</span>
        <p className="font-ui text-[0.8125rem] text-muted-foreground">
          Developer-first debugging. Receive → Replay → Response.
        </p>
      </footer>
    </div>
  )
}