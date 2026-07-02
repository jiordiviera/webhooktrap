'use client'

import { useEffect, useRef, useState } from 'react'
import { IconCheck, IconCopy } from '@tabler/icons-react'
import { apiUrl, siteUrl } from '@/lib/config'

const apiOrigin = apiUrl.replace(/\/$/, '')
const webOrigin = siteUrl.replace(/\/$/, '')

const codeExamples = [
  {
    label: 'Create inbox',
    code: `curl -X POST ${apiOrigin}/api/v1/inboxes

{
  "data": {
    "inbox": {
      "id": "xK9m2pQ7nR4a",
      "ingestUrl": "/i/xK9m2pQ7nR4a"
    }
  }
}`,
  },
  {
    label: 'Send webhook',
    code: `curl -X POST ${webOrigin}/i/xK9m2pQ7nR4a \\
  -H "Content-Type: application/json" \\
  -H "X-Provider: stripe" \\
  -d @payload.json`,
  },
  {
    label: 'Replay',
    code: `curl -X POST ${apiOrigin}/api/v1/events/evt_8f2a/replay \\
  -H "Authorization: Bearer <token>" \\
  -d '{"destination":"http://localhost:7777/webhooks"}'`,
  },
]

const features = [
  { title: 'REST API', description: 'Create inboxes, list events, trigger replays.' },
  { title: 'Anonymous start', description: 'No account required for the first capture.' },
  { title: 'curl-first', description: 'Every flow works from the terminal.' },
  { title: 'Open responses', description: 'Status, headers, and body on every replay.' },
]

export function DevelopersSection() {
  const [activeTab, setActiveTab] = useState(0)
  const [copied, setCopied] = useState(false)
  const [isVisible, setIsVisible] = useState(false)
  const sectionRef = useRef<HTMLElement>(null)

  useEffect(() => {
    const observer = new IntersectionObserver(
      ([entry]) => {
        if (entry?.isIntersecting) setIsVisible(true)
      },
      { threshold: 0.1 }
    )
    if (sectionRef.current) observer.observe(sectionRef.current)
    return () => observer.disconnect()
  }, [])

  async function handleCopy() {
    const example = codeExamples[activeTab]
    if (!example) return
    await navigator.clipboard.writeText(example.code)
    setCopied(true)
    window.setTimeout(() => setCopied(false), 2000)
  }

  return (
    <section id="developers" ref={sectionRef} className="relative scroll-mt-24 py-16 lg:py-32">
      <div className="mx-auto max-w-[1400px] px-6 lg:px-12">
        <div className="grid gap-16 lg:grid-cols-2 lg:gap-24">
          <div
            className={`transition-all duration-700 ${
              isVisible ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0'
            }`}
          >
            <span className="mb-6 inline-flex items-center gap-3 text-sm text-muted-foreground">
              <span className="h-px w-8 bg-primary/40" />
              Developers
            </span>
            <h2 className="font-heading mb-6 text-3xl tracking-tight sm:mb-8 sm:text-4xl lg:text-6xl">
              Terminal-friendly
              <br className="hidden sm:inline" />
              <span className="text-muted-foreground/55"> from minute one.</span>
            </h2>
            <p className="mb-8 text-base leading-relaxed text-muted-foreground sm:mb-10 sm:text-xl">
              Earned familiarity for backend devs: predictable URLs, honest JSON, copy-paste cURL.
            </p>
            <div className="grid gap-3 sm:grid-cols-2 sm:gap-4">
              {features.map((feature) => (
                <div key={feature.title} className="border border-border p-3 sm:p-4">
                  <p className="mb-1 text-sm font-medium text-foreground sm:text-base">{feature.title}</p>
                  <p className="text-xs text-muted-foreground sm:text-sm">{feature.description}</p>
                </div>
              ))}
            </div>
          </div>

          <div
            className={`transition-all delay-150 duration-700 ${
              isVisible ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0'
            }`}
          >
            <div className="flex gap-2 overflow-x-auto border-b border-border">
              {codeExamples.map((example, index) => (
                <button
                  key={example.label}
                  type="button"
                  onClick={() => setActiveTab(index)}
                  className={`shrink-0 px-3 py-2.5 text-xs transition-colors sm:px-4 sm:py-3 sm:text-sm ${
                    activeTab === index
                      ? 'border-b-2 border-primary text-foreground'
                      : 'text-muted-foreground hover:text-foreground'
                  }`}
                >
                  {example.label}
                </button>
              ))}
            </div>
            <div className="relative border border-t-0 border-border bg-card">
              <button
                type="button"
                onClick={() => void handleCopy()}
                className="absolute top-3 right-3 inline-flex items-center gap-1.5 rounded-md border border-border px-2 py-1 text-[0.625rem] text-muted-foreground transition-colors hover:text-foreground sm:top-4 sm:right-4 sm:px-2.5 sm:py-1.5 sm:text-xs"
              >
                {copied ? <IconCheck className="size-3" /> : <IconCopy className="size-3" />}
                {copied ? 'Copied' : 'Copy'}
              </button>
              <pre className="overflow-x-auto p-4 font-mono text-[0.625rem] leading-relaxed text-muted-foreground sm:p-6 sm:text-sm">
                {codeExamples[activeTab]?.code}
              </pre>
            </div>
          </div>
        </div>
      </div>
    </section>
  )
}