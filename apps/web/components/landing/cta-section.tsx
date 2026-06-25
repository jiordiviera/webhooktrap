'use client'

import Link from 'next/link'
import { useEffect, useRef, useState } from 'react'
import { IconArrowRight } from '@tabler/icons-react'
import { Button } from '@workspace/ui/components/button'
import { CreateInboxCta } from '@/app/components/create-inbox-cta'

export function CtaSection() {
  const [isVisible, setIsVisible] = useState(false)
  const sectionRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    const observer = new IntersectionObserver(
      ([entry]) => {
        if (entry?.isIntersecting) setIsVisible(true)
      },
      { threshold: 0.2 }
    )
    if (sectionRef.current) observer.observe(sectionRef.current)
    return () => observer.disconnect()
  }, [])

  return (
    <section id="cta" ref={sectionRef} className="relative scroll-mt-24 py-24 lg:py-32">
      <div className="mx-auto max-w-[1400px] px-6 lg:px-12">
        <div
          className={`relative border border-border bg-card transition-all duration-1000 ${
            isVisible ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0'
          }`}
        >
          <div className="relative z-10 px-8 py-16 lg:px-16 lg:py-24">
            <div className="max-w-3xl">
              <h2 className="font-heading mb-8 text-4xl leading-[0.95] tracking-tight lg:text-7xl">
                Your next webhook
                <br />
                is already on its way.
              </h2>
              <p className="mb-10 max-w-xl text-xl leading-relaxed text-muted-foreground">
                Open an inbox in seconds. Point your provider at the ingest URL. Inspect, replay,
                and see the response.
              </p>
              <CreateInboxCta showSecondaryLink={false} />
              <div className="mt-6 flex flex-wrap items-center gap-4">
                <Button variant="outline" className="rounded-full" asChild>
                  <Link href="/login">
                    Sign in to save inboxes
                    <IconArrowRight className="ml-2 size-4" />
                  </Link>
                </Button>
                <p className="font-ui text-sm text-muted-foreground">Anonymous inboxes last 48 hours</p>
              </div>
            </div>
          </div>
          <div className="absolute top-0 right-0 h-32 w-32 border-b border-l border-border/60" />
          <div className="absolute bottom-0 left-0 h-32 w-32 border-t border-r border-border/60" />
        </div>
      </div>
    </section>
  )
}