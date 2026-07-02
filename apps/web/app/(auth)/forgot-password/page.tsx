'use client'

import { useState } from 'react'
import Link from 'next/link'
import { AuthShell } from '@/features/auth/components/auth-shell'
import { ForgotPasswordForm } from '@/features/auth/components/forgot-password-form'
import { VerifyResetForm } from '@/features/auth/components/verify-reset-form'
import { ResetPasswordForm } from '@/features/auth/components/reset-password-form'

type Step = 'email' | 'otp' | 'password' | 'done'

export default function ForgotPasswordPage() {
  const [step, setStep] = useState<Step>('email')
  const [email, setEmail] = useState('')
  const [resetToken, setResetToken] = useState('')

  function handleEmailSent(addr: string) {
    setEmail(addr)
    setStep('otp')
  }

  function handleOtpVerified(token: string) {
    setResetToken(token)
    setStep('password')
  }

  function handlePasswordReset() {
    setStep('done')
  }

  if (step === 'done') {
    return (
      <AuthShell
        title="Password reset"
        description="Your password has been updated successfully."
        footer={
          <>
            <Link href="/login" className="text-primary underline-offset-4 hover:underline">
              Sign in with your new password
            </Link>
          </>
        }
      >
        <p className="text-sm text-muted-foreground">
          You can now sign in with your new password.
        </p>
      </AuthShell>
    )
  }

  return (
    <AuthShell
      title={
        step === 'email'
          ? 'Reset password'
          : step === 'otp'
            ? 'Check your email'
            : 'New password'
      }
      description={
        step === 'email'
          ? 'Enter your email and we\'ll send you a reset code.'
          : step === 'otp'
            ? `Enter the 6-digit code sent to ${email}.`
            : 'Choose a new password for your account.'
      }
      footer={
        step === 'email' ? (
          <Link href="/login" className="text-primary underline-offset-4 hover:underline">
            Back to sign in
          </Link>
        ) : (
          <button
            type="button"
            onClick={() => setStep('email')}
            className="text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
          >
            Back to email
          </button>
        )
      }
    >
      {step === 'email' && <ForgotPasswordForm onSent={handleEmailSent} />}
      {step === 'otp' && <VerifyResetForm email={email} onVerified={handleOtpVerified} />}
      {step === 'password' && (
        <ResetPasswordForm resetToken={resetToken} onSuccess={handlePasswordReset} />
      )}
    </AuthShell>
  )
}
