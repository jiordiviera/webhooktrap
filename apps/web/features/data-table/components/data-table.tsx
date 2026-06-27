'use client'

import { useEffect } from 'react'
import { IconArrowDown, IconArrowUp, IconArrowsSort } from '@tabler/icons-react'
import { Button } from '@workspace/ui/components/button'
import { Skeleton } from '@workspace/ui/components/skeleton'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@workspace/ui/components/table'
import { cn } from '@workspace/ui/lib/utils'
import { DataTablePagination } from '@/features/data-table/components/data-table-pagination'
import { DataTableToolbar } from '@/features/data-table/components/data-table-toolbar'
import { useDataTableQuery } from '@/features/data-table/hooks/use-data-table-query'
import type {
  DataTableModelContextMap,
  DataTableModelId,
  DataTableRowMap,
} from '@/features/data-table/registry'
import type {
  DataTableCellRenderers,
  DataTableColumnDef,
  DataTableModel,
} from '@/features/data-table/types'
import { getResponsiveClass } from '@/features/data-table/utils'

type DataTableProps<TModel extends DataTableModelId> = {
  model: TModel
  context?: DataTableModelContextMap[TModel]
  cellRenderers?: DataTableCellRenderers<DataTableRowMap[TModel]>
  selectedRowId?: string | null
  onRowClickAction?: (row: DataTableRowMap[TModel]) => void
  refetchInterval?: number
  enabled?: boolean
  showToolbar?: boolean
  showPagination?: boolean
  toolbarActions?: React.ReactNode
  className?: string
  emptyState?: React.ReactNode
  onDataChangeAction?: (data: { rows: DataTableRowMap[TModel][]; total: number }) => void
}

function SortIcon({ active, desc }: { active: boolean; desc: boolean }) {
  if (!active) return <IconArrowsSort className="size-3.5 opacity-40" aria-hidden />
  if (desc) return <IconArrowDown className="size-3.5" aria-hidden />
  return <IconArrowUp className="size-3.5" aria-hidden />
}

function renderCell<T>(
  row: T,
  column: DataTableColumnDef<T>,
  cellRenderers?: DataTableCellRenderers<T>
) {
  if (cellRenderers?.[column.id]) return cellRenderers[column.id]!(row)
  if (column.cell) return column.cell(row)
  if (column.accessorKey) return String(row[column.accessorKey] ?? '')
  return null
}

function TableSkeleton({ columns }: { columns: number }) {
  return (
    <>
      {Array.from({ length: 3 }).map((_, rowIndex) => (
        <TableRow key={rowIndex}>
          {Array.from({ length: columns }).map((__, cellIndex) => (
            <TableCell key={cellIndex}>
              <Skeleton className="h-4 w-full max-w-32" />
            </TableCell>
          ))}
        </TableRow>
      ))}
    </>
  )
}

export function DataTable<TModel extends DataTableModelId>({
  model: modelId,
  context,
  cellRenderers,
  selectedRowId,
  onRowClickAction,
  refetchInterval,
  enabled = true,
  showToolbar = true,
  showPagination = true,
  toolbarActions,
  className,
  emptyState,
  onDataChangeAction,
}: DataTableProps<TModel>) {
  type TRow = DataTableRowMap[TModel]

  const table = useDataTableQuery({
    model: modelId,
    context,
    refetchInterval,
    enabled,
  })

  const model = table.model as DataTableModel<
    DataTableRowMap[TModel],
    DataTableModelContextMap[TModel]
  >
  const { params, query, pageCount, setSearch, setFilter, toggleSort, setPage, setPageSize } = table

  const rows = (query.data?.rows ?? []) as TRow[]
  const total = query.data?.total ?? 0
  const isLoading = query.isLoading
  const isError = query.isError
  const isEmpty = !isLoading && !isError && total === 0
  const hasActiveFilters =
    params.search.trim().length > 0 ||
    Object.values(params.filters).some((value) => value !== null && value !== '')

  useEffect(() => {
    if (!query.data || !onDataChangeAction) return
    onDataChangeAction(query.data)
  }, [onDataChangeAction, query.data])

  return (
    <div className={cn('overflow-hidden rounded-2xl border border-border bg-card', className)}>
      {showToolbar ? (
        <div className="border-b border-border px-4 py-3">
          <DataTableToolbar
            search={params.search}
            onSearchChange={setSearch}
            searchPlaceholder={model.searchPlaceholder}
            filters={model.filters}
            filterValues={params.filters}
            onFilterChange={setFilter}
            actions={toolbarActions}
          />
        </div>
      ) : null}

      {isError ? (
        <div className="flex flex-col items-center gap-3 py-12">
          <p className="text-sm text-muted-foreground">Could not load data.</p>
          <Button type="button" variant="outline" onClick={() => void query.refetch()}>
            Retry
          </Button>
        </div>
      ) : (
        <>
          <Table>
            <TableHeader>
              <TableRow className="bg-muted/30 hover:bg-muted/30">
                {model.columns.map((column) => (
                  <TableHead
                    key={column.id}
                    className={cn(
                      'text-[0.6875rem] tracking-wider text-muted-foreground uppercase',
                      getResponsiveClass(column.hidden),
                      column.headerClassName
                    )}
                  >
                    {column.sortable ? (
                      <button
                        type="button"
                        onClick={() => toggleSort(column.id)}
                        className="inline-flex items-center gap-1.5 transition-colors hover:text-foreground"
                      >
                        {column.header}
                        <SortIcon
                          active={params.sort?.id === column.id}
                          desc={params.sort?.desc ?? false}
                        />
                      </button>
                    ) : (
                      column.header
                    )}
                  </TableHead>
                ))}
              </TableRow>
            </TableHeader>

            <TableBody>
              {isLoading ? (
                <TableSkeleton columns={model.columns.length} />
              ) : isEmpty ? (
                <TableRow className="hover:bg-transparent">
                  <TableCell colSpan={model.columns.length} className="h-32 text-center">
                    {emptyState && !hasActiveFilters ? (
                      emptyState
                    ) : (
                      <p className="text-sm text-muted-foreground">
                        {model.emptyMessage ?? 'No results.'}
                      </p>
                    )}
                  </TableCell>
                </TableRow>
              ) : (
                rows.map((row) => {
                  const rowId = model.getRowId(row)
                  const selected = selectedRowId === rowId

                  return (
                    <TableRow
                      key={rowId}
                      data-state={selected ? 'selected' : undefined}
                      className={onRowClickAction ? 'cursor-pointer' : undefined}
                      onClick={onRowClickAction ? () => onRowClickAction(row) : undefined}
                    >
                      {model.columns.map((column) => (
                        <TableCell
                          key={column.id}
                          className={cn(getResponsiveClass(column.hidden), column.className)}
                        >
                          {renderCell(row, column as DataTableColumnDef<TRow>, cellRenderers)}
                        </TableCell>
                      ))}
                    </TableRow>
                  )
                })
              )}
            </TableBody>
          </Table>

          {showPagination ? (
            <DataTablePagination
              page={params.page}
              pageCount={pageCount}
              pageSize={params.pageSize}
              total={total}
              pageSizeOptions={model.pageSizeOptions}
              onPageChangeAction={setPage}
              onPageSizeChangeAction={setPageSize}
            />
          ) : null}
        </>
      )}
    </div>
  )
}