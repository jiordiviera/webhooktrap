"use client"

import * as React from "react"
import { IconDots, IconFolder, IconShare3, IconTrash, type Icon } from "@tabler/icons-react"

import {
  SidebarGroup,
  SidebarGroupContent,
  SidebarMenu,
  SidebarMenuAction,
  SidebarMenuButton,
  SidebarMenuItem,
  useSidebar,
} from '@workspace/ui/components/sidebar'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@workspace/ui/components/dropdown-menu'

export function NavSecondary({
  items,
  ...props
}: {
  items: {
    title: string
    url: string
    icon: Icon
  }[]
  } & React.ComponentPropsWithoutRef<typeof SidebarGroup>) {
    const { isMobile } = useSidebar()

  return (
    <SidebarGroup {...props}>
      <SidebarGroupContent>
        <SidebarMenu>
          {items.map((item) => (
            <SidebarMenuItem key={item.title}>
              <SidebarMenuButton asChild>
                <a href={item.url}>
                  <item.icon />
                  <span>{item.title}</span>
                </a>
              </SidebarMenuButton>
              <DropdownMenu>
                           <DropdownMenuTrigger asChild>
                             <SidebarMenuAction
                               showOnHover
                               className="rounded-sm data-[state=open]:bg-accent"
                             >
                               <IconDots />
                               <span className="sr-only">More</span>
                             </SidebarMenuAction>
                           </DropdownMenuTrigger>
                           <DropdownMenuContent
                             className="w-24 rounded-lg"
                             side={isMobile ? "bottom" : "right"}
                             align={isMobile ? "end" : "start"}
                           >
                             <DropdownMenuItem>
                               <IconFolder />
                               <span>Open</span>
                             </DropdownMenuItem>
                             <DropdownMenuItem>
                               <IconShare3 />
                               <span>Share</span>
                             </DropdownMenuItem>
                             <DropdownMenuSeparator />
                             <DropdownMenuItem variant="destructive">
                               <IconTrash />
                               <span>Delete</span>
                             </DropdownMenuItem>
                           </DropdownMenuContent>
                         </DropdownMenu>
            </SidebarMenuItem>
          ))}
        </SidebarMenu>
      </SidebarGroupContent>
    </SidebarGroup>
  )
}
