'use client'

import { IconChevronLeft, IconChevronRight } from '@tabler/icons-react'
import { Button } from '@workspace/ui/components/button'
import { DEFAULT_PAGE_SIZE_OPTIONS } from '@/features/data-table/utils'

type DataTablePaginationProps = {
  page: number
  pageCount: number
  pageSize: number
  total: number
  pageSizeOptions?: number[]
  onPageChangeAction: (page: number) => void
  onPageSizeChangeAction: (pageSize: number) => void
}

export function DataTablePagination({
  page,
  pageCount,
  pageSize,
  total,
  pageSizeOptions = [...DEFAULT_PAGE_SIZE_OPTIONS],
  onPageChangeAction,
  onPageSizeChangeAction,
}: DataTablePaginationProps) {
  if (total === 0) return null

  const from = (page - 1) * pageSize + 1
  const to = Math.min(page * pageSize, total)

  return (
    <div className="flex flex-col gap-3 border-t border-border px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
      <p className="text-sm text-muted-foreground">
        {from}–{to} of {total}
      </p>

      <div className="flex flex-wrap items-center gap-3">
        <label className="flex items-center gap-2 text-sm text-muted-foreground">
          Rows
          <select
            value={pageSize}
            onChange={(event) => onPageSizeChangeAction(Number(event.target.value))}
            className="h-8 rounded-md border border-input bg-transparent px-2 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
            aria-label="Rows per page"
          >
            {pageSizeOptions.map((option) => (
              <option key={option} value={option}>
                {option}
              </option>
            ))}
          </select>
        </label>

        <div className="flex items-center gap-1">
          <Button
            type="button"
            variant="outline"
            size="icon-sm"
            disabled={page <= 1}
            onClick={() => onPageChangeAction(page - 1)}
            aria-label="Previous page"
          >
            <IconChevronLeft className="size-4" aria-hidden />
          </Button>
          <span className="min-w-18 text-center text-sm tabular-nums text-foreground">
            {page} / {pageCount}
          </span>
          <Button
            type="button"
            variant="outline"
            size="icon-sm"
            disabled={page >= pageCount}
            onClick={() => onPageChangeAction(page + 1)}
            aria-label="Next page"
          >
            <IconChevronRight className="size-4" aria-hidden />
          </Button>
        </div>
      </div>
    </div>
  )
}