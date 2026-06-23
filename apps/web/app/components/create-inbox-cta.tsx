'use client'

import { useState } from 'react'
import styles from '../page.module.css'

type InboxResponse = {
  data: {
    inbox: {
      id: string
      ingestUrl: string
    }
  }
}

const API_URL = process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:3333'

export function CreateInboxCta() {
  const [status, setStatus] = useState<'idle' | 'loading' | 'ready' | 'error'>('idle')
  const [ingestUrl, setIngestUrl] = useState('')
  const [copied, setCopied] = useState(false)

  async function createInbox() {
    setStatus('loading')
    setCopied(false)

    try {
      const response = await fetch(`${API_URL}/api/v1/inboxes`, { method: 'POST' })
      if (!response.ok) throw new Error('Failed to create inbox')

      const body = (await response.json()) as InboxResponse
      const url = `${API_URL}${body.data.inbox.ingestUrl}`
      setIngestUrl(url)
      setStatus('ready')
    } catch {
      setStatus('error')
    }
  }

  async function copyUrl() {
    if (!ingestUrl) return
    await navigator.clipboard.writeText(ingestUrl)
    setCopied(true)
    window.setTimeout(() => setCopied(false), 2000)
  }

  return (
    <div className={styles.ctaBlock}>
      <div className={styles.ctaActions}>
        <button
          type="button"
          className={styles.ctaPrimary}
          onClick={createInbox}
          disabled={status === 'loading'}
        >
          {status === 'loading' ? 'Opening inbox…' : 'Open a free inbox'}
        </button>
        <a href="#flow" className={styles.ctaGhost}>
          See how it works
        </a>
      </div>

      {status === 'ready' && (
        <div className={styles.ctaResult} role="status">
          <p className={styles.ctaResultLabel}>Your ingest URL</p>
          <div className={styles.ctaResultRow}>
            <code className={styles.ctaResultUrl}>{ingestUrl}</code>
            <button type="button" className={styles.ctaCopy} onClick={copyUrl}>
              {copied ? 'Copied' : 'Copy'}
            </button>
          </div>
          <p className={styles.ctaResultHint}>
            Point Stripe, GitHub, or any provider at this URL. Events appear in your inbox.
          </p>
        </div>
      )}

      {status === 'error' && (
        <p className={styles.ctaError} role="alert">
          Could not reach the API. Start the server on port 3333 and try again.
        </p>
      )}
    </div>
  )
}