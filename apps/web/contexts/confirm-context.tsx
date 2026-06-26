'use client'

import {
  createContext,
  useCallback,
  useContext,
  useMemo,
  useRef,
  useState,
  type ReactNode,
} from 'react'
import { Button } from '@workspace/ui/components/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@workspace/ui/components/dialog'
export type ConfirmOptions = {
  title: string
  description: ReactNode
  confirmLabel?: string
  cancelLabel?: string
  destructive?: boolean
}

type ConfirmRequest = ConfirmOptions & {
  open: boolean
}

type ConfirmContextValue = {
  confirm: (options: ConfirmOptions) => Promise<boolean>
}

const ConfirmContext = createContext<ConfirmContextValue | null>(null)

export function ConfirmProvider({ children }: { children: ReactNode }) {
  const [request, setRequest] = useState<ConfirmRequest | null>(null)
  const resolveRef = useRef<((value: boolean) => void) | null>(null)

  const settle = useCallback((result: boolean) => {
    const resolve = resolveRef.current
    if (!resolve) return

    resolveRef.current = null
    setRequest(null)
    resolve(result)
  }, [])

  const confirm = useCallback((options: ConfirmOptions) => {
    return new Promise<boolean>((resolve) => {
      resolveRef.current = resolve
      setRequest({ ...options, open: true })
    })
  }, [])

  const handleOpenChange = useCallback(
    (open: boolean) => {
      if (!open) {
        settle(false)
      }
    },
    [settle]
  )

  const value = useMemo(() => ({ confirm }), [confirm])

  return (
    <ConfirmContext.Provider value={value}>
      {children}
      {request ? (
        <Dialog open={request.open} onOpenChange={handleOpenChange}>
          <DialogContent className="sm:max-w-md">
            <DialogHeader>
              <DialogTitle>{request.title}</DialogTitle>
              {typeof request.description === 'string' ? (
                <DialogDescription>{request.description}</DialogDescription>
              ) : (
                <DialogDescription asChild>
                  <div>{request.description}</div>
                </DialogDescription>
              )}
            </DialogHeader>

            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => settle(false)}>
                {request.cancelLabel ?? 'Cancel'}
              </Button>
              <Button
                type="button"
                variant={request.destructive ? 'destructive' : 'default'}
                onClick={() => settle(true)}
              >
                {request.confirmLabel ?? 'Confirm'}
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      ) : null}
    </ConfirmContext.Provider>
  )
}

export function useConfirm() {
  const context = useContext(ConfirmContext)

  if (!context) {
    throw new Error('useConfirm must be used within ConfirmProvider')
  }

  return context.confirm
}