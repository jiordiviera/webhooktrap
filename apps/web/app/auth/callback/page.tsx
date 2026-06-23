'use client'

import Link from 'next/link'
import { useRouter, useSearchParams } from 'next/navigation'
import { Suspense, useEffect, useState } from 'react'
import { Button } from '@workspace/ui/components/button'
import { saveAuthToken } from '@/lib/auth'

const ERROR_MESSAGES: Record<string, string> = {
  unknown_provider: 'This sign-in provider is not available.',
  access_denied: 'Sign-in was cancelled.',
  state_mismatch: 'Sign-in session expired. Try again.',
  oauth_error: 'Something went wrong with the provider. Try again.',
}

function AuthCallbackContent() {
  const router = useRouter()
  const searchParams = useSearchParams()
  const [status, setStatus] = useState<'loading' | 'error'>('loading')
  const [message, setMessage] = useState('')

  useEffect(() => {
    const token = searchParams.get('token')
    const error = searchParams.get('error')

    if (token) {
      saveAuthToken(token)
      router.replace('/')
      return
    }

    setStatus('error')
    setMessage(error ? (ERROR_MESSAGES[error] ?? 'Sign-in failed.') : 'Missing sign-in token.')
  }, [router, searchParams])

  if (status === 'loading') {
    return (
      <div className="flex min-h-svh flex-col items-center justify-center gap-3 px-6 text-center">
        <p className="font-heading text-xl font-semibold">Finishing sign-in…</p>
        <p className="text-sm text-muted-foreground">You will be redirected in a moment.</p>
      </div>
    )
  }

  return (
    <div className="flex min-h-svh flex-col items-center justify-center gap-6 px-6 text-center">
      <div>
        <p className="font-heading mb-2 text-xl font-semibold">Sign-in failed</p>
        <p className="max-w-sm text-sm text-muted-foreground">{message}</p>
      </div>
      <div className="flex flex-wrap justify-center gap-3">
        <Button asChild>
          <Link href="/login">Try again</Link>
        </Button>
        <Button variant="outline" asChild>
          <Link href="/">Go home</Link>
        </Button>
      </div>
    </div>
  )
}

export default function AuthCallbackPage() {
  return (
    <Suspense
      fallback={
        <div className="flex min-h-svh items-center justify-center text-sm text-muted-foreground">
          Loading…
        </div>
      }
    >
      <AuthCallbackContent />
    </Suspense>
  )
}