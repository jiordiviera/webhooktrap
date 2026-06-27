import type { ReactNode } from 'react'

export type SortDirection = 'asc' | 'desc'

export type DataTableSort = {
  id: string
  desc: boolean
}

export type DataTableFilterValue = string | boolean | null

export type DataTableFilters = Record<string, DataTableFilterValue>

export type DataTableParams = {
  page: number
  pageSize: number
  sort: DataTableSort | null
  filters: DataTableFilters
  search: string
}

export type DataTableResult<T> = {
  rows: T[]
  total: number
}

export type DataTableResponsiveVisibility = boolean | 'sm' | 'md' | 'lg'

export type DataTableColumnDef<T> = {
  id: string
  header: string
  accessorKey?: keyof T & string
  cell?: (row: T) => ReactNode
  sortable?: boolean
  sortValue?: (row: T) => string | number | null
  hidden?: DataTableResponsiveVisibility
  className?: string
  headerClassName?: string
}

export type DataTableFilterDef = {
  id: string
  label: string
  type: 'text' | 'select'
  placeholder?: string
  options?: Array<{ label: string; value: string }>
}

export type DataTableFetchArgs<TContext> = {
  context: TContext
  params: DataTableParams
}

export type DataTableModel<T, TContext = void> = {
  id: string
  fetch: (args: DataTableFetchArgs<TContext>) => Promise<DataTableResult<T>>
  columns: DataTableColumnDef<T>[]
  getRowId: (row: T) => string
  defaultSort?: DataTableSort
  defaultPageSize?: number
  pageSizeOptions?: number[]
  filters?: DataTableFilterDef[]
  searchPlaceholder?: string
  searchKeys?: Array<keyof T & string>
  emptyMessage?: string
}

export type DataTableCellRenderers<T> = Partial<
  Record<string, (row: T) => ReactNode>
>