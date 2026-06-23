"use client";

import type { ReactNode } from 'react'
import { ThemeProvider } from '@/app/components/theme-provider'
import { AuthProvider } from '@/contexts/auth-context'
import { TooltipProvider } from '@workspace/ui/components/tooltip'

export function Providers({ children }: { children: ReactNode }) {
  return (
    <ThemeProvider>
      <TooltipProvider>
        <AuthProvider>{children}</AuthProvider>
      </TooltipProvider>
    </ThemeProvider>
  )
}
