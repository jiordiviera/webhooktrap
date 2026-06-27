'use client'

import { SettingsPage } from '@/features/settings/components/settings-page'
import { useAuth } from '@/contexts/auth-context'

export default function Page() {
  const { token } = useAuth()

  if (!token) return null

  return <SettingsPage token={token} />
}
