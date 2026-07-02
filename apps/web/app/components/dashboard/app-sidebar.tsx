'use client'

import { useEffect, useMemo } from 'react'
import { usePathname } from 'next/navigation'
import { IconHelp, IconSettings } from '@tabler/icons-react'
import { Sidebar, SidebarContent, SidebarFooter, useSidebar } from '@workspace/ui/components/sidebar'
import { useDashboardNav } from '@/features/dashboard/context/dashboard-nav-context'
import { NavMain } from './nav-main'
import { NavSearch } from './nav-search'
import { NavSecondary } from './nav-secondary'
import { NavUser } from './nav-user'
import { DashboardSidebarHeader } from './sidebar/sidebar-header'

const navSecondary = [
  {
    title: 'Settings',
    url: '/settings',
    icon: IconSettings,
  },
  {
    title: 'Help',
    url: '#',
    icon: IconHelp,
  },
]

export function AppSidebar() {
  const navItems = useDashboardNav()
  const pathname = usePathname()
  const { setOpenMobile } = useSidebar()

  const primaryNav = useMemo(
    () => navItems.filter((item) => item.label === 'Dashboard' || item.label === 'Inboxes'),
    [navItems],
  )

  useEffect(() => {
    setOpenMobile(false)
  }, [pathname, setOpenMobile])

  return (
    <Sidebar variant="floating" collapsible="offcanvas">
      <div className="flex h-full flex-col">
        <DashboardSidebarHeader />

        <div className="px-3 py-2">
          <NavSearch />
        </div>

        <SidebarContent className="flex-1 overflow-hidden px-0">
          <div className="px-3 py-3">
            <NavMain items={primaryNav} />
          </div>

          <NavSecondary items={navSecondary} className="mt-auto" />
        </SidebarContent>

        <SidebarFooter>
          <NavUser />
        </SidebarFooter>
      </div>
    </Sidebar>
  )
}
