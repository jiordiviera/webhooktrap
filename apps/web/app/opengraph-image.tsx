import { ImageResponse } from 'next/og'

export const runtime = 'edge'
export const size = { width: 1200, height: 630 }
export const contentType = 'image/png'

export default function OpenGraphImage() {
  return new ImageResponse(
    (
      <div
        style={{
          width: '100%',
          height: '100%',
          display: 'flex',
          flexDirection: 'column',
          alignItems: 'flex-start',
          justifyContent: 'center',
          padding: '80px',
          background: 'oklch(0.21 0.04 42)',
          fontFamily: 'serif',
        }}
      >
        <div
          style={{
            display: 'flex',
            alignItems: 'center',
            gap: 16,
            marginBottom: 32,
          }}
        >
          <svg width="40" height="40" viewBox="0 0 40 40" fill="none">
            <rect width="40" height="40" rx="8" fill="oklch(0.72 0.11 56)" />
            <path
              d="M12 14h16M12 20h12M12 26h14"
              stroke="oklch(0.21 0.04 42)"
              strokeWidth="3"
              strokeLinecap="round"
            />
          </svg>
          <span
            style={{
              fontSize: 24,
              color: 'oklch(0.72 0.11 56)',
              letterSpacing: '0.12em',
              textTransform: 'uppercase',
              fontFamily: 'system-ui',
            }}
          >
            Hookvane
          </span>
        </div>
        <h1
          style={{
            fontSize: 72,
            fontWeight: 600,
            color: 'oklch(0.97 0.01 42)',
            lineHeight: 1.1,
            margin: 0,
            marginBottom: 24,
            letterSpacing: '-0.02em',
          }}
        >
          Catch the hook.
          <br />
          Then debug.
        </h1>
        <p
          style={{
            fontSize: 28,
            color: 'oklch(0.7 0.03 42)',
            margin: 0,
            maxWidth: 600,
            lineHeight: 1.4,
          }}
        >
          Receive webhooks, inspect every payload, replay to your local server.
        </p>
      </div>
    ),
    { width: 1200, height: 630 }
  )
}
