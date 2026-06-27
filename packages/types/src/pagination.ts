export type SortDir = 'asc' | 'desc'

export type ListQueryParams = {
  page?: number
  pageSize?: number
  search?: string
  sort?: string | null
  sortDir?: SortDir | null
  filters?: Record<string, string | undefined>
}

export type DataTableParams = {
  page: number
  pageSize: number
  sort: { id: string; desc: boolean } | null
  filters: Record<string, string | undefined>
  search: string
}
