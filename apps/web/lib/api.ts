import axios, { type AxiosRequestConfig, isAxiosError } from 'axios'

const API_URL = process.env.NEXT_PUBLIC_API_URL ?? 'http://localhost:3333'

export type ApiErrorBody = {
  message?: string
  errors?: Array<{ message: string; field?: string }>
}

export class ApiError extends Error {
  status: number
  body: ApiErrorBody

  constructor(status: number, body: ApiErrorBody) {
    super(body.message ?? 'Request failed')
    this.status = status
    this.body = body
  }
}

export const apiClient = axios.create({
  baseURL: API_URL,
  headers: {
    Accept: 'application/json',
  },
})

export type ApiFetchInit = Omit<AxiosRequestConfig, 'url' | 'baseURL'> & {
  token?: string | null
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
  const { token, headers, body, data, method, ...rest } = init ?? {}
  const payload = resolveRequestData(init)
  const isFormData = typeof FormData !== 'undefined' && payload instanceof FormData

  const requestHeaders: AxiosRequestConfig['headers'] = {
    ...(token ? { Authorization: `Bearer ${token}` } : {}),
    ...headers,
  }

  if (!isFormData && payload !== undefined) {
    requestHeaders['Content-Type'] ??= 'application/json'
  }

  try {
    const response = await apiClient.request<T>({
      url: path,
      method: method ?? 'GET',
      headers: requestHeaders,
      data: payload,
      ...rest,
    })

    return response.data
  } catch (error) {
    if (isAxiosError(error) && error.response) {
      const responseBody = (error.response.data ?? {}) as ApiErrorBody
      throw new ApiError(error.response.status, responseBody)
    }

    throw error
  }
}

export { API_URL }