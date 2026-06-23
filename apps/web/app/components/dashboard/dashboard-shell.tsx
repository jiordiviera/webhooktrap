'use client'

import Image from 'next/image'
import Link from 'next/link'
import { usePathname } from 'next/navigation'
import { Home, Inbox, LogOut } from 'lucide-react'
import { Loader } from '@workspace/ui/components/loader'
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarGroup,
  SidebarGroupContent,
  SidebarGroupLabel,
  SidebarHeader,
  SidebarInset,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarProvider,
  SidebarRail,
  SidebarSeparator,
  SidebarTrigger,
} from '@workspace/ui/components/sidebar'
import { TooltipProvider } from '@workspace/ui/components/tooltip'
import { cn } from '@workspace/ui/lib/utils'
import { useAuth } from '@/contexts/auth-context'

const NAV = [
  { href: '/dashboard', label: 'Inboxes', icon: Inbox },
  { href: '/', label: 'Home', icon: Home },
] as const

export function DashboardShell({ children }: { children: React.ReactNode }) {
  const pathname = usePathname()
  const { user, status, signOut } = useAuth()

  return (
    <TooltipProvider delayDuration={0}>
      <SidebarProvider defaultOpen>
        <Sidebar collapsible="icon" className="font-ui border-sidebar-border">
          <SidebarHeader className="border-b border-sidebar-border">
            <SidebarMenu>
              <SidebarMenuItem>
                <SidebarMenuButton size="lg" asChild className="hover:bg-transparent active:bg-transparent">
                  <Link href="/" className="gap-3">
                    <span className="flex size-8 shrink-0 items-center justify-center overflow-hidden rounded-md bg-background">
                      <Image
                        src="/logo.png"
                        alt=""
                        width={32}
                        height={32}
                        className="size-7 object-contain"
                        aria-hidden
                      />
                    </span>
                    <span className="truncate font-medium text-sidebar-foreground">Hookscope</span>
                  </Link>
                </SidebarMenuButton>
              </SidebarMenuItem>
            </SidebarMenu>
          </SidebarHeader>

          <SidebarContent>
            <SidebarGroup>
              <SidebarGroupLabel>Workspace</SidebarGroupLabel>
              <SidebarGroupContent>
                <SidebarMenu>
                  {NAV.map((item) => {
                    const Icon = item.icon
                    const isActive =
                      item.href === '/dashboard'
                        ? pathname === '/dashboard' || pathname.startsWith('/i/')
                        : pathname === item.href

                    return (
                      <SidebarMenuItem key={item.href}>
                        <SidebarMenuButton asChild isActive={isActive} tooltip={item.label}>
                          <Link href={item.href}>
                            <Icon />
                            <span>{item.label}</span>
                          </Link>
                        </SidebarMenuButton>
                      </SidebarMenuItem>
                    )
                  })}
                </SidebarMenu>
              </SidebarGroupContent>
            </SidebarGroup>
          </SidebarContent>

          <SidebarFooter className="border-t border-sidebar-border">
            <SidebarMenu>
              {status === 'loading' ? (
                <SidebarMenuItem>
                  <div className="flex h-8 items-center px-2">
                    <Loader size="sm" tone="muted" label="Checking session" />
                  </div>
                </SidebarMenuItem>
              ) : user ? (
                <>
                  <SidebarMenuItem>
                    <div
                      className={cn(
                        'flex min-w-0 items-center gap-2 rounded-md px-2 py-1.5',
                        'group-data-[collapsible=icon]/sidebar-wrapper:justify-center'
                      )}
                    >
                      <span
                        className="flex size-7 shrink-0 items-center justify-center rounded-md bg-sidebar-accent text-xs font-medium text-sidebar-accent-foreground"
                        aria-hidden
                      >
                        {user.initials}
                      </span>
                      <div className="min-w-0 group-data-[collapsible=icon]/sidebar-wrapper:hidden">
                        <p className="truncate text-sm font-medium text-sidebar-foreground">
                          {user.fullName ?? 'Account'}
                        </p>
                        <p className="truncate text-xs text-sidebar-foreground/70">{user.email}</p>
                      </div>
                    </div>
                  </SidebarMenuItem>
                  <SidebarMenuItem>
                    <SidebarMenuButton
                      tooltip="Sign out"
                      onClick={() => void signOut()}
                      className="text-sidebar-foreground/80"
                    >
                      <LogOut />
                      <span>Sign out</span>
                    </SidebarMenuButton>
                  </SidebarMenuItem>
                </>
              ) : (
                <SidebarMenuItem>
                  <SidebarMenuButton asChild tooltip="Sign in">
                    <Link href="/login?returnTo=/dashboard">Sign in</Link>
                  </SidebarMenuButton>
                </SidebarMenuItem>
              )}
            </SidebarMenu>
          </SidebarFooter>

          <SidebarRail />
        </Sidebar>

        <SidebarInset className="font-ui min-h-svh">
          <header className="sticky top-0 z-10 flex h-14 shrink-0 items-center gap-2 border-b border-border bg-background/95 px-4 backdrop-blur-sm supports-[backdrop-filter]:bg-background/80">
            <SidebarTrigger className="-ml-1" />
            <SidebarSeparator orientation="vertical" className="mr-1 h-4 bg-border" />
          </header>

          <main className="flex flex-1 flex-col px-4 py-6 md:px-8 md:py-8">{children}</main>
        </SidebarInset>
      </SidebarProvider>
    </TooltipProvider>
  )
}