'use client'

import Link from 'next/link'
import { useRouter, useSearchParams } from 'next/navigation'
import { Suspense, useEffect, useState } from 'react'
import { Button } from '@workspace/ui/components/button'
import { Loader } from '@workspace/ui/components/loader'
import { useAuth } from '@/contexts/auth-context'

const ERROR_MESSAGES: Record<string, string> = {
  unknown_provider: 'This sign-in provider is not available.',
  access_denied: 'Sign-in was cancelled.',
  state_mismatch: 'Sign-in session expired. Try again.',
  oauth_error: 'Something went wrong with the provider. Try again.',
}

function AuthCallbackContent() {
  const router = useRouter()
  const searchParams = useSearchParams()
  const { signInWithToken } = useAuth()
  const [status, setStatus] = useState<'loading' | 'error'>('loading')
  const [message, setMessage] = useState('')

  useEffect(() => {
    const token = searchParams.get('token')
    const error = searchParams.get('error')
    console.log("searchParams: ", searchParams)

    if (error) {
      setStatus('error')
      setMessage(ERROR_MESSAGES[error] ?? 'Sign-in failed.')
      return
    }

    if (!token) {
      setStatus('error')
      setMessage('Missing sign-in token.')
      return
    }

    void signInWithToken(token)
      .then(() => router.replace('/'))
      .catch(() => {
        setStatus('error')
        setMessage('Could not finish sign-in. Try again.')
      })
  }, [router, searchParams, signInWithToken])

  if (status === 'loading') {
    return (
      <Loader
        layout="fullscreen"
        variant="ring"
        size="lg"
        label="Finishing sign-in"
        showLabel
        className="px-6"
      />
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
        <Loader layout="fullscreen" variant="ring" size="lg" label="Loading" showLabel />
      }
    >
      <AuthCallbackContent />
    </Suspense>
  )
}