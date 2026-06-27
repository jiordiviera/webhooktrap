'use client'

import { useState } from 'react'
import { useRouter } from 'next/navigation'
import { IconSearch } from '@tabler/icons-react'
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from '@workspace/ui/components/dialog'
import { Button } from '@workspace/ui/components/button'
import { Input } from '@workspace/ui/components/input'
import { useDashboardNav } from '@/features/dashboard/context/dashboard-nav-context'

export function MobileSearchFab() {
  const router = useRouter()
  const navItems = useDashboardNav()

  const [open, setOpen] = useState(false)
  const [searchQuery, setSearchQuery] = useState('')

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

  return (
    <>
      <Button
        type="button"
        variant="default"
        size="icon"
        className="fixed right-4 bottom-4 z-50 size-12 rounded-full shadow-lg md:hidden"
        onClick={() => setOpen(true)}
        aria-label="Search pages"
      >
        <IconSearch className="size-5" stroke={1.8} />
      </Button>

      <Dialog open={open} onOpenChange={handleOpenChange}>
        <DialogContent className="max-w-md gap-0 overflow-hidden p-0 duration-200 ease-out sm:max-w-md">
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
    </>
  )
}
