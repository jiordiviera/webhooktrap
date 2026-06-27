'use client'

import { useState } from 'react'
import { zodResolver } from '@hookform/resolvers/zod'
import Link from 'next/link'
import { useRouter, useSearchParams } from 'next/navigation'
import { useForm } from 'react-hook-form'
import { Button } from '@workspace/ui/components/button'
import {
  Field,
  FieldError,
  FieldGroup,
  FieldLabel,
  FieldSeparator,
} from '@workspace/ui/components/field'
import {
  InputOTP,
  InputOTPGroup,
  InputOTPSeparator,
  InputOTPSlot,
  REGEXP_ONLY_DIGITS,
} from '@workspace/ui/components/input-otp'
import { Input } from '@workspace/ui/components/input'
import { useAuth } from '@/contexts/auth-context'
import { ApiError, apiFetch } from '@/lib/api'
import { type AuthPayload } from '@/lib/auth'
import {
  type LoginValues,
  loginSchema,
  type OtpValues,
  challengeOtpSchema,
} from '@/lib/schemas/auth'
import { verifyChallenge } from '@/lib/2fa'
import { OAuthButtons } from './oauth-buttons'

export function LoginForm() {
  const router = useRouter()
  const searchParams = useSearchParams()
  const { signIn } = useAuth()
  const returnTo = searchParams.get('returnTo') ?? '/'

  const [requires2fa, setRequires2fa] = useState(false)
  const [challengeToken, setChallengeToken] = useState<string | null>(null)

  const loginForm = useForm<LoginValues>({
    resolver: zodResolver(loginSchema),
    defaultValues: { email: '', password: '' },
  })

  const otpForm = useForm<OtpValues>({
    resolver: zodResolver(challengeOtpSchema),
    defaultValues: { otp: '' },
  })

  async function onSubmitLogin(values: LoginValues) {
    loginForm.clearErrors()
    try {
      const body = await apiFetch<AuthPayload>('/api/v1/auth/login', {
        method: 'POST',
        skipAuth: true,
        body: JSON.stringify(values),
      })

      if ('requires_2fa' in body.data) {
        setChallengeToken(body.data.challenge_token)
        setRequires2fa(true)
        return
      }

      signIn(body.data)
      router.push(returnTo.startsWith('/') ? returnTo : '/')
      router.refresh()
    } catch (err) {
      if (err instanceof ApiError) {
        const fieldError = err.body.errors?.[0]
        const loginFields = ['email', 'password'] as const
        if (
          fieldError?.field &&
          loginFields.includes(fieldError.field as (typeof loginFields)[number])
        ) {
          loginForm.setError(fieldError.field as keyof LoginValues, {
            message: fieldError.message,
          })
          return
        }
        loginForm.setError('root', {
          message: fieldError?.message ?? err.message,
        })
      } else {
        loginForm.setError('root', {
          message: 'Could not reach the API. Is the server running on port 3333?',
        })
      }
    }
  }

  async function onSubmitOtp(values: OtpValues) {
    if (!challengeToken) return

    try {
      const body = await verifyChallenge(values.otp, challengeToken)
      signIn(body.data)
      router.push(returnTo.startsWith('/') ? returnTo : '/')
      router.refresh()
    } catch (err) {
      if (err instanceof ApiError) {
        otpForm.setError('otp', { message: err.message || 'Invalid code. Try again.' })
      } else {
        otpForm.setError('otp', { message: 'Verification failed. Please try again.' })
      }
    }
  }

  function handleBackToLogin() {
    setRequires2fa(false)
    setChallengeToken(null)
    otpForm.reset()
  }

  if (requires2fa) {
    const otpValue = otpForm.watch('otp')
    const otpError = otpForm.formState.errors.otp?.message

    return (
      <div className="flex flex-col gap-6">
        <div className="text-center">
          <p className="text-sm font-medium text-foreground">Two-factor authentication</p>
          <p className="mt-1 text-sm text-muted-foreground">
            Enter the code from your authenticator app.
          </p>
        </div>

        <form onSubmit={otpForm.handleSubmit(onSubmitOtp)}>
          <FieldGroup>
            <Field>
              <FieldLabel htmlFor="2fa-challenge-otp">Authentication code</FieldLabel>
              <InputOTP
                pattern={REGEXP_ONLY_DIGITS}
                inputMode="numeric"
                id="2fa-challenge-otp"
                className='w-full'
                maxLength={6}
                value={otpValue}
                onChange={(v) => otpForm.setValue('otp', v, { shouldValidate: true })}
                onComplete={() => otpForm.handleSubmit(onSubmitOtp)()}
              >
                <InputOTPGroup> 
                  {Array.from({ length: 3 }).map((_, index) => (
                    <InputOTPSlot
                      key={index}
                      index={index}
                      className="size-12 text-2xl sm:size-16 sm:text-3xl"
                    />
                  ))}
                </InputOTPGroup>
                <InputOTPSeparator />
                <InputOTPGroup>
                  {Array.from({ length: 3 }).map((_, index) => (
                    <InputOTPSlot
                      key={index}
                      index={index}
                      className="size-12 text-2xl sm:size-16 sm:text-3xl"
                    />
                  ))}
                </InputOTPGroup>
              </InputOTP>
              <FieldError>{otpError}</FieldError>
            </Field>

            <Button
              type="submit"
              className="font-ui h-10 w-full"
              disabled={otpForm.formState.isSubmitting || otpValue.length < 6}
            >
              {otpForm.formState.isSubmitting ? 'Verifying…' : 'Verify'}
            </Button>
          </FieldGroup>
        </form>

        <button
          type="button"
          onClick={handleBackToLogin}
          className="font-ui text-center text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
        >
          Back to sign in
        </button>
      </div>
    )
  }

  return (
    <div className="flex flex-col gap-6">
      <OAuthButtons />

      <FieldSeparator>or with email</FieldSeparator>

      <form onSubmit={loginForm.handleSubmit(onSubmitLogin)} noValidate>
        <FieldGroup>
          <Field data-invalid={!!loginForm.formState.errors.email}>
            <FieldLabel htmlFor="email">Email</FieldLabel>
            <Input
              id="email"
              type="email"
              autoComplete="email"
              aria-invalid={!!loginForm.formState.errors.email}
              {...loginForm.register('email')}
            />
            <FieldError
              errors={
                loginForm.formState.errors.email
                  ? [loginForm.formState.errors.email]
                  : undefined
              }
            />
          </Field>

          <Field data-invalid={!!loginForm.formState.errors.password}>
            <FieldLabel htmlFor="password">Password</FieldLabel>
            <Input
              id="password"
              type="password"
              autoComplete="current-password"
              aria-invalid={!!loginForm.formState.errors.password}
              {...loginForm.register('password')}
            />
            <FieldError
              errors={
                loginForm.formState.errors.password
                  ? [loginForm.formState.errors.password]
                  : undefined
              }
            />
          </Field>

          {loginForm.formState.errors.root && (
            <FieldError>{loginForm.formState.errors.root.message}</FieldError>
          )}

          <Button
            type="submit"
            className="font-ui h-10 w-full"
            disabled={loginForm.formState.isSubmitting}
          >
            {loginForm.formState.isSubmitting ? 'Signing in…' : 'Sign in'}
          </Button>
        </FieldGroup>
      </form>

      <p className="font-ui text-center text-sm text-muted-foreground">
        No account yet?{' '}
        <Link href="/register" className="text-primary underline-offset-4 hover:underline">
          Create one
        </Link>
      </p>
    </div>
  )
}
