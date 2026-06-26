import type { SimplePaginatorContract } from '@adonisjs/lucid/types/querybuilder'

export type PaginatedMeta = {
  total: number
  perPage: number
  currentPage: number
  lastPage: number
}

export function paginatedPayload<T>(
  key: string,
  paginator: SimplePaginatorContract<T>,
  rows: unknown[]
) {
  return {
    [key]: rows,
    meta: {
      total: paginator.total,
      perPage: paginator.perPage,
      currentPage: paginator.currentPage,
      lastPage: paginator.lastPage,
    } satisfies PaginatedMeta,
  }
}
