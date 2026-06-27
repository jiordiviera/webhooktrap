export type ApiResponse<T> = {
  success: boolean
  data: T
  meta?: Record<string, unknown>
}

export type PaginatedMeta = {
  total: number
  page: number
  pageSize: number
  totalPages: number
}

export type PaginatedResponse<T> = ApiResponse<Record<string, T[]>> & {
  meta: PaginatedMeta
}
