'use client'

import Link from 'next/link'
import { usePathname } from 'next/navigation'
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from '@workspace/ui/components/breadcrumb'
import {
  type DashboardNavItem,
  useDashboardNav,
} from '@/features/dashboard/context/dashboard-nav-context'
import { useInboxPageTitle } from '@/features/inbox/context/inbox-page-context'

type BreadcrumbCrumb = {
  label: string
  href?: string
}

function getBreadcrumbs(
  pathname: string,
  navItems: DashboardNavItem[],
  inboxTitle: string | null | undefined
): BreadcrumbCrumb[] {
  if (pathname.startsWith('/i/')) {
    const inboxId = pathname.slice(3).split('/')[0]
    return [
      { label: 'Inboxes', href: '/inboxes' },
      { label: inboxTitle ?? inboxId ?? 'Inbox' },
    ]
  }

  const activeItem = navItems.find((item) => item.isActive(pathname))
  if (activeItem) {
    return [{ label: activeItem.label }]
  }

  return [{ label: 'Hookscope' }]
}

export function DashboardBreadcrumb() {
  const pathname = usePathname()
  const navItems = useDashboardNav()
  const inboxPage = useInboxPageTitle()
  const crumbs = getBreadcrumbs(pathname, navItems, inboxPage?.title)

  return (
    <Breadcrumb className="w-full max-w-none">
      <BreadcrumbList>
        <BreadcrumbItem>
          <BreadcrumbLink asChild>
            <Link href="/">Home</Link>
          </BreadcrumbLink>
        </BreadcrumbItem>

        {crumbs.map((crumb, index) => {
          const isLast = index === crumbs.length - 1

          return (
            <span key={`${crumb.label}-${index}`} className="contents">
              <BreadcrumbSeparator />
              <BreadcrumbItem>
                {isLast || !crumb.href ? (
                  <BreadcrumbPage>{crumb.label}</BreadcrumbPage>
                ) : (
                  <BreadcrumbLink asChild>
                    <Link href={crumb.href}>{crumb.label}</Link>
                  </BreadcrumbLink>
                )}
              </BreadcrumbItem>
            </span>
          )
        })}
      </BreadcrumbList>
    </Breadcrumb>
  )
}