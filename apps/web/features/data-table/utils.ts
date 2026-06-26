import type {
  DataTableColumnDef,
  DataTableModel,
  DataTableParams,
  DataTableResult,
  DataTableSort,
} from '@/features/data-table/types'

function compareValues(a: unknown, b: unknown, desc: boolean) {
  const direction = desc ? -1 : 1

  if (a === b) return 0
  if (a === null || a === undefined) return 1 * direction
  if (b === null || b === undefined) return -1 * direction

  if (typeof a === 'number' && typeof b === 'number') {
    return a === b ? 0 : a < b ? -1 * direction : 1 * direction
  }

  const aText = String(a).toLowerCase()
  const bText = String(b).toLowerCase()
  return aText.localeCompare(bText) * direction
}

function getSortValue<T>(row: T, column: DataTableColumnDef<T>) {
  if (column.sortValue) return column.sortValue(row)
  if (column.accessorKey) return row[column.accessorKey] as string | number | null
  return null
}

function sortRows<T>(rows: T[], columns: DataTableColumnDef<T>[], sort: DataTableSort) {
  const column = columns.find((item) => item.id === sort.id)
  if (!column) return rows

  return [...rows].sort((left, right) =>
    compareValues(getSortValue(left, column), getSortValue(right, column), sort.desc)
  )
}

function filterRows<T>(
  rows: T[],
  columns: DataTableColumnDef<T>[],
  searchKeys: Array<keyof T & string> | undefined,
  params: DataTableParams
) {
  let result = rows

  const query = params.search.trim().toLowerCase()
  if (query) {
    const keys =
      searchKeys ??
      columns
        .filter((column) => column.accessorKey)
        .map((column) => column.accessorKey as keyof T & string)

    result = result.filter((row) =>
      keys.some((key) => String(row[key] ?? '').toLowerCase().includes(query))
    )
  }

  for (const [filterId, value] of Object.entries(params.filters)) {
    if (value === null || value === undefined || value === '') continue

    const column = columns.find((item) => item.id === filterId)
    if (!column?.accessorKey) continue

    result = result.filter(
      (row) => String(row[column.accessorKey as keyof T] ?? '') === String(value)
    )
  }

  return result
}

export function applyDataTableParams<T>(
  rows: T[],
  model: Pick<DataTableModel<T>, 'columns' | 'searchKeys'>,
  params: DataTableParams
): DataTableResult<T> {
  let result = filterRows(rows, model.columns, model.searchKeys, params)

  if (params.sort) {
    result = sortRows(result, model.columns, params.sort)
  }

  const total = result.length
  const start = (params.page - 1) * params.pageSize

  return {
    rows: result.slice(start, start + params.pageSize),
    total,
  }
}

export function getResponsiveClass(hidden: DataTableColumnDef<unknown>['hidden']) {
  if (hidden === true) return 'hidden'
  if (hidden === 'sm') return 'hidden sm:table-cell'
  if (hidden === 'md') return 'hidden md:table-cell'
  if (hidden === 'lg') return 'hidden lg:table-cell'
  return undefined
}

export const DEFAULT_PAGE_SIZE_OPTIONS = [10, 25, 50] as const

export function createInitialParams(model: {
  defaultSort?: DataTableSort
  defaultPageSize?: number
  filters?: Array<{ id: string }>
}): DataTableParams {
  const filters: DataTableParams['filters'] = {}

  for (const filter of model.filters ?? []) {
    filters[filter.id] = null
  }

  return {
    page: 1,
    pageSize: model.defaultPageSize ?? 10,
    sort: model.defaultSort ?? null,
    filters,
    search: '',
  }
}