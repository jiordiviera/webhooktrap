import type { HttpContext } from '@adonisjs/core/http'
import { listQueryValidator } from '#validators/list_query_validator'

export type ListQuery = {
  page: number
  pageSize: number
  sort: string | null
  sortDir: 'asc' | 'desc'
  search: string | null
  filters: Record<string, string>
}

type ParseListQueryOptions = {
  sortable: string[]
  defaultSort: string
  defaultSortDir?: 'asc' | 'desc'
  filterable?: string[]
}

export async function parseListQuery(
  request: HttpContext['request'],
  options: ParseListQueryOptions
): Promise<ListQuery> {
  const payload = await request.validateUsing(listQueryValidator)
  const filterable = new Set(options.filterable ?? [])
  const rawFilters = payload.filters ?? {}

  const filters: Record<string, string> = {}
  for (const [key, value] of Object.entries(rawFilters)) {
    if (!filterable.has(key)) continue
    if (typeof value === 'string' && value.trim()) {
      filters[key] = value.trim()
    }
  }

  const sort = payload.sort && options.sortable.includes(payload.sort) ? payload.sort : null

  return {
    page: payload.page ?? 1,
    pageSize: payload.page_size ?? 10,
    sort,
    sortDir: payload.sort_dir ?? options.defaultSortDir ?? 'desc',
    search: payload.search?.trim() || null,
    filters,
  }
}

export function resolveSortColumn(
  sort: string | null,
  sortable: Record<string, string>,
  fallback: string
) {
  if (!sort) return fallback
  return sortable[sort] ?? fallback
}
