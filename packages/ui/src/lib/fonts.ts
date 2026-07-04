import { Bricolage_Grotesque, JetBrains_Mono } from "next/font/google";

/**
 * Shared fonts for Webhook Trap
 * UI Sans-serif + Monospace
 */

export const bricolageGrotesque = Bricolage_Grotesque({
  subsets: ["latin"],
  variable: "--font-sans",
});

export const jetbrainsMono = JetBrains_Mono({
  subsets: ["latin"],
  variable: "--font-mono",
  weight: ["400", "500"],
});

/**
 * Font class names for use in html/body
 * Usage: <html className={fontClasses}>
 */
export const fontClasses = [
  bricolageGrotesque.variable,
  jetbrainsMono.variable,
].join(" ");
