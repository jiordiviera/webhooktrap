'use client'

import { useState, useEffect, useCallback, useRef } from 'react'

export function useCountdown() {
  const [remaining, setRemaining] = useState(0)
  const endRef = useRef(0)
  const intervalRef = useRef<ReturnType<typeof setInterval> | undefined>(undefined)

  const start = useCallback((seconds: number) => {
    endRef.current = Date.now() + seconds * 1000
    setRemaining(seconds)
  }, [])

  useEffect(() => {
    if (intervalRef.current) clearInterval(intervalRef.current)

    if (remaining <= 0) return

    intervalRef.current = setInterval(() => {
      const left = Math.max(0, Math.ceil((endRef.current - Date.now()) / 1000))
      if (left <= 0) {
        if (intervalRef.current) clearInterval(intervalRef.current)
        setRemaining(0)
      }
    }, 250)

    return () => {
      if (intervalRef.current) clearInterval(intervalRef.current)
    }
  }, [remaining])

  return { remaining, isRunning: remaining > 0, start }
}
