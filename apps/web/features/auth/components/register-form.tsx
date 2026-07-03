'use client'

import * as React from 'react'
import { zodResolver } from '@hookform/resolvers/zod'
import Link from 'next/link'
import { useRouter } from 'next/navigation'
import { Controller, useForm } from 'react-hook-form'
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
import { useAuth } from '@/contexts/auth-context'
import { ApiError, apiFetch } from '@/lib/api'
import { type AuthData } from '@/lib/auth'
import { type RegisterValues, registerSchema } from '@/lib/schemas/auth'
import { OAuthButtons } from './oauth-buttons'

export function RegisterForm() {
  const router = useRouter()
  const { signIn, isAuthenticated } = useAuth()

  React.useEffect(() => {
    if (isAuthenticated) router.replace('/dashboard')
  }, [isAuthenticated, router])

  const form = useForm<RegisterValues>({
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
      const body = await apiFetch<{ data: AuthData }>('/auth/signup', {
        method: 'POST',
        skipAuth: true,
        body: JSON.stringify({
          fullName: values.fullName?.trim() || null,
          email: values.email,
          password: values.password,
          passwordConfirmation: values.passwordConfirmation,
        }),
      })

      signIn(body.data)
      router.push('/verify-email')
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
          form.setError(fieldError.field as keyof RegisterValues, { message: fieldError.message })
          return
        }
        form.setError('root', {
          message: fieldError?.message ?? err.message,
        })
      } else {
        form.setError('root', {
          message: 'Could not reach the API. Is the server running on port 3333?',
        })
      }
    }
  }

  return (
    <div className="flex flex-col gap-6">
      <OAuthButtons />

      <FieldSeparator>or with email</FieldSeparator>

      <form id="register-form" onSubmit={form.handleSubmit(onSubmit)} noValidate>
        <FieldGroup>
          <Controller
            name="fullName"
            control={form.control}
            render={({ field, fieldState }) => (
              <Field data-invalid={fieldState.invalid}>
                <FieldLabel htmlFor="fullName">Name</FieldLabel>
                <Input
                  id="fullName"
                  type="text"
                  autoComplete="name"
                  placeholder="Optional"
                  {...field}
                />
                <FieldDescription>Shown on shared event links.</FieldDescription>
              </Field>
            )}
          />

          <Controller
            name="email"
            control={form.control}
            render={({ field, fieldState }) => (
              <Field data-invalid={fieldState.invalid}>
                <FieldLabel htmlFor="email">Email</FieldLabel>
                <Input
                  id="email"
                  type="email"
                  autoComplete="email"
                  aria-invalid={fieldState.invalid}
                  {...field}
                />
                {fieldState.invalid && (
                  <FieldError errors={[fieldState.error]} />
                )}
              </Field>
            )}
          />

          <Controller
            name="password"
            control={form.control}
            render={({ field, fieldState }) => (
              <Field data-invalid={fieldState.invalid}>
                <FieldLabel htmlFor="password">Password</FieldLabel>
                <Input
                  id="password"
                  type="password"
                  autoComplete="new-password"
                  aria-invalid={fieldState.invalid}
                  {...field}
                />
                <FieldDescription>8 to 32 characters.</FieldDescription>
                {fieldState.invalid && (
                  <FieldError errors={[fieldState.error]} />
                )}
              </Field>
            )}
          />

          <Controller
            name="passwordConfirmation"
            control={form.control}
            render={({ field, fieldState }) => (
              <Field data-invalid={fieldState.invalid}>
                <FieldLabel htmlFor="passwordConfirmation">Confirm password</FieldLabel>
                <Input
                  id="passwordConfirmation"
                  type="password"
                  autoComplete="new-password"
                  aria-invalid={fieldState.invalid}
                  {...field}
                />
                {fieldState.invalid && (
                  <FieldError errors={[fieldState.error]} />
                )}
              </Field>
            )}
          />

          {form.formState.errors.root && (
            <FieldError>{form.formState.errors.root.message}</FieldError>
          )}

          <Button
            type="submit"
            form="register-form"
            className="h-10 w-full"
            disabled={form.formState.isSubmitting}
          >
            {form.formState.isSubmitting ? 'Creating account…' : 'Create account'}
          </Button>
        </FieldGroup>
      </form>

      <p className="text-center text-sm text-muted-foreground">
        Already have an account?{' '}
        <Link href="/login" className="text-primary underline-offset-4 hover:underline">
          Sign in
        </Link>
      </p>
    </div>
  )
}