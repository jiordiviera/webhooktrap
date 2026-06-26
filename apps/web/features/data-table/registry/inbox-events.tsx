import type { DataTableModel } from '@/features/data-table/types'
import { fetchInboxEventsPage, type EventSummary } from '@/lib/events'
import { formatRelativeTime } from '@/lib/inboxes'

export type InboxEventsContext = {
  inboxId: string
}

function formatBytes(bytes: number) {
  if (bytes < 1024) return `${bytes} B`
  return `${(bytes / 1024).toFixed(1)} KB`
}

export const inboxEventsDataTableModel: DataTableModel<EventSummary, InboxEventsContext> = {
  id: 'inbox-events',
  fetch: ({ token, context, params }) =>
    fetchInboxEventsPage(token, context.inboxId, params),
  getRowId: (row) => row.id,
  defaultSort: { id: 'receivedAt', desc: true },
  defaultPageSize: 25,
  pageSizeOptions: [25, 50, 100],
  searchPlaceholder: 'Filter by path…',
  searchKeys: ['path', 'method'],
  emptyMessage: 'No events yet. Send a POST to the ingest URL.',
  filters: [
    {
      id: 'method',
      label: 'Method',
      type: 'select',
      options: [
        { label: 'All methods', value: '' },
        { label: 'GET', value: 'GET' },
        { label: 'POST', value: 'POST' },
        { label: 'PUT', value: 'PUT' },
        { label: 'PATCH', value: 'PATCH' },
        { label: 'DELETE', value: 'DELETE' },
      ],
    },
  ],
  columns: [
    {
      id: 'method',
      header: 'Method',
      accessorKey: 'method',
      sortable: true,
      className: 'px-4 py-2.5',
      cell: (event) => (
        <span className="rounded bg-primary/10 px-1.5 py-0.5 font-mono text-[0.6875rem] font-semibold text-primary">
          {event.method}
        </span>
      ),
    },
    {
      id: 'path',
      header: 'Path',
      accessorKey: 'path',
      sortable: true,
      className: 'max-w-[10rem] truncate px-4 py-2.5 font-mono text-sm sm:max-w-none',
      cell: (event) => (
        <>
          {event.path}
          <span className="mt-0.5 block text-xs text-muted-foreground sm:hidden">
            {formatRelativeTime(event.receivedAt)} · {formatBytes(event.sizeBytes)}
          </span>
        </>
      ),
    },
    {
      id: 'receivedAt',
      header: 'Received',
      accessorKey: 'receivedAt',
      sortable: true,
      hidden: 'sm',
      className: 'px-4 py-2.5 text-right text-sm text-muted-foreground',
      headerClassName: 'text-right',
      cell: (event) => formatRelativeTime(event.receivedAt),
    },
    {
      id: 'sizeBytes',
      header: 'Size',
      accessorKey: 'sizeBytes',
      sortable: true,
      hidden: 'sm',
      className: 'px-4 py-2.5 text-right text-sm tabular-nums text-muted-foreground',
      headerClassName: 'text-right',
      cell: (event) => formatBytes(event.sizeBytes),
    },
  ],
}