import type { Metadata } from 'next'
import { Cormorant_Garamond, Lora, Albert_Sans } from 'next/font/google'
import { Providers } from '@/app/components/providers'
import '@workspace/ui/globals.css'
import { cn } from '@workspace/ui/lib/utils'
import { siteUrl } from '@/lib/config'

const cormorant = Cormorant_Garamond({
  subsets: ['latin'],
  weight: ['500', '600'],
  variable: '--font-cormorant',
  display: 'swap',
})

const lora = Lora({
  subsets: ['latin'],
  weight: ['400', '500'],
  variable: '--font-lora',
  display: 'swap',
})

const albertSans = Albert_Sans({subsets:['latin'],variable:'--font-sans'})

export const metadata: Metadata = {
  metadataBase: new URL(siteUrl),
  title: {
    default: 'Hookscope — Webhook debugger',
    template: '%s — Hookscope',
  },
  description:
    'Receive webhooks, inspect every payload, replay to your local server. Developer-first debugging without the dashboard bloat.',
  openGraph: {
    type: 'website',
    siteName: 'Hookscope',
    title: 'Hookscope — Webhook debugger',
    description:
      'Receive webhooks, inspect every payload, replay to your local server. Developer-first debugging without the dashboard bloat.',
    images: [
      {
        url: '/opengraph-image.png',
        width: 1200,
        height: 630,
        alt: 'Hookscope — Webhook debugging, simplified.',
      },
    ],
  },
  twitter: {
    card: 'summary_large_image',
    title: 'Hookscope — Webhook debugger',
    description:
      'Receive webhooks, inspect every payload, replay to your local server. Developer-first debugging without the dashboard bloat.',
    images: ['/opengraph-image.png'],
  },
  robots: { index: true, follow: true },
}

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode
}>) {
  return (
    <html lang="en" className={cn(cormorant.variable, lora.variable, "font-sans", albertSans.variable)} suppressHydrationWarning>
      <body>
        <Providers>{children}</Providers>
      </body>
    </html>
  )
}