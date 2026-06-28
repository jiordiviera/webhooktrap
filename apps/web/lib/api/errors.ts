import type { AxiosError } from 'axios'

export type ApiErrorBody = {
  message?: string
  retryAfter?: number
  errors?: Array<{ message: string; field?: string }>
}

export class ApiError extends Error {
  status: number
  body: ApiErrorBody

  constructor(status: number, body: ApiErrorBody) {
    super(body.message ?? body.errors?.[0]?.message ?? 'Request failed')
    this.name = 'ApiError'
    this.status = status
    this.body = body
  }
}

export function parseApiErrorBody(data: unknown): ApiErrorBody {
  if (!data || typeof data !== 'object') return {}

  const body = data as ApiErrorBody
  return {
    message: typeof body.message === 'string' ? body.message : undefined,
    errors: Array.isArray(body.errors) ? body.errors : undefined,
  }
}

export function toApiError(error: AxiosError): ApiError {
  const status = error.response?.status ?? 0
  const body = parseApiErrorBody(error.response?.data)
  return new ApiError(status, body)
}