'use client'

import { IconSearch } from '@tabler/icons-react'
import { Input } from '@workspace/ui/components/input'
import { Label } from '@workspace/ui/components/label'
import type { DataTableFilterDef } from '@/features/data-table/types'
import { cn } from '@workspace/ui/lib/utils'

type DataTableToolbarProps = {
  search: string
  onSearchChange: (value: string) => void
  searchPlaceholder?: string
  filters?: DataTableFilterDef[]
  filterValues: Record<string, string | boolean | null>
  onFilterChange: (id: string, value: string | boolean | null) => void
  className?: string
  actions?: React.ReactNode
}

export function DataTableToolbar({
  search,
  onSearchChange,
  searchPlaceholder = 'Search…',
  filters,
  filterValues,
  onFilterChange,
  className,
  actions,
}: DataTableToolbarProps) {
  const hasFilters = (filters?.length ?? 0) > 0

  if (!hasFilters && !actions) {
    return (
      <div className={cn('relative', className)}>
        <IconSearch
          className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
          aria-hidden
        />
        <Input
          value={search}
          onChange={(event) => onSearchChange(event.target.value)}
          placeholder={searchPlaceholder}
          className="pl-9"
          aria-label="Search table"
        />
      </div>
    )
  }

  return (
    <div className={cn('flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between', className)}>
      <div className="flex flex-1 flex-col gap-3 sm:flex-row sm:items-end">
        <div className="relative min-w-[12rem] flex-1">
          <IconSearch
            className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
            aria-hidden
          />
          <Input
            value={search}
            onChange={(event) => onSearchChange(event.target.value)}
            placeholder={searchPlaceholder}
            className="pl-9"
            aria-label="Search table"
          />
        </div>

        {filters?.map((filter) => (
          <div key={filter.id} className="min-w-[10rem] space-y-1.5">
            <Label htmlFor={`filter-${filter.id}`} className="text-xs text-muted-foreground">
              {filter.label}
            </Label>
            {filter.type === 'select' ? (
              <select
                id={`filter-${filter.id}`}
                value={String(filterValues[filter.id] ?? '')}
                onChange={(event) => onFilterChange(filter.id, event.target.value || null)}
                className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
              >
                {filter.options?.map((option) => (
                  <option key={option.value || 'all'} value={option.value}>
                    {option.label}
                  </option>
                ))}
              </select>
            ) : (
              <Input
                id={`filter-${filter.id}`}
                value={String(filterValues[filter.id] ?? '')}
                onChange={(event) => onFilterChange(filter.id, event.target.value || null)}
                placeholder={filter.placeholder}
              />
            )}
          </div>
        ))}
      </div>

      {actions ? <div className="flex shrink-0 items-center gap-2">{actions}</div> : null}
    </div>
  )
}