import { Bricolage_Grotesque, Geist_Mono, JetBrains_Mono, Lora, Cormorant_Garamond } from 'geist/font'

/**
 * Shared fonts for Webhook Trap
 * Used across web, docs, and other apps
 */

// Landing page serif fonts
export const cormorantGaramond = Cormorant_Garamond({
  subsets: ['latin'],
  variable: '--font-cormorant',
  weight: ['600'],
})

export const lora = Lora({
  subsets: ['latin'],
  variable: '--font-lora',
  weight: ['400'],
})

// App UI fonts
export const bricolageGrotesque = Bricolage_Grotesque({
  subsets: ['latin'],
  variable: '--font-bricolage',
})

export const geistMono = Geist_Mono({
  variable: '--font-geist-mono',
})

export const jetbrainsMono = JetBrains_Mono({
  subsets: ['latin'],
  variable: '--font-jetbrains-mono',
  weight: ['400', '500'],
})

/**
 * Font class names for use in html/body
 * Usage: <html className={fontClasses}>
 */
export const fontClasses = [
  cormorantGaramond.variable,
  lora.variable,
  bricolageGrotesque.variable,
  geistMono.variable,
  jetbrainsMono.variable,
].join(' ')
