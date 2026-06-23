'use client'

import { zodResolver } from '@hookform/resolvers/zod'
import Link from 'next/link'
import { useRouter } from 'next/navigation'
import { useForm } from 'react-hook-form'
import { Button } from '@workspace/ui/components/button'
import {
  Field,
  FieldDescription,
  FieldError,
  FieldGroup,
  FieldLabel,
  FieldSeparator,
} from '@workspace/ui/components/field'
import { Input } from '@workspace/ui/components/input'
import { ApiError, apiFetch } from '@/lib/api'
import { type AuthPayload, saveAuthToken } from '@/lib/auth'
import { type RegisterValues, registerSchema } from '@/lib/schemas/auth'
import { OAuthButtons } from './oauth-buttons'

export function RegisterForm() {
  const router = useRouter()
  const {
    register,
    handleSubmit,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<RegisterValues>({
    resolver: zodResolver(registerSchema),
    defaultValues: {
      fullName: '',
      email: '',
      password: '',
      passwordConfirmation: '',
    },
  })

  async function onSubmit(values: RegisterValues) {
    try {
      const body = await apiFetch<AuthPayload>('/api/v1/auth/signup', {
        method: 'POST',
        body: JSON.stringify({
          fullName: values.fullName?.trim() || null,
          email: values.email,
          password: values.password,
          passwordConfirmation: values.passwordConfirmation,
        }),
      })

      saveAuthToken(body.data.token)
      router.push('/')
      router.refresh()
    } catch (err) {
      if (err instanceof ApiError) {
        const fieldError = err.body.errors?.[0]
        const registerFields = [
          'fullName',
          'email',
          'password',
          'passwordConfirmation',
        ] as const
        if (
          fieldError?.field &&
          registerFields.includes(fieldError.field as (typeof registerFields)[number])
        ) {
          setError(fieldError.field as keyof RegisterValues, { message: fieldError.message })
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
          <Field>
            <FieldLabel htmlFor="fullName">Name</FieldLabel>
            <Input
              id="fullName"
              type="text"
              autoComplete="name"
              placeholder="Optional"
              {...register('fullName')}
            />
            <FieldDescription>Shown on shared event links.</FieldDescription>
          </Field>

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
              autoComplete="new-password"
              aria-invalid={!!errors.password}
              {...register('password')}
            />
            <FieldDescription>8 to 32 characters.</FieldDescription>
            <FieldError errors={errors.password ? [errors.password] : undefined} />
          </Field>

          <Field data-invalid={!!errors.passwordConfirmation}>
            <FieldLabel htmlFor="passwordConfirmation">Confirm password</FieldLabel>
            <Input
              id="passwordConfirmation"
              type="password"
              autoComplete="new-password"
              aria-invalid={!!errors.passwordConfirmation}
              {...register('passwordConfirmation')}
            />
            <FieldError
              errors={errors.passwordConfirmation ? [errors.passwordConfirmation] : undefined}
            />
          </Field>

          {errors.root && <FieldError>{errors.root.message}</FieldError>}

          <Button type="submit" className="font-ui h-10 w-full" disabled={isSubmitting}>
            {isSubmitting ? 'Creating account…' : 'Create account'}
          </Button>
        </FieldGroup>
      </form>

      <p className="font-ui text-center text-sm text-muted-foreground">
        Already have an account?{' '}
        <Link href="/login" className="text-primary underline-offset-4 hover:underline">
          Sign in
        </Link>
      </p>
    </div>
  )
}