import Link from 'next/link'
import type { DataTableModel } from '@/features/data-table/types'
import { fetchInboxesPage, formatRelativeTime, type InboxSummary } from '@/lib/inboxes'

export const inboxesDataTableModel: DataTableModel<InboxSummary> = {
  id: 'inboxes',
  fetch: ({ token, params }) => fetchInboxesPage(token, params),
  getRowId: (row) => row.id,
  defaultSort: { id: 'lastEventAt', desc: true },
  defaultPageSize: 10,
  searchPlaceholder: 'Search inboxes…',
  searchKeys: ['name', 'id'],
  emptyMessage: 'No inboxes match your filters.',
  columns: [
    {
      id: 'name',
      header: 'Inbox',
      accessorKey: 'name',
      sortable: true,
      className: 'min-w-[12rem] whitespace-normal',
      cell: (inbox) => (
        <>
          <Link
            href={`/i/${inbox.id}`}
            className="text-[0.9375rem] font-medium text-foreground underline-offset-4 transition-colors hover:text-primary hover:underline"
          >
            {inbox.name}
          </Link>
          <p className="mt-0.5 font-mono text-xs text-muted-foreground">/i/{inbox.id}</p>
          <p className="mt-2 text-sm text-muted-foreground md:hidden">
            {inbox.eventsCount} events · {formatRelativeTime(inbox.lastEventAt)}
          </p>
        </>
      ),
    },
    {
      id: 'eventsCount',
      header: 'Events',
      accessorKey: 'eventsCount',
      sortable: true,
      hidden: 'md',
      className: 'text-right tabular-nums',
      headerClassName: 'text-right',
    },
    {
      id: 'lastEventAt',
      header: 'Last seen',
      accessorKey: 'lastEventAt',
      sortable: true,
      sortValue: (inbox) => inbox.lastEventAt ?? '',
      hidden: 'md',
      className: 'text-right text-muted-foreground',
      headerClassName: 'text-right',
      cell: (inbox) => formatRelativeTime(inbox.lastEventAt),
    },
    {
      id: 'ingestUrl',
      header: 'Ingest URL',
      accessorKey: 'ingestUrl',
      hidden: 'md',
      className: 'min-w-[14rem] max-w-xs whitespace-normal',
      cell: (inbox) => (
        <code className="block truncate rounded-md bg-muted/50 px-2 py-1 font-mono text-[0.8125rem] text-foreground/85">
          {inbox.ingestUrl}
        </code>
      ),
    },
    {
      id: 'actions',
      header: 'Actions',
      className: 'text-right',
      headerClassName: 'text-right',
    },
  ],
}