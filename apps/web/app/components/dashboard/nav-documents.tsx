'use client'

import Link from 'next/link'
import type { TablerIcon } from '@tabler/icons-react'
import {
  SidebarGroup,
  SidebarGroupLabel,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
} from '@workspace/ui/components/sidebar'

type DocumentItem = {
  name: string
  url: string
  icon: TablerIcon
}

export function NavDocuments({ items }: { items: DocumentItem[] }) {
  if (items.length === 0) return null

  return (
    <SidebarGroup>
      <SidebarGroupLabel>Developers</SidebarGroupLabel>
      <SidebarMenu>
        {items.map((item) => (
          <SidebarMenuItem key={item.name}>
            <SidebarMenuButton asChild>
              <Link href={item.url}>
                <item.icon className="size-4" />
                <span>{item.name}</span>
              </Link>
            </SidebarMenuButton>
          </SidebarMenuItem>
        ))}
      </SidebarMenu>
    </SidebarGroup>
  )
}
