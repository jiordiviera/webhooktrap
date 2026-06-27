'use client'

import { useCallback, useRef, useState } from 'react'
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
import { Input } from '@workspace/ui/components/input'
import { useAuth } from '@/contexts/auth-context'
import { ApiError, apiFetch } from '@/lib/api'
import { type AuthPayload } from '@/lib/auth'
import { type LoginValues, loginSchema } from '@/lib/schemas/auth'
import { verifyChallenge } from '@/lib/2fa'
import { OAuthButtons } from './oauth-buttons'

export function LoginForm() {
  const router = useRouter()
  const searchParams = useSearchParams()
  const { signIn } = useAuth()
  const returnTo = searchParams.get('returnTo') ?? '/'

  const [requires2fa, setRequires2fa] = useState(false)
  const [challengeToken, setChallengeToken] = useState<string | null>(null)
  const [otp, setOtp] = useState('')
  const [otpError, setOtpError] = useState<string | null>(null)
  const [otpSubmitting, setOtpSubmitting] = useState(false)
  const otpInputRef = useRef<HTMLInputElement>(null)
  const [loginError, setLoginError] = useState<string | null>(null)

  const {
    register,
    handleSubmit,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<LoginValues>({
    resolver: zodResolver(loginSchema),
    defaultValues: {
      email: '',
      password: '',
    },
  })

  async function onSubmit(values: LoginValues) {
    setLoginError(null)
    try {
      const body = await apiFetch<AuthPayload>('/api/v1/auth/login', {
        method: 'POST',
        skipAuth: true,
        body: JSON.stringify(values),
      })

      if ('requires_2fa' in body.data) {
        setChallengeToken(body.data.challenge_token)
        setRequires2fa(true)
        setTimeout(() => otpInputRef.current?.focus(), 150)
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
          setError(fieldError.field as keyof LoginValues, { message: fieldError.message })
          return
        }
        setError('root', {
          message: fieldError?.message ?? err.message,
        })
      } else {
        setLoginError('Could not reach the API. Is the server running on port 3333?')
      }
    }
  }

  const handleOtpSubmit = useCallback(
    async (e: React.FormEvent) => {
      e.preventDefault()
      if (!otp.trim() || !challengeToken) return

      setOtpSubmitting(true)
      setOtpError(null)
      try {
        const body = await verifyChallenge(otp.trim(), challengeToken)
        signIn(body.data)
        router.push(returnTo.startsWith('/') ? returnTo : '/')
        router.refresh()
      } catch (err) {
        if (err instanceof ApiError) {
          setOtpError(err.message || 'Invalid code. Try again.')
        } else {
          setOtpError('Verification failed. Please try again.')
        }
      } finally {
        setOtpSubmitting(false)
      }
    },
    [otp, challengeToken, signIn, router, returnTo]
  )

  const handleBackToLogin = useCallback(() => {
    setRequires2fa(false)
    setChallengeToken(null)
    setOtp('')
    setOtpError(null)
  }, [])

  if (requires2fa) {
    return (
      <div className="flex flex-col gap-6">
        <div className="text-center">
          <p className="text-sm font-medium text-foreground">Two-factor authentication</p>
          <p className="mt-1 text-sm text-muted-foreground">
            Enter the code from your authenticator app.
          </p>
        </div>

        <form onSubmit={handleOtpSubmit}>
          <FieldGroup>
            <Field>
              <FieldLabel htmlFor="2fa-challenge-otp">Authentication code</FieldLabel>
              <Input
                ref={otpInputRef}
                id="2fa-challenge-otp"
                placeholder="000 000"
                value={otp}
                onChange={(e) => {
                  setOtp(e.target.value.replace(/\D/g, '').slice(0, 6))
                  setOtpError(null)
                }}
                maxLength={6}
                className="font-mono tracking-[0.3em] text-center"
              />
              <FieldError>{otpError}</FieldError>
            </Field>

            {loginError && <FieldError>{loginError}</FieldError>}

            <Button type="submit" className="font-ui h-10 w-full" disabled={otpSubmitting || otp.length < 6}>
              {otpSubmitting ? 'Verifying…' : 'Verify'}
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

      <form onSubmit={handleSubmit(onSubmit)} noValidate>
        <FieldGroup>
          <Field data-invalid={!!errors.email}>
            <FieldLabel htmlFor="email">Email</FieldLabel>
            <Input
              id="email"
              type="email"
              autoComplete="email"
              aria-invalid={!!errors.email}
              {...register('email')}
            />
            <FieldError errors={errors.email ? [errors.email] : undefined} />
          </Field>

          <Field data-invalid={!!errors.password}>
            <FieldLabel htmlFor="password">Password</FieldLabel>
            <Input
              id="password"
              type="password"
              autoComplete="current-password"
              aria-invalid={!!errors.password}
              {...register('password')}
            />
            <FieldError errors={errors.password ? [errors.password] : undefined} />
          </Field>

          {errors.root && <FieldError>{errors.root.message}</FieldError>}

          <Button type="submit" className="font-ui h-10 w-full" disabled={isSubmitting}>
            {isSubmitting ? 'Signing in…' : 'Sign in'}
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
