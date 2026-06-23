'use client'

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
import { OAuthButtons } from './oauth-buttons'

export function LoginForm() {
  const router = useRouter()
  const searchParams = useSearchParams()
  const { signIn } = useAuth()
  const returnTo = searchParams.get('returnTo') ?? '/'
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
    try {
      const body = await apiFetch<AuthPayload>('/api/v1/auth/login', {
        method: 'POST',
        body: JSON.stringify(values),
      })

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
        setError('root', {
          message: 'Could not reach the API. Is the server running on port 3333?',
        })
      }
    }
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