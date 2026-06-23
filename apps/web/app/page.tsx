import Image from 'next/image'
import { CreateInboxCta } from './components/create-inbox-cta'
import { InspectorMock } from './components/inspector-mock'
import styles from './page.module.css'
import Link from 'next/link'

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
    <div className={styles.page}>
      <header className={styles.header}>
        <Link href="/" className={styles.brand}>
          <Image
            src="/logo.png"
            alt="Hookscope"
            width={148}
            height={40}
            priority
            className={styles.brandLogo}
          />
        </Link>
        <nav className={styles.nav} aria-label="Primary">
          <a href="#flow">Flow</a>
          <a href="https://github.com" className={styles.navMuted}>
            GitHub
          </a>
        </nav>
      </header>

      <main>
        <section className={styles.hero}>
          <div className={styles.heroCopy}>
            <p className={styles.heroKicker}>Webhook debugger</p>
            <h1 className={styles.heroTitle}>
              See every payload
              <span className={styles.heroTitleBreak}>before it vanishes.</span>
            </h1>
            <p className={styles.heroLead}>
              Hookscope catches incoming webhooks, shows you the full request, and replays it to
              your local server. No tunnels to configure, no bloated dashboard.
            </p>
            <CreateInboxCta />
          </div>

          <div className={styles.heroVisual}>
            <InspectorMock />
          </div>
        </section>

        <section id="flow" className={styles.flow}>
          <div className={styles.flowIntro}>
            <h2 className={styles.flowTitle}>Receive, inspect, replay</h2>
            <p className={styles.flowLead}>
              Three moves. That is the whole product. Built for the afternoon before your demo, not
              for enterprise procurement.
            </p>
          </div>

          <ol className={styles.flowList}>
            {FLOW.map((item) => (
              <li key={item.step} className={styles.flowItem}>
                <span className={styles.flowStep}>{item.step}</span>
                <div className={styles.flowContent}>
                  <h3 className={styles.flowItemTitle}>{item.title}</h3>
                  <p className={styles.flowItemBody}>{item.body}</p>
                </div>
              </li>
            ))}
          </ol>
        </section>

        <section className={styles.closing}>
          <h2 className={styles.closingTitle}>Your next webhook is already on its way.</h2>
          <p className={styles.closingLead}>
            Open an inbox in seconds. Anonymous inboxes last 48 hours. Sign in later to keep them.
          </p>
          <CreateInboxCta />
        </section>
      </main>

      <footer className={styles.footer}>
        <span className={styles.footerWordmark}>Hookscope</span>
        <p className={styles.footerNote}>Developer-first debugging. Receive → Replay → Response.</p>
      </footer>
    </div>
  )
}