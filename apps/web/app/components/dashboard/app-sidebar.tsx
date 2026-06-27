'use client'

import { useEffect } from 'react'
import { usePathname } from 'next/navigation'
import { ScrollArea } from '@workspace/ui/components/scroll-area'
import { Sidebar, SidebarContent, useSidebar } from '@workspace/ui/components/sidebar'
import { useDashboardNav } from '@/features/dashboard/context/dashboard-nav-context'
import { NavMain } from './nav-main'
import { DashboardSidebarFooter } from './sidebar/sidebar-footer'
import { DashboardSidebarHeader } from './sidebar/sidebar-header'

export function AppSidebar() {
  const navItems = useDashboardNav()
  const pathname = usePathname()
  const { setOpenMobile } = useSidebar()

  useEffect(() => {
    setOpenMobile(false)
  }, [pathname, setOpenMobile])

  return (
    <Sidebar
      variant="floating"
      collapsible="offcanvas"
      // className="h-full px-0 [--sidebar-width:15.5rem] **:data-[slot=sidebar-inner]:h-full"
    >
      <div className="flex h-full flex-col">
        <DashboardSidebarHeader />

        <SidebarContent className="flex-1 overflow-hidden px-0">
          <ScrollArea className="h-full">
            <div className="px-3 py-3">
              <NavMain items={navItems} />
            </div>
          </ScrollArea>
        </SidebarContent>

        <div className="shrink-0">
          <DashboardSidebarFooter />
        </div>
      </div>
    </Sidebar>
  )
}