import type { Metadata } from 'next'
import Link from 'next/link'
import { AuthShell } from '@/features/auth/components/auth-shell'
import { RegisterForm } from '@/features/auth/components/register-form'

export const metadata: Metadata = {
  title: 'Create account — Hookvane',
  description: 'Create a Hookvane account to keep your webhook inboxes beyond 48 hours.',
}

export default function RegisterPage() {
  return (
    <AuthShell
      title="Create account"
      description="Keep inboxes, name integrations, and share events with your team."
      footer={
        <>
          <Link href="/" className="text-primary underline-offset-4 hover:underline">
            Back to home
          </Link>
        </>
      }
    >
      <RegisterForm />
    </AuthShell>
  )
}