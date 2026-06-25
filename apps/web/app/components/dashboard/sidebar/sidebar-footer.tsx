'use client'

import { SidebarFooter } from '@workspace/ui/components/sidebar'
import { DeveloperCredit } from '@/app/components/developer-credit'

export function DashboardSidebarFooter() {
  return (
    <SidebarFooter className="p-4">
      <DeveloperCredit variant="sidebar" className="px-1" />
    </SidebarFooter>
  )
}