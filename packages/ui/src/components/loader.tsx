import * as React from 'react'
import { cva, type VariantProps } from 'class-variance-authority'

import { cn } from '@workspace/ui/lib/utils'

const loaderVariants = cva('inline-flex shrink-0 items-center justify-center', {
  variants: {
    variant: {
      spinner: '',
      dots: 'gap-1',
      ring: '',
    },
    size: {
      xs: '',
      sm: '',
      md: '',
      lg: '',
      xl: '',
    },
    tone: {
      default: 'text-primary',
      muted: 'text-muted-foreground',
      signal: 'text-signal',
      inherit: 'text-current',
    },
  },
  defaultVariants: {
    variant: 'spinner',
    size: 'md',
    tone: 'default',
  },
})

const spinnerSizeClasses = {
  xs: 'size-3 border',
  sm: 'size-4 border-2',
  md: 'size-6 border-2',
  lg: 'size-8 border-2',
  xl: 'size-10 border-[3px]',
} as const

const dotSizeClasses = {
  xs: 'size-1',
  sm: 'size-1.5',
  md: 'size-2',
  lg: 'size-2.5',
  xl: 'size-3',
} as const

const ringSizeClasses = {
  xs: 'size-4',
  sm: 'size-5',
  md: 'size-7',
  lg: 'size-9',
  xl: 'size-11',
} as const

const layoutClasses = {
  inline: '',
  centered: 'flex-col gap-3 py-8',
  fullscreen: 'min-h-svh flex-col gap-3',
} as const

type LoaderProps = React.ComponentProps<'div'> &
  VariantProps<typeof loaderVariants> & {
    /** Accessible label. Also shown when `showLabel` is true. */
    label?: string
    showLabel?: boolean
    layout?: keyof typeof layoutClasses
    indicatorClassName?: string
  }

function Loader({
  className,
  indicatorClassName,
  variant = 'spinner',
  size = 'md',
  tone = 'default',
  label = 'Loading',
  showLabel = false,
  layout = 'inline',
  ...props
}: LoaderProps) {
  const resolvedSize = size ?? 'md'

  const indicator = (() => {
    if (variant === 'dots') {
      return (
        <span className={cn(loaderVariants({ variant, size, tone }), indicatorClassName)} aria-hidden>
          {[0, 1, 2].map((i) => (
            <span
              key={i}
              className={cn(
                'animate-pulse rounded-full bg-current motion-reduce:animate-none',
                dotSizeClasses[resolvedSize]
              )}
              style={{ animationDelay: `${i * 160}ms` }}
            />
          ))}
        </span>
      )
    }

    if (variant === 'ring') {
      return (
        <span
          className={cn(
            'relative inline-flex items-center justify-center motion-reduce:animate-none',
            ringSizeClasses[resolvedSize],
            loaderVariants({ tone }),
            indicatorClassName
          )}
          aria-hidden
        >
          <span
            className={cn(
              'absolute inset-0 animate-spin rounded-full border-2 border-current/25 motion-reduce:animate-none',
              ringSizeClasses[resolvedSize]
            )}
          />
          <span
            className={cn(
              'absolute inset-[18%] animate-spin rounded-full border-2 border-current border-t-transparent motion-reduce:animate-none',
              '[animation-direction:reverse] [animation-duration:1.1s]'
            )}
          />
        </span>
      )
    }

    return (
      <span
        className={cn(
          'animate-spin rounded-full border-current border-t-transparent motion-reduce:animate-none',
          spinnerSizeClasses[resolvedSize],
          loaderVariants({ tone }),
          indicatorClassName
        )}
        aria-hidden
      />
    )
  })()

  const isBlockLayout = layout !== 'inline'

  return (
    <div
      role="status"
      aria-live="polite"
      aria-busy="true"
      className={cn(
        isBlockLayout && 'flex items-center justify-center text-center',
        layoutClasses[layout],
        className
      )}
      {...props}
    >
      {indicator}
      <span className={showLabel ? 'font-ui text-sm text-muted-foreground' : 'sr-only'}>
        {label}
      </span>
    </div>
  )
}

export { Loader, loaderVariants }
export type { LoaderProps }