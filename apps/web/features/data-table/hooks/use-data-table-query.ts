'use client'

import { useQuery } from '@tanstack/react-query'
import { useCallback, useMemo, useState } from 'react'
import {
  type DataTableModelContextMap,
  type DataTableModelId,
  type DataTableRowMap,
  getDataTableModel,
} from '@/features/data-table/registry'
import type { DataTableModel, DataTableParams, DataTableSort } from '@/features/data-table/types'
import { createInitialParams } from '@/features/data-table/utils'

type UseDataTableQueryOptions<TModel extends DataTableModelId> = {
  model: TModel
  token: string
  context?: DataTableModelContextMap[TModel]
  refetchInterval?: number
  enabled?: boolean
}

export function useDataTableQuery<TModel extends DataTableModelId>({
  model: modelId,
  token,
  context,
  refetchInterval,
  enabled = true,
}: UseDataTableQueryOptions<TModel>) {
  const model = getDataTableModel(modelId) as unknown as DataTableModel<
    DataTableRowMap[TModel],
    DataTableModelContextMap[TModel]
  >
  const resolvedContext = (context ?? undefined) as DataTableModelContextMap[TModel]

  const [params, setParams] = useState<DataTableParams>(() => createInitialParams(model))

  const query = useQuery({
    queryKey: ['data-table', modelId, resolvedContext, params, token],
    enabled: enabled && Boolean(token),
    refetchInterval,
    queryFn: () =>
      model.fetch({
        token,
        context: resolvedContext as never,
        params,
      }),
  })

  const setSearch = useCallback((search: string) => {
    setParams((current) => ({ ...current, search, page: 1 }))
  }, [])

  const setFilter = useCallback((id: string, value: string | boolean | null) => {
    setParams((current) => ({
      ...current,
      filters: { ...current.filters, [id]: value },
      page: 1,
    }))
  }, [])

  const setSort = useCallback((sort: DataTableSort | null) => {
    setParams((current) => ({ ...current, sort, page: 1 }))
  }, [])

  const toggleSort = useCallback((columnId: string) => {
    setParams((current) => {
      if (current.sort?.id === columnId) {
        if (!current.sort.desc) {
          return { ...current, sort: { id: columnId, desc: true }, page: 1 }
        }
        return { ...current, sort: null, page: 1 }
      }

      return { ...current, sort: { id: columnId, desc: false }, page: 1 }
    })
  }, [])

  const setPage = useCallback((page: number) => {
    setParams((current) => ({ ...current, page }))
  }, [])

  const setPageSize = useCallback((pageSize: number) => {
    setParams((current) => ({ ...current, pageSize, page: 1 }))
  }, [])

  const resetParams = useCallback(() => {
    setParams(createInitialParams(model))
  }, [model])

  const pageCount = useMemo(() => {
    const total = query.data?.total ?? 0
    return Math.max(1, Math.ceil(total / params.pageSize))
  }, [params.pageSize, query.data?.total])

  return {
    model,
    params,
    query,
    pageCount,
    setSearch,
    setFilter,
    setSort,
    toggleSort,
    setPage,
    setPageSize,
    resetParams,
  }
}