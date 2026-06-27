'use client'

import { SidebarTrigger } from '@workspace/ui/components/sidebar'
import { ThemeToggle } from '@/app/components/theme-toggle'
import { DashboardBreadcrumb } from './breadcrumb'

export function DashboardHeader() {
  return (
    <>
      <header className="bg-background/80 sticky top-0 z-10 flex w-full items-center justify-between border-b px-3 py-2 backdrop-blur-sm sm:px-6 sm:py-3">
        <SidebarTrigger />
        <div className="ml-auto">
          <ThemeToggle />
        </div>
      </header>

      <div className="bg-background/50 flex justify-around border-b px-4 py-3 sm:px-6">
        <DashboardBreadcrumb />
      </div>
    </>
  )
}
