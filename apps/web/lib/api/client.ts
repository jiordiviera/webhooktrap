import axios, { isAxiosError, type InternalAxiosRequestConfig } from 'axios'
import { clearAuthToken, getAuthToken } from '@/lib/auth'
import { toApiError } from '@/lib/api/errors'

/** Server-only API origin. Browser calls same-origin /api/v1 via proxy.ts. */
const SERVER_APP_URL = process.env.APP_URL ?? 'http://localhost:3333'

function resolveApiBaseUrl() {
  if (typeof window !== 'undefined') return ''
  return SERVER_APP_URL
}

export const APP_URL = SERVER_APP_URL

export const SESSION_EXPIRED_EVENT = 'hookscope:session-expired'

declare module 'axios' {
  export interface AxiosRequestConfig {
    /** Explicit bearer token. `null` disables auth for this request. */
    token?: string | null
    /** Skip automatic Authorization header injection. */
    skipAuth?: boolean
  }
}

function resolveAuthToken(config: InternalAxiosRequestConfig): string | null {
  if (config.skipAuth) return null

  if (config.token !== undefined) {
    return config.token
  }

  return getAuthToken()
}

function setContentType(config: InternalAxiosRequestConfig) {
  const isFormData = typeof FormData !== 'undefined' && config.data instanceof FormData

  if (isFormData || config.data === undefined) return

  if (!config.headers.has('Content-Type')) {
    config.headers.set('Content-Type', 'application/json')
  }
}

function handleUnauthorized(config: InternalAxiosRequestConfig | undefined) {
  if (typeof window === 'undefined' || !config || config.skipAuth) return

  const sentAuth = config.headers.get('Authorization')
  if (!sentAuth) return

  clearAuthToken()
  window.dispatchEvent(new CustomEvent(SESSION_EXPIRED_EVENT))
}

export const apiClient = axios.create({
  baseURL: resolveApiBaseUrl(),
  timeout: 30_000,
  headers: {
    Accept: 'application/json',
  },
})

apiClient.interceptors.request.use(
  (config) => {
    setContentType(config)

    const token = resolveAuthToken(config)
    if (token) {
      config.headers.set('Authorization', `Bearer ${token}`)
    } else {
      config.headers.delete('Authorization')
    }

    return config
  },
  (error) => Promise.reject(error)
)

apiClient.interceptors.response.use(
  (response) => response,
  (error) => {
    if (isAxiosError(error) && error.response) {
      if (error.response.status === 401) {
        handleUnauthorized(error.config)
      }

      return Promise.reject(toApiError(error))
    }

    return Promise.reject(error)
  }
)