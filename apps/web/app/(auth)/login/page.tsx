import type { Metadata } from 'next'
import Link from 'next/link'
import { Suspense } from 'react'
import { AuthShell } from '@/features/auth/components/auth-shell'
import { LoginForm } from '@/features/auth/components/login-form'

export const metadata: Metadata = {
  title: 'Sign in — Hookvane',
  description: 'Sign in to save your webhook inboxes and replay events to localhost.',
}

export default function LoginPage() {
  return (
    <AuthShell
      title="Sign in"
      description="Access your saved inboxes and replay history."
      footer={
        <>
          <Link href="/" className="text-primary underline-offset-4 hover:underline">
            Back to home
          </Link>
        </>
      }
    >
      <Suspense fallback={null}>
        <LoginForm />
      </Suspense>
    </AuthShell>
  )
}