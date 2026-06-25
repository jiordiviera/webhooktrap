'use client'

import Link from 'next/link'
import { usePathname } from 'next/navigation'
import { TablerIcon } from '@tabler/icons-react'
import {
  SidebarGroup,
  SidebarGroupContent,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
} from '@workspace/ui/components/sidebar'
import { cn } from '@workspace/ui/lib/utils'
import type { DashboardNavItem } from '@/features/dashboard/context/dashboard-nav-context'

function renderIcon(icon: TablerIcon) {
  const Icon = icon
  return <Icon className="size-4 shrink-0" stroke={1.8} />
}

export function NavMain({ items }: { items: DashboardNavItem[] }) {
  const pathname = usePathname()

  return (
    <SidebarGroup className="p-0">
      <SidebarGroupContent>
        <SidebarMenu className="gap-1.5">
          {items.map((item) => (
            <SidebarMenuItem key={item.href}>
              <SidebarMenuButton
                id={`nav-main-button-${item.label.toLowerCase().replace(/\s+/g, '-')}`}
                tooltip={item.label}
                isActive={item.isActive(pathname)}
                className={cn(
                  'relative my-0.5 h-auto rounded-lg p-0 text-sm text-sidebar-foreground/70 transition-colors',
                  'hover:bg-sidebar-accent/50 hover:text-sidebar-accent-foreground/80 hover:transition-none',
                  'data-[active=true]:bg-primary/10 data-[active=true]:text-primary data-[active=true]:hover:transition-colors'
                )}
                asChild
              >
                <Link
                  href={item.href}
                  className={cn(
                    'flex items-center justify-between gap-2 rounded-lg px-3 py-2 text-start wrap-anywhere',
                    'data-[active=true]:before:absolute data-[active=true]:before:inset-y-2.5 data-[active=true]:before:inset-s-2.5 data-[active=true]:before:w-px'
                  )}
                >
                  <div className="relative flex items-center justify-start gap-2.5">
                    {renderIcon(item.icon)}
                    <span className="line-clamp-1">{item.label}</span>
                  </div>
                </Link>
              </SidebarMenuButton>
            </SidebarMenuItem>
          ))}
        </SidebarMenu>
      </SidebarGroupContent>
    </SidebarGroup>
  )
}