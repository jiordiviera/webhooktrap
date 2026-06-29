import type { Metadata } from 'next'
import Link from 'next/link'
import { AuthShell } from '@/features/auth/components/auth-shell'
import { VerifyEmailForm } from '@/features/auth/components/verify-email-form'
import { productName } from '@/lib/config'

export const metadata: Metadata = {
  title: `Verify email — ${productName}`,
  description: 'Verify your email address to access all features.',
}

export default function VerifyEmailPage() {
  return (
    <AuthShell
      title="Verify your email"
      description="Confirm your email address to unlock all features."
      footer={
        <>
          <Link href="/" className="text-primary underline-offset-4 hover:underline">
            Back to home
          </Link>
        </>
      }
    >
      <VerifyEmailForm />
    </AuthShell>
  )
}
