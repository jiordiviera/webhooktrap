'use client'

import { IconArrowUpRight, IconBook2 } from '@tabler/icons-react'
import { SidebarFooter } from '@workspace/ui/components/sidebar'
import { DeveloperCredit } from '@/app/components/developer-credit'
import { docsUrl } from '@/lib/config'

export function DashboardSidebarFooter() {
  return (
    <SidebarFooter className="p-4">
      <a
        href={docsUrl}
        target="_blank"
        rel="noopener noreferrer"
        className="mb-2 inline-flex items-center gap-2 px-1 text-sm text-muted-foreground transition-colors hover:text-foreground"
      >
        <IconBook2 className="size-4" aria-hidden />
        Docs
        <IconArrowUpRight className="size-3" aria-hidden />
      </a>
      <DeveloperCredit variant="sidebar" className="px-1" />
    </SidebarFooter>
  )
}