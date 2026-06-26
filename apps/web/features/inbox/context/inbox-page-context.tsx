'use client'

import {
  createContext,
  useCallback,
  useContext,
  useMemo,
  useState,
  type ReactNode,
} from 'react'

type InboxPageContextValue = {
  title: string | null
  setTitle: (title: string | null) => void
}

const InboxPageContext = createContext<InboxPageContextValue | null>(null)

export function InboxPageProvider({ children }: { children: ReactNode }) {
  const [title, setTitleState] = useState<string | null>(null)

  const setTitle = useCallback((next: string | null) => {
    setTitleState(next)
  }, [])

  const value = useMemo(() => ({ title, setTitle }), [title, setTitle])

  return <InboxPageContext.Provider value={value}>{children}</InboxPageContext.Provider>
}

export function useInboxPageTitle() {
  return useContext(InboxPageContext)
}