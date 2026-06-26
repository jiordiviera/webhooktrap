import { Skeleton } from '@workspace/ui/components/skeleton'

export function WorkspaceSkeleton() {
  return (
    <div className="mx-auto w-full max-w-6xl" aria-busy="true" aria-label="Loading workspace">
      <div className="mb-8 flex flex-wrap items-start justify-between gap-4">
        <div className="min-w-0 space-y-3">
          <Skeleton className="h-3 w-28 rounded-md" />
          <Skeleton className="h-9 w-56 max-w-full rounded-lg" />
          <Skeleton className="h-4 w-72 max-w-full rounded-md" />
        </div>
        <Skeleton className="h-9 w-32 shrink-0 rounded-lg" />
      </div>

      <Skeleton className="mb-8 h-24 w-full rounded-xl" />

      <div className="grid gap-4 lg:grid-cols-[minmax(0,18rem)_minmax(0,1fr)] lg:gap-6">
        <div>
          <Skeleton className="mb-3 h-4 w-20 rounded-md" />
          <div className="overflow-hidden rounded-xl border border-border bg-card">
            <div className="border-b border-border px-4 py-3">
              <Skeleton className="h-8 w-full max-w-xs rounded-lg" />
            </div>
            <div className="divide-y divide-border">
              {Array.from({ length: 5 }).map((_, index) => (
                <div key={index} className="flex items-center gap-4 px-4 py-3">
                  <Skeleton className="h-5 w-12 rounded-md" />
                  <Skeleton className="h-4 flex-1 rounded-md" />
                  <Skeleton className="hidden h-4 w-14 rounded-md sm:block" />
                </div>
              ))}
            </div>
          </div>
        </div>

        <div className="space-y-4">
          <div className="overflow-hidden rounded-xl border border-border bg-card">
            <div className="border-b border-border px-4 py-3">
              <Skeleton className="h-4 w-24 rounded-md" />
            </div>
            <div className="space-y-3 p-4">
              <Skeleton className="h-4 w-full rounded-md" />
              <Skeleton className="h-32 w-full rounded-lg" />
              <Skeleton className="h-32 w-full rounded-lg" />
            </div>
          </div>

          <div className="overflow-hidden rounded-xl border border-border bg-card">
            <div className="space-y-2 border-b border-border px-4 py-3">
              <Skeleton className="h-4 w-16 rounded-md" />
              <Skeleton className="h-3 w-48 rounded-md" />
            </div>
            <div className="space-y-3 p-4">
              <Skeleton className="h-9 w-full rounded-lg" />
              <div className="flex gap-2">
                <Skeleton className="h-9 w-16 rounded-lg" />
                <Skeleton className="h-9 w-24 rounded-lg" />
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}