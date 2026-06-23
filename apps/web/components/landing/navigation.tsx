'use client'

import Image from 'next/image'
import Link from 'next/link'
import { useEffect, useState } from 'react'
import { Menu, X } from 'lucide-react'
import { Button } from '@workspace/ui/components/button'
import { AuthNav } from '@/app/components/auth-nav'

const navLinks = [
  { name: 'Features', href: '#features' },
  { name: 'How it works', href: '#how-it-works' },
  { name: 'Integrations', href: '#integrations' },
  { name: 'Developers', href: '#developers' },
  { name: 'Pricing', href: '#pricing' },
]

export function Navigation() {
  const [isScrolled, setIsScrolled] = useState(false)
  const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false)

  useEffect(() => {
    const handleScroll = () => setIsScrolled(window.scrollY > 20)
    window.addEventListener('scroll', handleScroll)
    return () => window.removeEventListener('scroll', handleScroll)
  }, [])

  return (
    <header
      className={`fixed z-50 transition-all duration-500 ${
        isScrolled ? 'top-4 right-4 left-4' : 'top-0 right-0 left-0'
      }`}
    >
      <nav
        className={`mx-auto transition-all duration-500 ${
          isScrolled || isMobileMenuOpen
            ? 'max-w-[1200px] rounded-2xl border border-border/80 bg-background/90 shadow-[0_8px_32px_oklch(0.35_0.04_48/0.08)] backdrop-blur-xl'
            : 'max-w-[1400px] bg-transparent'
        }`}
      >
        <div
          className={`flex items-center justify-between px-6 transition-all duration-500 lg:px-8 ${
            isScrolled ? 'h-14' : 'h-20'
          }`}
        >
          <Link href="/" className="flex items-center gap-2.5">
            <Image
              src="/logo.png"
              alt="Hookscope"
              width={120}
              height={32}
              className={`h-auto w-auto transition-all duration-500 ${isScrolled ? 'max-h-7' : 'max-h-8'}`}
              priority
            />
          </Link>

          <div className="hidden items-center gap-10 md:flex">
            {navLinks.map((link) => (
              <a
                key={link.name}
                href={link.href}
                className="font-ui group relative text-sm text-foreground/70 transition-colors duration-300 hover:text-primary"
              >
                {link.name}
                <span className="absolute -bottom-1 left-0 h-px w-0 bg-primary transition-all duration-300 group-hover:w-full" />
              </a>
            ))}
          </div>

          <div className="hidden items-center gap-4 md:flex">
            <AuthNav />
            <Button
              size="sm"
              className={`rounded-full transition-all duration-500 ${isScrolled ? 'h-8 px-4 text-xs' : 'px-6'}`}
              asChild
            >
              <a href="#cta">Open inbox</a>
            </Button>
          </div>

          <button
            type="button"
            onClick={() => setIsMobileMenuOpen(!isMobileMenuOpen)}
            className="p-2 md:hidden"
            aria-label="Toggle menu"
          >
            {isMobileMenuOpen ? <X className="size-6" /> : <Menu className="size-6" />}
          </button>
        </div>
      </nav>

      <div
        className={`fixed inset-0 z-40 bg-background transition-all duration-500 md:hidden ${
          isMobileMenuOpen ? 'pointer-events-auto opacity-100' : 'pointer-events-none opacity-0'
        }`}
      >
        <div className="flex h-full flex-col px-8 pt-28 pb-8">
          <div className="flex flex-1 flex-col justify-center gap-8">
            {navLinks.map((link, i) => (
              <a
                key={link.name}
                href={link.href}
                onClick={() => setIsMobileMenuOpen(false)}
                className={`font-display text-5xl text-foreground transition-all duration-500 hover:text-primary ${
                  isMobileMenuOpen ? 'translate-y-0 opacity-100' : 'translate-y-4 opacity-0'
                }`}
                style={{ transitionDelay: isMobileMenuOpen ? `${i * 75}ms` : '0ms' }}
              >
                {link.name}
              </a>
            ))}
          </div>

          <div
            className={`flex flex-col gap-4 border-t border-border pt-8 transition-all duration-500 ${
              isMobileMenuOpen ? 'translate-y-0 opacity-100' : 'translate-y-4 opacity-0'
            }`}
            style={{ transitionDelay: isMobileMenuOpen ? '300ms' : '0ms' }}
          >
            <AuthNav />
            <Button className="h-14 rounded-full text-base" asChild>
              <a href="#cta" onClick={() => setIsMobileMenuOpen(false)}>
                Open inbox
              </a>
            </Button>
          </div>
        </div>
      </div>
    </header>
  )
}