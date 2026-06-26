import { Skeleton } from '@workspace/ui/components/skeleton'

function PanelHeaderSkeleton() {
  return (
    <div className="flex items-center justify-between gap-3 border-b border-border px-4 py-3">
      <Skeleton className="h-4 w-24 rounded-md" />
      <Skeleton className="h-7 w-20 rounded-lg" />
    </div>
  )
}

function JsonBlockSkeleton({ lines = 4 }: { lines?: number }) {
  return (
    <div className="space-y-2 rounded-lg border border-border bg-muted/20 p-3">
      {Array.from({ length: lines }).map((_, index) => (
        <Skeleton
          key={index}
          className="h-3 rounded-md"
          style={{ width: `${88 - index * 12}%` }}
        />
      ))}
    </div>
  )
}

export function EventInspectorSkeleton() {
  return (
    <div className="overflow-hidden rounded-xl border border-border bg-card" aria-busy="true">
      <PanelHeaderSkeleton />
      <div className="space-y-5 p-4">
        <div className="flex flex-wrap items-center gap-3">
          <Skeleton className="h-6 w-14 rounded-md" />
          <Skeleton className="h-4 w-40 rounded-md" />
          <Skeleton className="h-4 w-16 rounded-md" />
        </div>
        <div>
          <Skeleton className="mb-2 h-3 w-16 rounded-md" />
          <JsonBlockSkeleton lines={5} />
        </div>
        <div>
          <Skeleton className="mb-2 h-3 w-10 rounded-md" />
          <JsonBlockSkeleton lines={6} />
        </div>
      </div>
    </div>
  )
}

export function ReplayPanelSkeleton() {
  return (
    <div className="overflow-hidden rounded-xl border border-border bg-card" aria-busy="true">
      <div className="space-y-2 border-b border-border px-4 py-3">
        <Skeleton className="h-4 w-16 rounded-md" />
        <Skeleton className="h-3 w-56 max-w-full rounded-md" />
      </div>
      <div className="space-y-4 p-4">
        <Skeleton className="h-9 w-full rounded-lg" />
        <div className="flex gap-2">
          <Skeleton className="h-9 w-16 rounded-lg" />
          <Skeleton className="h-9 w-24 rounded-lg" />
        </div>
      </div>
    </div>
  )
}

function EventsTableSkeleton() {
  return (
    <div className="overflow-hidden rounded-xl border border-border bg-card" aria-busy="true">
      <div className="border-b border-border px-4 py-3">
        <Skeleton className="h-8 w-full max-w-xs rounded-lg" />
      </div>
      <div className="divide-y divide-border">
        {Array.from({ length: 6 }).map((_, index) => (
          <div key={index} className="flex items-center gap-4 px-4 py-3">
            <Skeleton className="h-5 w-12 rounded-md" />
            <Skeleton className="h-4 flex-1 rounded-md" />
            <Skeleton className="hidden h-4 w-14 rounded-md sm:block" />
          </div>
        ))}
      </div>
      <div className="flex items-center justify-between border-t border-border px-4 py-3">
        <Skeleton className="h-4 w-24 rounded-md" />
        <Skeleton className="h-8 w-32 rounded-lg" />
      </div>
    </div>
  )
}

export function InboxDetailSkeleton() {
  return (
    <div className="mx-auto flex w-full max-w-6xl flex-col gap-6" aria-busy="true" aria-label="Loading inbox">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div className="min-w-0 space-y-3">
          <Skeleton className="h-8 w-24 rounded-lg" />
          <Skeleton className="h-8 w-48 max-w-full rounded-lg" />
          <Skeleton className="h-4 w-32 rounded-md" />
        </div>
        <div className="flex gap-2">
          <Skeleton className="h-8 w-20 rounded-lg" />
          <Skeleton className="h-8 w-20 rounded-lg" />
        </div>
      </div>

      <div className="rounded-xl border border-border bg-card p-4 sm:p-5">
        <Skeleton className="mb-3 h-3 w-28 rounded-md" />
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
          <Skeleton className="h-10 flex-1 rounded-lg" />
          <Skeleton className="h-9 w-28 shrink-0 rounded-lg" />
        </div>
      </div>

      <div className="grid gap-4 lg:grid-cols-[minmax(0,18rem)_minmax(0,1fr)] lg:gap-6">
        <section>
          <Skeleton className="mb-3 ml-1 h-4 w-20 rounded-md" />
          <EventsTableSkeleton />
        </section>

        <section className="flex flex-col gap-4">
          <EventInspectorSkeleton />
          <ReplayPanelSkeleton />
        </section>
      </div>
    </div>
  )
}