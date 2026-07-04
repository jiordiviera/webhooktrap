import { ReactNode } from 'react'

interface FontProviderProps {
  children: ReactNode
}

/**
 * Shared font provider for all apps
 * Applies font classes to html element
 *
 * Usage in layout.tsx:
 * ```tsx
 * import { FontProvider } from '@workspace/ui'
 *
 * export default function RootLayout({ children }) {
 *   return (
 *     <html suppressHydrationWarning>
 *       <body className={fontClasses}>
 *         {children}
 *       </body>
 *     </html>
 *   )
 * }
 * ```
 */
export function FontProvider({ children }: FontProviderProps) {
  return <>{children}</>
}

export { fontClasses } from '@workspace/ui/lib/fonts'
