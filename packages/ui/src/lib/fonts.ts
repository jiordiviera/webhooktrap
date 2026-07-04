import { Bricolage_Grotesque, JetBrains_Mono } from 'geist/font'

/**
 * Shared fonts for Webhook Trap
 * UI Sans-serif + Monospace
 */

export const bricolageGrotesque = Bricolage_Grotesque({
  subsets: ['latin'],
  variable: '--font-ui',
})

export const jetbrainsMono = JetBrains_Mono({
  subsets: ['latin'],
  variable: '--font-mono',
  weight: ['400', '500'],
})

/**
 * Font class names for use in html/body
 * Usage: <html className={fontClasses}>
 */
export const fontClasses = [
  bricolageGrotesque.variable,
  jetbrainsMono.variable,
].join(' ')
