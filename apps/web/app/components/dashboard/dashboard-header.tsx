'use client'

import { useEffect, useState } from 'react'
import Link from 'next/link'
import { useRouter } from 'next/navigation'
import {
  IconChevronRight,
  IconExternalLink,
  IconLogout,
  IconSearch,
  IconUserCircle,
} from '@tabler/icons-react'
import { Avatar, AvatarFallback, AvatarImage } from '@workspace/ui/components/avatar'
import { Button } from '@workspace/ui/components/button'
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@workspace/ui/components/dialog'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@workspace/ui/components/dropdown-menu'
import { Input } from '@workspace/ui/components/input'
import { Kbd, KbdGroup } from '@workspace/ui/components/kbd'
import { SidebarTrigger } from '@workspace/ui/components/sidebar'
import { useAuth } from '@/contexts/auth-context'
import { useDashboardNav } from '@/features/dashboard/context/dashboard-nav-context'
import { DashboardBreadcrumb } from './breadcrumb'

export function DashboardHeader() {
  const router = useRouter()
  const navItems = useDashboardNav()
  const { user, signOut } = useAuth()

  const [searchQuery, setSearchQuery] = useState('')
  const [open, setOpen] = useState(false)
  const [modifierKey, setModifierKey] = useState('Ctrl')

  const allPages = navItems.map((item) => ({
    label: item.label,
    href: item.href,
    icon: item.icon,
  }))

  const filteredPages = searchQuery.trim()
    ? allPages.filter((page) => page.label.toLowerCase().includes(searchQuery.toLowerCase()))
    : allPages

  const handleSelect = (href: string) => {
    setOpen(false)
    setSearchQuery('')
    router.push(href)
  }

  const handleOpenChange = (value: boolean) => {
    setOpen(value)
    if (!value) setSearchQuery('')
  }

  const handleLogout = () => {
    void signOut()
    router.push('/login')
  }

  useEffect(() => {
    setModifierKey(/Mac|iPhone|iPod|iPad/.test(navigator.platform) ? '⌘' : 'Ctrl')
  }, [])

  useEffect(() => {
    const handleKeyDown = (event: KeyboardEvent) => {
      if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault()
        setOpen(true)
      }
    }

    document.addEventListener('keydown', handleKeyDown)
    return () => document.removeEventListener('keydown', handleKeyDown)
  }, [])

  const displayName = user?.fullName ?? user?.email ?? 'Account'
  const avatarSrc = user?.avatar ?? '/logo.png'

  return (
    <>
      <header className="bg-background/80 sticky top-0 z-10 flex w-full items-center justify-between gap-2 border-b px-3 py-3 backdrop-blur-sm sm:px-6 sm:py-4">
        <div className="flex items-center">
          <SidebarTrigger className="focus-visible:ring-ring! hover:bg-accent! hover:text-accent-foreground! text-muted-foreground! inline-flex items-center justify-center rounded-md p-1.5 text-sm font-medium transition-colors duration-100 focus-visible:ring-2 focus-visible:outline-none disabled:pointer-events-none disabled:opacity-50 md:hidden [&_svg]:size-4.5" />
        </div>

        <div className="flex-1 px-2 sm:px-4">
          <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogTrigger asChild>
              <div className="mx-auto flex w-full max-w-xl items-center">
                <button
                  type="button"
                  className="bg-card text-muted-foreground hover:bg-accent flex h-9 w-9 items-center justify-center rounded-md border transition-colors sm:hidden"
                  aria-label="Search pages"
                >
                  <IconSearch className="size-4" stroke={1.8} aria-hidden />
                </button>

                <button
                  type="button"
                  className="bg-card text-muted-foreground hover:bg-accent hidden h-9 w-full items-center gap-2 rounded-md border border-dashed px-3 text-sm transition-colors sm:flex"
                  aria-label="Search pages"
                >
                  <IconSearch className="size-4 shrink-0" stroke={1.8} aria-hidden />
                  <span className="flex-1 text-left">Search pages…</span>
                  <KbdGroup>
                    <Kbd>{modifierKey}</Kbd>
                    <Kbd>K</Kbd>
                  </KbdGroup>
                </button>
              </div>
            </DialogTrigger>

            <DialogContent className="data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-95 data-[state=closed]:slide-out-to-top-1 data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95 data-[state=open]:slide-in-from-top-1 max-w-md gap-0 overflow-hidden p-0 duration-200 ease-out sm:max-w-md">
              <DialogHeader className="sr-only">
                <DialogTitle>Search pages</DialogTitle>
              </DialogHeader>

              <div className="relative border-b">
                <IconSearch
                  className="text-muted-foreground absolute top-1/2 left-4 size-4 -translate-y-1/2"
                  stroke={1.8}
                  aria-hidden
                />
                <Input
                  placeholder="Search pages…"
                  value={searchQuery}
                  onChange={(event) => setSearchQuery(event.target.value)}
                  className="h-12 rounded-none border-0 bg-transparent pl-10 focus-visible:ring-0"
                  autoFocus
                />
              </div>

              <div className="max-h-72 overflow-y-auto py-1" role="listbox">
                {filteredPages.length === 0 ? (
                  <p className="text-muted-foreground px-4 py-8 text-center text-sm">No pages found.</p>
                ) : (
                  filteredPages.map((page) => {
                    const Icon = page.icon

                    return (
                      <button
                        key={page.href}
                        type="button"
                        role="option"
                        aria-selected={false}
                        className="hover:bg-accent flex w-full items-center gap-3 px-4 py-2.5 text-left text-sm transition-colors"
                        onClick={() => handleSelect(page.href)}
                      >
                        <Icon className="text-muted-foreground size-4 shrink-0" stroke={1.8} aria-hidden />
                        {page.label}
                      </button>
                    )
                  })
                )}
              </div>
            </DialogContent>
          </Dialog>
        </div>

        <div className="flex items-center gap-2">
          {user ? (
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button
                  variant="ghost"
                  className="relative h-9 w-9 shrink-0 overflow-hidden rounded-full"
                >
                  <Avatar className="size-9">
                    <AvatarImage src={avatarSrc} alt={displayName} />
                    <AvatarFallback className="text-xs font-semibold">{user.initials}</AvatarFallback>
                  </Avatar>
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent
                className="w-72 rounded-xl p-2"
                side="bottom"
                align="end"
                sideOffset={8}
              >
                <div className="flex items-center gap-3 px-2 py-2.5">
                  <Avatar className="size-10 shrink-0 rounded-lg">
                    <AvatarImage src={avatarSrc} alt={displayName} />
                    <AvatarFallback className="rounded-lg text-sm font-semibold">
                      {user.initials}
                    </AvatarFallback>
                  </Avatar>
                  <div className="flex min-w-0 flex-col gap-0.5 leading-none">
                    <span className="truncate text-sm font-semibold">{displayName}</span>
                    <span className="text-muted-foreground truncate text-xs">{user.email}</span>
                  </div>
                </div>

                <DropdownMenuSeparator className="my-1" />

                <div className="space-y-0.5 px-1 py-1">
                  <DropdownMenuItem asChild className="rounded-lg px-2 py-2">
                    <Link href="/profile">
                      <IconUserCircle className="mr-2.5 size-4" stroke={1.8} aria-hidden />
                      Profile
                      <IconChevronRight className="ml-auto size-3.5 opacity-50" stroke={1.8} />
                    </Link>
                  </DropdownMenuItem>
                  <DropdownMenuItem asChild className="rounded-lg px-2 py-2">
                    <Link href="/">
                      <IconExternalLink className="mr-2.5 size-4" stroke={1.8} aria-hidden />
                      Marketing site
                      <IconChevronRight className="ml-auto size-3.5 opacity-50" stroke={1.8} />
                    </Link>
                  </DropdownMenuItem>
                </div>

                <DropdownMenuSeparator className="my-1" />

                <DropdownMenuItem
                  onSelect={handleLogout}
                  className="text-destructive focus:bg-destructive/10 rounded-lg px-2 py-2"
                >
                  <IconLogout className="mr-2.5 size-4" stroke={1.8} aria-hidden />
                  Sign out
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>
          ) : (
            <Button variant="outline" size="sm" asChild>
              <Link href="/login?returnTo=/dashboard">Sign in</Link>
            </Button>
          )}
        </div>
      </header>

      <div className="bg-background/50 flex justify-around border-b px-4 py-3 sm:px-6">
        <DashboardBreadcrumb />
      </div>
    </>
  )
}