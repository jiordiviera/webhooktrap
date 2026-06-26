import type { EventSummary } from '@/lib/events'
import type { InboxSummary } from '@/lib/inboxes'
import { inboxEventsDataTableModel } from '@/features/data-table/registry/inbox-events'
import { inboxesDataTableModel } from '@/features/data-table/registry/inboxes'

export const dataTableRegistry = {
  inboxes: inboxesDataTableModel,
  'inbox-events': inboxEventsDataTableModel,
} as const

export type DataTableModelId = keyof typeof dataTableRegistry

export type DataTableModelContextMap = {
  inboxes: void
  'inbox-events': { inboxId: string }
}

export type DataTableRowMap = {
  inboxes: InboxSummary
  'inbox-events': EventSummary
}

export function getDataTableModel<TModel extends DataTableModelId>(modelId: TModel) {
  return dataTableRegistry[modelId]
}