'use client'

import { useEffect, useState } from 'react'
import { IconMoon, IconSun } from '@tabler/icons-react'
import { useTheme } from 'next-themes'
import { Button } from '@workspace/ui/components/button'
import { cn } from '@workspace/ui/lib/utils'

type ThemeToggleProps = {
  className?: string
}

export function ThemeToggle({ className }: ThemeToggleProps) {
  const { resolvedTheme, setTheme } = useTheme()
  const [mounted, setMounted] = useState(false)

  useEffect(() => {
    setMounted(true)
  }, [])

  if (!mounted) {
    return (
      <Button
        type="button"
        variant="outline"
        size="icon-sm"
        className={cn('rounded-full', className)}
        disabled
        aria-label="Toggle theme"
      >
        <IconSun className="size-4 opacity-0" aria-hidden />
      </Button>
    )
  }

  const isDark = resolvedTheme === 'dark'

  return (
    <Button
      type="button"
      variant="outline"
      size="icon-sm"
      className={cn('rounded-full', className)}
      onClick={() => setTheme(isDark ? 'light' : 'dark')}
      aria-label={isDark ? 'Switch to light mode' : 'Switch to dark mode'}
      aria-pressed={isDark}
    >
      {isDark ? <IconSun className="size-4" aria-hidden /> : <IconMoon className="size-4" aria-hidden />}
    </Button>
  )
}