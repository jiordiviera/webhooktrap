'use client'

import Image from 'next/image'
import Link from 'next/link'
import { SidebarHeader } from '@workspace/ui/components/sidebar'

export function DashboardSidebarHeader() {
  return (
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
          <span className="block truncate text-sm font-semibold text-sidebar-foreground">Hookscope</span>
          <span className="block truncate text-[0.6875rem] text-sidebar-foreground/65">
            Inspect · Replay · Respond
          </span>
        </span>
      </Link>
    </SidebarHeader>
  )
}