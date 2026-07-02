'use client'

import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from 'react'

const CONSENT_KEY = 'hs:consent'
const CONSENT_VERSION = '1'

export type ConsentStatus = 'accepted' | 'declined'

type StoredConsent = { status: ConsentStatus; version: string }

type ConsentContextValue = {
  /** null until the stored choice (if any) has been read on the client */
  status: ConsentStatus | null
  accept: () => void
  decline: () => void
}

const ConsentContext = createContext<ConsentContextValue | null>(null)

function readStoredConsent(): ConsentStatus | null {
  try {
    const raw = window.localStorage.getItem(CONSENT_KEY)
    if (!raw) return null
    const parsed = JSON.parse(raw) as StoredConsent
    return parsed.version === CONSENT_VERSION ? parsed.status : null
  } catch {
    return null
  }
}

function writeStoredConsent(status: ConsentStatus) {
  const value: StoredConsent = { status, version: CONSENT_VERSION }
  window.localStorage.setItem(CONSENT_KEY, JSON.stringify(value))
}

export function ConsentProvider({ children }: { children: ReactNode }) {
  const [status, setStatus] = useState<ConsentStatus | null>(null)

  useEffect(() => {
    setStatus(readStoredConsent())
  }, [])

  const accept = useCallback(() => {
    writeStoredConsent('accepted')
    setStatus('accepted')
  }, [])

  const decline = useCallback(() => {
    writeStoredConsent('declined')
    setStatus('declined')
  }, [])

  const value = useMemo(() => ({ status, accept, decline }), [status, accept, decline])

  return <ConsentContext.Provider value={value}>{children}</ConsentContext.Provider>
}

export function useConsent() {
  const ctx = useContext(ConsentContext)
  if (!ctx) throw new Error('useConsent must be used within a ConsentProvider')
  return ctx
}
