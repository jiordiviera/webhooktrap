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
import { ApiError, apiFetch, SESSION_EXPIRED_EVENT } from '@/lib/api'
import {
  type AuthData,
  type AuthUser,
  clearAuthToken,
  getAuthToken,
  normalizeAuthUser,
  saveAuthToken,
} from '@/lib/auth'
import { fetchProfile as loadProfile } from '@/lib/profile'

type AuthStatus = 'loading' | 'authenticated' | 'unauthenticated'

type AuthContextValue = {
  user: AuthUser | null
  token: string | null
  status: AuthStatus
  isAuthenticated: boolean
  emailVerified: boolean
  signIn: (payload: AuthData) => void
  signInWithToken: (token: string) => Promise<void>
  signOut: () => Promise<void>
  refreshProfile: () => Promise<AuthUser | null>
  setUser: (user: AuthUser) => void
  setEmailVerified: (v: boolean) => void
}

const AuthContext = createContext<AuthContextValue | null>(null)

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<AuthUser | null>(null)
  const [token, setToken] = useState<string | null>(null)
  const [status, setStatus] = useState<AuthStatus>('loading')
  const [emailVerified, setEmailVerified] = useState(true)

  const signIn = useCallback((payload: AuthData) => {
    saveAuthToken(payload.token)
    setToken(payload.token)
    setUser(normalizeAuthUser(payload.user))
    setEmailVerified(payload.email_verified ?? true)
    setStatus('authenticated')
  }, [])

  const signInWithToken = useCallback(async (nextToken: string) => {
    saveAuthToken(nextToken)
    setToken(nextToken)

    const profile = await loadProfile()
    setUser(profile)
    setStatus('authenticated')
  }, [])

  const setUserState = useCallback((nextUser: AuthUser) => {
    setUser(nextUser)
  }, [])

  const refreshProfile = useCallback(async () => {
    const currentToken = getAuthToken()
    if (!currentToken) return null

    const profile = await loadProfile()
    setUser(profile)
    return profile
  }, [])

  const signOut = useCallback(async () => {
    const currentToken = getAuthToken()

    if (currentToken) {
      try {
        await apiFetch('/account/logout', {
          method: 'POST',
          token: currentToken,
        })
      } catch {
        // Clear local session even if the API is unreachable.
      }
    }

    clearAuthToken()
    setToken(null)
    setUser(null)
    setStatus('unauthenticated')
  }, [])

  useEffect(() => {
    function onSessionExpired() {
      setToken(null)
      setUser(null)
      setStatus('unauthenticated')
    }

    window.addEventListener(SESSION_EXPIRED_EVENT, onSessionExpired)
    return () => window.removeEventListener(SESSION_EXPIRED_EVENT, onSessionExpired)
  }, [])

  useEffect(() => {
    let cancelled = false

    async function restoreSession() {
      const storedToken = getAuthToken()

      if (!storedToken) {
        if (!cancelled) setStatus('unauthenticated')
        return
      }

      try {
        const profile = await loadProfile()
        if (cancelled) return
        setToken(storedToken)
        setUser(profile)
        setStatus('authenticated')
      } catch (error) {
        if (cancelled) return
        if (error instanceof ApiError && error.status === 401) {
          clearAuthToken()
        }
        setToken(null)
        setUser(null)
        setStatus('unauthenticated')
      }
    }

    void restoreSession()

    return () => {
      cancelled = true
    }
  }, [])

  const value = useMemo<AuthContextValue>(
    () => ({
      user,
      token,
      status,
      isAuthenticated: status === 'authenticated' && user !== null,
      emailVerified,
      signIn,
      signInWithToken,
      signOut,
      refreshProfile,
      setUser: setUserState,
      setEmailVerified,
    }),
    [user, token, status, emailVerified, signIn, signInWithToken, signOut, refreshProfile, setUserState]
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export function useAuth() {
  const context = useContext(AuthContext)
  if (!context) {
    throw new Error('useAuth must be used within AuthProvider')
  }
  return context
}