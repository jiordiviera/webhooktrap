'use client'

import Image from 'next/image'
import Link from 'next/link'
import { usePathname } from 'next/navigation'
import { IconExternalLink, IconInbox, IconLayoutDashboard, IconLogout } from '@tabler/icons-react'
import { Loader } from '@workspace/ui/components/loader'
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarGroup,
  SidebarGroupContent,
  SidebarHeader,
  SidebarInset,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarProvider,
  SidebarTrigger,
} from '@workspace/ui/components/sidebar'
import { TooltipProvider } from '@workspace/ui/components/tooltip'
import { cn } from '@workspace/ui/lib/utils'
import { DeveloperCredit } from '@/app/components/developer-credit'
import { useAuth } from '@/contexts/auth-context'

const NAV = [
  {
    href: '/dashboard',
    label: 'Dashboard',
    hint: 'Activity and quick actions',
    icon: IconLayoutDashboard,
    isActive: (pathname: string) => pathname === '/dashboard',
  },
  {
    href: '/inboxes',
    label: 'Inboxes',
    hint: 'Ingest URLs and events',
    icon: IconInbox,
    isActive: (pathname: string) => pathname === '/inboxes' || pathname.startsWith('/i/'),
  },
] as const

export function DashboardShell({ children }: { children: React.ReactNode }) {
  const pathname = usePathname()
  const { user, status, signOut } = useAuth()

  return (
    <TooltipProvider delayDuration={0}>
      <SidebarProvider
        defaultOpen
        className="bg-[radial-gradient(ellipse_at_top_left,oklch(0.94_0.03_72),transparent_55%)]"
      >
        <Sidebar
          variant="floating"
          collapsible="offcanvas"
          className="font-ui [--sidebar-width:15.5rem]"
        >
          <SidebarHeader className="gap-3 p-4">
            <Link href="/" className="flex items-center gap-3 rounded-xl p-1 transition-opacity hover:opacity-90">
              <Image
                  src="/logo.png"
                  alt=""
                  width={36}
                  height={36}
                  className="size-7 object-contain brightness-110"
                aria-hidden
              />
              <span className="min-w-0">
                <span className="block truncate text-sm font-semibold text-sidebar-foreground">
                  Hookscope
                </span>
                <span className="block truncate text-[0.6875rem] text-sidebar-foreground/65">
                  Inspect · Replay · Respond
                </span>
              </span>
            </Link>
          </SidebarHeader>

          <SidebarContent className="px-2">
            <SidebarGroup className="p-0">
              <SidebarGroupContent>
                <SidebarMenu className="gap-1.5">
                  {NAV.map((item) => {
                    const Icon = item.icon
                    const active = item.isActive(pathname)

                    return (
                      <SidebarMenuItem key={item.href}>
                        <SidebarMenuButton
                          asChild
                          isActive={active}
                          className={cn(
                            'h-auto flex-col items-start gap-0.5 rounded-xl px-3 py-2.5',
                            'data-[active=true]:bg-sidebar-accent data-[active=true]:text-sidebar-accent-foreground',
                            'hover:bg-sidebar-accent/70'
                          )}
                        >
                          <Link href={item.href}>
                            <span className="flex w-full items-center gap-2">
                              <Icon className="size-4 shrink-0 opacity-90" />
                              <span className="text-sm font-medium">{item.label}</span>
                            </span>
                            <span className="pl-6 text-[0.6875rem] leading-snug text-sidebar-foreground/55 group-data-[active=true]/menu-button:text-sidebar-accent-foreground/75">
                              {item.hint}
                            </span>
                          </Link>
                        </SidebarMenuButton>
                      </SidebarMenuItem>
                    )
                  })}
                </SidebarMenu>
              </SidebarGroupContent>
            </SidebarGroup>
          </SidebarContent>

          <SidebarFooter className="gap-3 p-4">
            <SidebarMenu>
              <SidebarMenuItem>
                <SidebarMenuButton
                  asChild
                  className="rounded-xl text-sidebar-foreground/75 hover:bg-sidebar-accent/70 hover:text-sidebar-foreground"
                >
                  <Link href="/">
                    <IconExternalLink className="size-4" />
                    <span>Marketing site</span>
                  </Link>
                </SidebarMenuButton>
              </SidebarMenuItem>

              {status === 'loading' ? (
                <SidebarMenuItem>
                  <div className="flex h-10 items-center px-2">
                    <Loader size="sm" tone="muted" label="Checking session" />
                  </div>
                </SidebarMenuItem>
              ) : user ? (
                <>
                  <SidebarMenuItem>
                    <div className="flex min-w-0 items-center gap-2.5 rounded-xl bg-sidebar-accent/50 px-3 py-2.5 ring-1 ring-sidebar-border">
                      <span
                        className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-sidebar-primary text-xs font-semibold text-sidebar-primary-foreground"
                        aria-hidden
                      >
                        {user.initials}
                      </span>
                      <div className="min-w-0">
                        <p className="truncate text-sm font-medium text-sidebar-foreground">
                          {user.fullName ?? 'Account'}
                        </p>
                        <p className="truncate text-[0.6875rem] text-sidebar-foreground/60">
                          {user.email}
                        </p>
                      </div>
                    </div>
                  </SidebarMenuItem>
                  <SidebarMenuItem>
                    <SidebarMenuButton
                      onClick={() => void signOut()}
                      className="rounded-xl text-sidebar-foreground/75 hover:bg-sidebar-accent/70"
                    >
                      <IconLogout className="size-4" />
                      <span>Sign out</span>
                    </SidebarMenuButton>
                  </SidebarMenuItem>
                </>
              ) : (
                <SidebarMenuItem>
                  <SidebarMenuButton
                    asChild
                    className="rounded-xl bg-sidebar-primary text-sidebar-primary-foreground hover:bg-sidebar-primary/90 hover:text-sidebar-primary-foreground"
                  >
                    <Link href="/login?returnTo=/dashboard">Sign in</Link>
                  </SidebarMenuButton>
                </SidebarMenuItem>
              )}
            </SidebarMenu>
            <DeveloperCredit variant="sidebar" className="px-1" />
          </SidebarFooter>
        </Sidebar>

        <SidebarInset className="font-ui min-h-svh md:m-2 md:ml-0 md:rounded-2xl md:shadow-[0_8px_40px_oklch(0.35_0.04_48/0.06)] md:ring-1 md:ring-border/80">
          <header className="flex h-14 shrink-0 items-center gap-3 border-b border-border/80 px-4 md:rounded-t-2xl md:px-6">
            <SidebarTrigger className="text-foreground md:hidden" />
            <p className="text-sm text-muted-foreground">
              {pathname === '/dashboard'
                ? 'Overview'
                : pathname === '/inboxes' || pathname.startsWith('/i/')
                  ? 'Inbox workspace'
                  : 'Hookscope'}
            </p>
          </header>

          <div className="flex flex-1 flex-col px-4 py-6 md:px-8 md:py-8">{children}</div>
        </SidebarInset>
      </SidebarProvider>
    </TooltipProvider>
  )
}