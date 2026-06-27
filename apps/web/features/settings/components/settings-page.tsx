'use client'

import { useCallback, useEffect, useState } from 'react'
import { IconBell, IconBuilding, IconShieldLock } from '@tabler/icons-react'
import { useAuth } from '@/contexts/auth-context'
import { TwoFactorSection } from './two-factor-section'

type SectionId = 'account' | 'security' | 'notifications'

interface Section {
  id: SectionId
  label: string
  icon: typeof IconBuilding
}

const sections: Section[] = [
  { id: 'account', label: 'Account', icon: IconBuilding },
  { id: 'security', label: 'Security', icon: IconShieldLock },
  { id: 'notifications', label: 'Notifications', icon: IconBell },
]

function formatDate(dateStr?: string): string {
  if (!dateStr) return '\u2014'
  return new Intl.DateTimeFormat('en', {
    month: 'long',
    year: 'numeric',
  }).format(new Date(dateStr))
}

export function SettingsPage() {
  const { user } = useAuth()
  const [activeSection, setActiveSection] = useState<SectionId>('account')

  const handleNavClick = useCallback((sectionId: SectionId) => {
    setActiveSection(sectionId)
    const el = document.getElementById(sectionId)
    if (el) {
      el.scrollIntoView({ behavior: 'smooth' })
    }
    window.history.replaceState(null, '', `#${sectionId}`)
  }, [])

  useEffect(() => {
    const hash = window.location.hash.replace('#', '') as SectionId
    if (hash && sections.some((s) => s.id === hash)) {
      setActiveSection(hash)
      const el = document.getElementById(hash)
      if (el) {
        setTimeout(() => el.scrollIntoView({ behavior: 'smooth' }), 80)
      }
    }
  }, [])

  useEffect(() => {
    const observer = new IntersectionObserver(
      (entries) => {
        for (const entry of entries) {
          if (entry.isIntersecting) {
            setActiveSection(entry.target.id as SectionId)
          }
        }
      },
      { rootMargin: '-80px 0px -60% 0px' }
    )

    for (const s of sections) {
      const el = document.getElementById(s.id)
      if (el) observer.observe(el)
    }

    return () => observer.disconnect()
  }, [])

  if (!user) return null

  return (
    <div className="mx-auto w-full max-w-5xl">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight text-foreground">Settings</h1>
        <p className="mt-1 text-sm text-muted-foreground">
          Manage your account, security, and notification preferences.
        </p>
      </div>

      <div className="mt-8 flex gap-10 lg:gap-14">
        <nav className="sticky top-24 hidden h-fit w-44 shrink-0 space-y-1 sm:block">
          {sections.map((section) => {
            const Icon = section.icon
            return (
              <button
                key={section.id}
                type="button"
                onClick={() => handleNavClick(section.id)}
                data-active={activeSection === section.id}
                className="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-left text-sm font-medium transition-colors data-[active=true]:bg-secondary data-[active=true]:text-foreground data-[active=false]:text-muted-foreground hover:text-foreground"
              >
                <Icon className="size-4 shrink-0" stroke={1.8} />
                {section.label}
              </button>
            )
          })}
        </nav>

        <div className="min-w-0 flex-1 space-y-8">
          <section
            id="account"
            className="scroll-mt-24 rounded-2xl border border-border bg-card"
          >
            <div className="border-b border-border px-6 py-5">
              <h2 className="flex items-center gap-2 text-sm font-semibold text-foreground">
                <IconBuilding className="size-4" stroke={1.8} />
                Account
              </h2>
              <p className="mt-1 text-sm text-muted-foreground">
                Your account details and profile information.
              </p>
            </div>
            <dl className="grid gap-5 px-6 py-5 sm:grid-cols-2">
              <div>
                <dt className="text-[0.6875rem] font-medium tracking-[0.12em] text-muted-foreground uppercase">
                  Email
                </dt>
                <dd className="mt-1 text-sm text-foreground">{user.email}</dd>
              </div>
              <div>
                <dt className="text-[0.6875rem] font-medium tracking-[0.12em] text-muted-foreground uppercase">
                  Member since
                </dt>
                <dd className="mt-1 text-sm text-foreground">{formatDate(user.createdAt)}</dd>
              </div>
              <div className="sm:col-span-2">
                <dt className="text-[0.6875rem] font-medium tracking-[0.12em] text-muted-foreground uppercase">
                  User ID
                </dt>
                <dd className="mt-1 font-mono text-xs text-foreground">{user.id}</dd>
              </div>
            </dl>
          </section>

          <section id="security" className="scroll-mt-24">
            <TwoFactorSection />
          </section>

          <section
            id="notifications"
            className="scroll-mt-24 rounded-2xl border border-border bg-card"
          >
            <div className="border-b border-border px-6 py-5">
              <h2 className="flex items-center gap-2 text-sm font-semibold text-foreground">
                <IconBell className="size-4" stroke={1.8} />
                Notifications
              </h2>
              <p className="mt-1 text-sm text-muted-foreground">
                Configure how and when you hear from Hookscope.
              </p>
            </div>
            <div className="px-6 py-12 text-center">
              <IconBell className="mx-auto mb-3 size-8 text-muted-foreground" stroke={1.5} />
              <p className="text-sm text-muted-foreground">Coming soon.</p>
            </div>
          </section>
        </div>
      </div>
    </div>
  )
}
