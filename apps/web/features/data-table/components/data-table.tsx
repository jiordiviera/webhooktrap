'use client'

import { useCallback, useEffect, useMemo, useState } from 'react'
import { IconArrowDown, IconArrowUp, IconArrowsSort, IconLayoutColumns } from '@tabler/icons-react'
import { Button } from '@workspace/ui/components/button'
import {
  DropdownMenu,
  DropdownMenuCheckboxItem,
  DropdownMenuContent,
  DropdownMenuTrigger,
} from '@workspace/ui/components/dropdown-menu'
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
  enableRowSelection?: boolean
  onSelectedRowsChangeAction?: (selectedIds: string[]) => void
  enableColumnVisibility?: boolean
}

function SortIcon({ active, desc }: { active: boolean; desc: boolean }) {
  if (!active) return <IconArrowsSort className="size-3.5 opacity-40" aria-hidden />
  if (desc) return <IconArrowDown className="size-3.5" aria-hidden />
  return <IconArrowUp className="size-3.5" aria-hidden />
}

function renderCell<T>(
  row: T,
  column: DataTableColumnDef<T>,
  cellRenderers?: DataTableCellRenderers<T>,
) {
  if (cellRenderers?.[column.id]) return cellRenderers[column.id]!(row)
  if (column.cell) return column.cell(row)
  if (column.accessorKey) return String(row[column.accessorKey] ?? '')
  return null
}

function ColumnCheckbox({
  checked,
  onCheckedChange,
  label,
}: {
  checked: boolean
  onCheckedChange: (checked: boolean) => void
  label: string
}) {
  return (
    <input
      type="checkbox"
      checked={checked}
      onChange={(event) => onCheckedChange(event.target.checked)}
      aria-label={label}
      className="size-4 rounded border-border bg-background text-primary ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
    />
  )
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
  enableRowSelection = false,
  onSelectedRowsChangeAction,
  enableColumnVisibility = false,
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

  const [selectedRowIds, setSelectedRowIds] = useState<Set<string>>(new Set())

  const currentPageIds = useMemo(() => new Set(rows.map((r) => model.getRowId(r))), [rows, model])

  const allPageSelected = rows.length > 0 && rows.every((r) => selectedRowIds.has(model.getRowId(r)))
  const somePageSelected = rows.some((r) => selectedRowIds.has(model.getRowId(r)))

  const toggleRowSelection = useCallback(
    (rowId: string) => {
      setSelectedRowIds((prev) => {
        const next = new Set(prev)
        if (next.has(rowId)) next.delete(rowId)
        else next.add(rowId)
        return next
      })
    },
    [],
  )

  const toggleAllPage = useCallback(() => {
    setSelectedRowIds((prev) => {
      const next = new Set(prev)
      if (allPageSelected) {
        for (const id of currentPageIds) next.delete(id)
      } else {
        for (const id of currentPageIds) next.add(id)
      }
      return next
    })
  }, [allPageSelected, currentPageIds])

  useEffect(() => {
    if (!onSelectedRowsChangeAction) return
    onSelectedRowsChangeAction(Array.from(selectedRowIds))
  }, [onSelectedRowsChangeAction, selectedRowIds])

  const [columnVisibility, setColumnVisibility] = useState<Record<string, boolean>>({})

  const visibleColumns = useMemo(
    () =>
      enableColumnVisibility
        ? model.columns.filter((col) => columnVisibility[col.id] !== false)
        : model.columns,
    [model.columns, columnVisibility, enableColumnVisibility],
  )

  const toggleColumnVisibility = useCallback((columnId: string) => {
    setColumnVisibility((prev) => ({ ...prev, [columnId]: prev[columnId] === false ? true : false }))
  }, [])

  const checkboxColumnStyle = 'w-10 px-3 py-2.5'

  const allColumns = useMemo(() => {
    const cols: Array<{ column: DataTableColumnDef<TRow>; type: 'data' | 'checkbox' }> = []
    if (enableRowSelection) {
      cols.push({
        column: {
          id: '__select__',
          header: '',
          className: checkboxColumnStyle,
          headerClassName: checkboxColumnStyle,
        },
        type: 'checkbox',
      })
    }
    for (const col of enableColumnVisibility ? visibleColumns : model.columns) {
      cols.push({ column: col, type: 'data' })
    }
    return cols
  }, [enableRowSelection, enableColumnVisibility, visibleColumns, model.columns])

  const visibleColumnCount = allColumns.length

  return (
    <div className={cn('overflow-hidden rounded-2xl border border-border bg-card', className)}>
      {showToolbar ? (
        <div className="border-b border-border px-4 py-3">
          <div className="flex items-start gap-3">
            <div className="min-w-0 flex-1">
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

            {enableColumnVisibility ? (
              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <Button variant="outline" size="sm" className="shrink-0">
                    <IconLayoutColumns className="size-4" />
                    <span className="hidden sm:inline">Columns</span>
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-44">
                  {model.columns.map((column) => (
                    <DropdownMenuCheckboxItem
                      key={column.id}
                      checked={columnVisibility[column.id] !== false}
                      onCheckedChange={() => toggleColumnVisibility(column.id)}
                    >
                      {column.header}
                    </DropdownMenuCheckboxItem>
                  ))}
                </DropdownMenuContent>
              </DropdownMenu>
            ) : null}
          </div>
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
                {allColumns.map(({ column, type }) => {
                  if (type === 'checkbox') {
                    return (
                      <TableHead key={column.id} className={cn(checkboxColumnStyle)}>
                        <ColumnCheckbox
                          checked={allPageSelected}
                          onCheckedChange={toggleAllPage}
                          label="Select all rows"
                        />
                      </TableHead>
                    )
                  }

                  return (
                    <TableHead
                      key={column.id}
                      className={cn(
                        'text-[0.6875rem] tracking-wider text-muted-foreground uppercase',
                        getResponsiveClass(column.hidden),
                        column.headerClassName,
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
                  )
                })}
              </TableRow>
            </TableHeader>

            <TableBody>
              {isLoading ? (
                <TableSkeleton columns={visibleColumnCount} />
              ) : isEmpty ? (
                <TableRow className="hover:bg-transparent">
                  <TableCell colSpan={visibleColumnCount} className="h-32 text-center">
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
                      className={cn(
                        onRowClickAction ? 'cursor-pointer' : undefined,
                        enableRowSelection && selectedRowIds.has(rowId) ? 'bg-muted/40' : undefined,
                      )}
                      onClick={onRowClickAction ? () => onRowClickAction(row) : undefined}
                    >
                      {allColumns.map(({ column, type }) => {
                        if (type === 'checkbox') {
                          return (
                            <TableCell key={column.id} className={checkboxColumnStyle}>
                              <ColumnCheckbox
                                checked={selectedRowIds.has(rowId)}
                                onCheckedChange={() => toggleRowSelection(rowId)}
                                label={`Select row ${rowId}`}
                              />
                            </TableCell>
                          )
                        }

                        return (
                          <TableCell
                            key={column.id}
                            className={cn(getResponsiveClass(column.hidden), column.className)}
                          >
                            {renderCell(row, column as DataTableColumnDef<TRow>, cellRenderers)}
                          </TableCell>
                        )
                      })}
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
              selectedCount={enableRowSelection ? selectedRowIds.size : undefined}
            />
          ) : null}
        </>
      )}
    </div>
  )
}
