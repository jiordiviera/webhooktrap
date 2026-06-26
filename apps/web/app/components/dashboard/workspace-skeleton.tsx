import { Skeleton } from '@workspace/ui/components/skeleton'

const NAV_ITEM_WIDTHS = ['72%', '58%', '64%', '52%'] as const

function SidebarSkeleton() {
  return (
    <>
      <div
        className="relative hidden w-(--sidebar-width) shrink-0 md:block"
        aria-hidden
      />
      <aside className="fixed inset-y-0 left-0 z-10 hidden h-svh w-(--sidebar-width) p-2 md:flex">
        <div className="bg-sidebar flex size-full flex-col rounded-lg shadow-sm ring-1 ring-sidebar-border">
          <div className="flex items-center gap-3 p-4">
            <Skeleton className="size-7 shrink-0 rounded-md" />
            <div className="min-w-0 flex-1 space-y-1.5">
              <Skeleton className="h-3.5 w-24 rounded-md" />
              <Skeleton className="h-2.5 w-32 rounded-md" />
            </div>
          </div>

          <div className="flex-1 space-y-1 px-3 py-3">
            {NAV_ITEM_WIDTHS.map((width, index) => (
              <div key={index} className="flex h-9 items-center gap-2 rounded-md px-2">
                <Skeleton className="size-4 shrink-0 rounded-md" />
                <Skeleton className="h-3.5 rounded-md" style={{ width }} />
              </div>
            ))}
          </div>

          <div className="p-4">
            <Skeleton className="h-8 w-full rounded-md" />
          </div>
        </div>
      </aside>
    </>
  )
}

function HeaderSkeleton() {
  return (
    <>
      <header className="flex w-full items-center justify-between gap-2 border-b px-3 py-3 sm:px-6 sm:py-4">
        <Skeleton className="size-8 shrink-0 rounded-md md:hidden" />
        <Skeleton className="h-9 w-full max-w-md rounded-lg" />
        <Skeleton className="size-8 shrink-0 rounded-full" />
      </header>

      <div className="flex border-b px-4 py-3 sm:px-6">
        <Skeleton className="h-4 w-36 rounded-md" />
      </div>
    </>
  )
}

function MainContentSkeleton() {
  return (
    <main className="custom-scrollbar flex-1 overflow-auto p-4 sm:p-6">
      <div className="mx-auto w-full max-w-5xl">
        <section className="mb-10 flex flex-wrap items-end justify-between gap-6">
          <div className="max-w-2xl space-y-3">
            <Skeleton className="h-10 w-full max-w-lg rounded-lg" />
            <Skeleton className="h-4 w-full max-w-md rounded-md" />
            <Skeleton className="h-4 w-4/5 max-w-sm rounded-md" />
          </div>
          <div className="flex gap-2">
            <Skeleton className="h-9 w-28 rounded-lg" />
            <Skeleton className="h-9 w-32 rounded-lg" />
          </div>
        </section>

        <section className="mb-10 grid gap-4 sm:grid-cols-3">
          <Skeleton className="h-36 rounded-2xl" />
          <Skeleton className="h-36 rounded-2xl" />
          <Skeleton className="h-36 rounded-2xl" />
        </section>

        <section>
          <Skeleton className="mb-4 h-6 w-36 rounded-md" />
          <div className="space-y-3">
            <Skeleton className="h-20 rounded-xl" />
            <Skeleton className="h-20 rounded-xl" />
          </div>
        </section>
      </div>
    </main>
  )
}

export function WorkspaceSkeleton() {
  return (
    <div
      className="bg-background flex h-svh w-full overflow-hidden [--sidebar-width:15.5rem]"
      aria-busy="true"
      aria-label="Loading workspace"
    >
      <SidebarSkeleton />

      <div className="flex min-w-0 flex-1 flex-col overflow-hidden">
        <HeaderSkeleton />
        <MainContentSkeleton />
      </div>
    </div>
  )
}