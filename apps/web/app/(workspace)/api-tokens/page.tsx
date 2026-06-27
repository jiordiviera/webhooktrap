'use client'

import { ApiTokensPage } from '@/features/settings/components/api-tokens-page'
import { useAuth } from '@/contexts/auth-context'

export default function Page() {
  const { token } = useAuth()

  if (!token) return null

  return <ApiTokensPage token={token} />
}
