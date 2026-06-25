'use client'

import { createContext, useContext, useMemo } from 'react'
import { IconInbox, IconLayoutDashboard, TablerIcon } from '@tabler/icons-react'

export type DashboardNavItem = {
  href: string
  label: string
  hint: string
  icon: TablerIcon
  isActive: (pathname: string) => boolean
}

const DEFAULT_NAV: DashboardNavItem[] = [
  {
    href: '/dashboard',
    label: 'Dashboard',
    hint: 'Activity and quick actions',
    icon: IconLayoutDashboard,
    isActive: (pathname) => pathname === '/dashboard',
  },
  {
    href: '/inboxes',
    label: 'Inboxes',
    hint: 'Ingest URLs and events',
    icon: IconInbox,
    isActive: (pathname) => pathname === '/inboxes' || pathname.startsWith('/i/'),
  },
]

const DashboardNavContext = createContext<DashboardNavItem[]>(DEFAULT_NAV)

export function DashboardNavProvider({ children }: { children: React.ReactNode }) {
  const items = useMemo(() => DEFAULT_NAV, [])

  return <DashboardNavContext.Provider value={items}>{children}</DashboardNavContext.Provider>
}

export function useDashboardNav() {
  return useContext(DashboardNavContext)
}