import { sanitizeAvatarUrl } from '@/lib/avatar'

export const AUTH_TOKEN_KEY = 'hookscope_token'

export type AuthUser = {
  id: number
  fullName: string | null
  email: string
  initials: string
  avatar: string | null
  createdAt?: string
  updatedAt?: string | null
}

export type AuthPayload = {
  data: {
    user: AuthUser
    token: string
  }
}

export function normalizeAuthUser(user: AuthUser): AuthUser {
  return {
    ...user,
    avatar: sanitizeAvatarUrl(user.avatar),
  }
}

export function saveAuthToken(token: string) {
  localStorage.setItem(AUTH_TOKEN_KEY, token)
}

export function getAuthToken(): string | null {
  if (typeof window === 'undefined') return null
  return localStorage.getItem(AUTH_TOKEN_KEY)
}

export function clearAuthToken() {
  localStorage.removeItem(AUTH_TOKEN_KEY)
}

export function getOAuthRedirectUrl(provider: 'github' | 'google', returnTo: string) {
  const origin =
    typeof window !== 'undefined'
      ? window.location.origin
      : (process.env.NEXT_PUBLIC_WEB_URL ?? process.env.WEB_URL ?? 'http://localhost:7777')
  const params = new URLSearchParams({ return_to: returnTo })
  return `${origin}/api/v1/auth/oauth/${provider}/redirect?${params}`
}