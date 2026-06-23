export const AUTH_TOKEN_KEY = 'hookscope_token'

export type AuthUser = {
  id: number
  fullName: string | null
  email: string
  initials: string
}

export type AuthPayload = {
  data: {
    user: AuthUser
    token: string
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
  const apiUrl = process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:3333'
  const params = new URLSearchParams({ return_to: returnTo })
  return `${apiUrl}/api/v1/auth/oauth/${provider}/redirect?${params}`
}