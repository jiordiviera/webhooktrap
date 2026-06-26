import type { AxiosRequestConfig } from 'axios'
import { apiClient } from '@/lib/api/client'

export { APP_URL, apiClient, SESSION_EXPIRED_EVENT } from '@/lib/api/client'
export { ApiError, type ApiErrorBody } from '@/lib/api/errors'

export type ApiFetchInit = Omit<AxiosRequestConfig, 'url' | 'baseURL'> & {
  token?: string | null
  skipAuth?: boolean
  body?: BodyInit | null
}

function resolveRequestData(init?: ApiFetchInit) {
  if (init?.data !== undefined) return init.data
  if (init?.body === undefined || init?.body === null) return undefined

  if (typeof init.body === 'string') {
    try {
      return JSON.parse(init.body) as unknown
    } catch {
      return init.body
    }
  }

  return init.body
}

export async function apiFetch<T>(path: string, init?: ApiFetchInit): Promise<T> {
  const { token, skipAuth, method, ...rest } = init ?? {}

  const response = await apiClient.request<T>({
    url: path,
    method: method ?? 'GET',
    data: resolveRequestData(init),
    token,
    skipAuth,
    ...rest,
  })

  return response.data
}