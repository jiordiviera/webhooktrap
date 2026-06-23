import styles from '../page.module.css'

const EVENTS = [
  { method: 'POST', label: 'checkout.session.completed', active: true, time: 'just now' },
  { method: 'POST', label: 'payment_intent.succeeded', active: false, time: '4 min ago' },
  { method: 'GET', label: 'challenge?abc123', active: false, time: '12 min ago' },
] as const

export function InspectorMock() {
  return (
    <div className={styles.inspector} aria-hidden="true">
      <div className={styles.inspectorChrome}>
        <span className={styles.inspectorDot} data-tone="warm" />
        <span className={styles.inspectorDot} data-tone="muted" />
        <span className={styles.inspectorDot} data-tone="muted" />
        <span className={styles.inspectorTitle}>Stripe Integration</span>
        <span className={styles.inspectorLive}>Live</span>
      </div>

      <div className={styles.inspectorBody}>
        <div className={styles.inspectorEvents}>
          <p className={styles.inspectorSectionLabel}>Events</p>
          <ul className={styles.inspectorEventList}>
            {EVENTS.map((event) => (
              <li
                key={event.label}
                className={styles.inspectorEvent}
                data-active={event.active ? 'true' : undefined}
              >
                <span className={styles.inspectorMethod}>{event.method}</span>
                <span className={styles.inspectorEventName}>{event.label}</span>
                <span className={styles.inspectorEventTime}>{event.time}</span>
              </li>
            ))}
          </ul>
        </div>

        <div className={styles.inspectorDetail}>
          <div className={styles.inspectorDetailHeader}>
            <span className={styles.inspectorMethod}>POST</span>
            <span className={styles.inspectorPath}>/i/xK9m2pQ7nR4a</span>
          </div>

          <div className={styles.inspectorTabs}>
            <span className={styles.inspectorTab} data-active="true">
              Body
            </span>
            <span className={styles.inspectorTab}>Headers</span>
            <span className={styles.inspectorTab}>Replay</span>
          </div>

          <pre className={styles.inspectorJson}>
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

          <div className={styles.inspectorReplay}>
            <span className={styles.inspectorReplayLabel}>Replay destination</span>
            <span className={styles.inspectorReplayUrl}>localhost:3000/webhooks/stripe</span>
            <span className={styles.inspectorReplayResult}>200 OK · 142ms</span>
          </div>
        </div>
      </div>

      <div className={styles.inspectorPulse} />
    </div>
  )
}