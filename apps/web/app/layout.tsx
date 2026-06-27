import type { Metadata } from 'next'
import { Cormorant_Garamond, Geist, Lora, Space_Grotesk, Outfit } from 'next/font/google'
import { Providers } from '@/app/components/providers'
import '@workspace/ui/globals.css'
import { cn } from '@workspace/ui/lib/utils'

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

const outfit = Outfit({subsets:['latin'],variable:'--font-sans'})

const siteUrl = process.env.WEB_URL ?? 'http://localhost:7777'

export const metadata: Metadata = {
  metadataBase: new URL(siteUrl),
  title: 'Hookscope — Webhook debugger',
  description:
    'Receive webhooks, inspect every payload, replay to your local server. Developer-first debugging without the dashboard bloat.',
  openGraph: {
    title: 'Hookscope',
    description: 'Receive. Inspect. Replay.',
    images: ['/logo.png'],
  },
}

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode
}>) {
  return (
    <html lang="en" className={cn(cormorant.variable, lora.variable, "font-sans", outfit.variable)} suppressHydrationWarning>
      <body>
        <Providers>{children}</Providers>
      </body>
    </html>
  )
}