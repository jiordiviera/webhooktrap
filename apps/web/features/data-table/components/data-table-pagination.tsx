'use client'

import { IconChevronLeft, IconChevronRight, IconChevronsLeft, IconChevronsRight } from '@tabler/icons-react'
import { Button } from '@workspace/ui/components/button'
import { Label } from '@workspace/ui/components/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@workspace/ui/components/select'
import { DEFAULT_PAGE_SIZE_OPTIONS } from '@/features/data-table/utils'

type DataTablePaginationProps = {
  page: number
  pageCount: number
  pageSize: number
  total: number
  pageSizeOptions?: number[]
  onPageChangeAction: (page: number) => void
  onPageSizeChangeAction: (pageSize: number) => void
  selectedCount?: number
}

export function DataTablePagination({
  page,
  pageCount,
  pageSize,
  total,
  pageSizeOptions = [...DEFAULT_PAGE_SIZE_OPTIONS],
  onPageChangeAction,
  onPageSizeChangeAction,
  selectedCount,
}: DataTablePaginationProps) {
  if (total === 0 && !selectedCount) return null

  const from = total > 0 ? (page - 1) * pageSize + 1 : 0
  const to = Math.min(page * pageSize, total)

  return (
    <div className="flex flex-col gap-3 border-t border-border px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
      <div className="flex flex-wrap items-center gap-4">
        {selectedCount !== undefined && total > 0 ? (
          <p className="text-sm text-muted-foreground">
            {selectedCount} of {total} row(s) selected.
          </p>
        ) : total > 0 ? (
          <p className="text-sm text-muted-foreground">
            {from}–{to} of {total}
          </p>
        ) : null}
      </div>

      <div className="flex flex-wrap items-center gap-3">
        <div className="hidden items-center gap-2 sm:flex">
          <Label htmlFor="rows-per-page" className="text-sm text-muted-foreground">
            Rows
          </Label>
          <Select
            value={`${pageSize}`}
            onValueChange={(value) => onPageSizeChangeAction(Number(value))}
          >
            <SelectTrigger
              id="rows-per-page"
              size="sm"
              className="h-8 w-16"
              aria-label="Rows per page"
            >
              <SelectValue placeholder={pageSize} />
            </SelectTrigger>
            <SelectContent side="top">
              {pageSizeOptions.map((option) => (
                <SelectItem key={option} value={`${option}`}>
                  {option}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        <div className="flex items-center gap-1">
          <Button
            type="button"
            variant="outline"
            size="icon-sm"
            disabled={page <= 1}
            onClick={() => onPageChangeAction(1)}
            aria-label="First page"
            className="hidden sm:flex"
          >
            <IconChevronsLeft className="size-4" aria-hidden />
          </Button>
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
          <Button
            type="button"
            variant="outline"
            size="icon-sm"
            disabled={page >= pageCount}
            onClick={() => onPageChangeAction(pageCount)}
            aria-label="Last page"
            className="hidden sm:flex"
          >
            <IconChevronsRight className="size-4" aria-hidden />
          </Button>
        </div>
      </div>
    </div>
  )
}
