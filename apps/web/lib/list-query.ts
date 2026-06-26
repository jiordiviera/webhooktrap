import type { DataTableParams } from '@/features/data-table/types'

export type PaginatedMeta = {
  total: number
  perPage: number
  currentPage: number
  lastPage: number
}

export function buildListQueryString(params: DataTableParams) {
  const query = new URLSearchParams()

  query.set('page', String(params.page))
  query.set('page_size', String(params.pageSize))

  if (params.search.trim()) {
    query.set('search', params.search.trim())
  }

  if (params.sort) {
    query.set('sort', params.sort.id)
    query.set('sort_dir', params.sort.desc ? 'desc' : 'asc')
  }

  for (const [key, value] of Object.entries(params.filters)) {
    if (value !== null && value !== undefined && value !== '') {
      query.set(`filters[${key}]`, String(value))
    }
  }

  const serialized = query.toString()
  return serialized ? `?${serialized}` : ''
}

export function parsePaginatedResponse<TKey extends string, TItem>(
  body: {
    data?: Record<string, unknown>
  },
  key: TKey
): { rows: TItem[]; total: number } {
  const data = body.data ?? {}
  const rows = (data[key] as TItem[] | undefined) ?? []
  const meta = data.meta as PaginatedMeta | undefined

  return {
    rows,
    total: meta?.total ?? rows.length,
  }
}