'use client'

import * as React from 'react'
import { useState } from 'react'
import { zodResolver } from '@hookform/resolvers/zod'
import { Controller, useForm } from 'react-hook-form'
import { Button } from '@workspace/ui/components/button'
import {
  Field,
  FieldError,
  FieldGroup,
  FieldLabel,
} from '@workspace/ui/components/field'
import { Input } from '@workspace/ui/components/input'
import { ApiError } from '@/lib/api'
import {
  type ForgotPasswordValues,
  forgotPasswordSchema,
} from '@/lib/schemas/auth'
import { requestOtp } from '@/lib/api/otp'
import { useCountdown } from '@/hooks/use-countdown'

type ForgotPasswordFormProps = {
  onSent: (email: string) => void
}

export function ForgotPasswordForm({ onSent }: ForgotPasswordFormProps) {
  const [sent, setSent] = useState(false)
  const countdown = useCountdown()

  const form = useForm<ForgotPasswordValues>({
    resolver: zodResolver(forgotPasswordSchema),
    defaultValues: { email: '' },
  })

  async function onSubmit(values: ForgotPasswordValues) {
    try {
      await requestOtp(values.email, 'password_reset')
      setSent(true)
      countdown.start(60)
      onSent(values.email)
    } catch (err) {
      if (err instanceof ApiError) {
        if (err.status === 429) {
          countdown.start(err.body.retryAfter ?? 60)
          setSent(true)
          onSent(values.email)
        } else {
          form.setError('email', { message: err.message })
        }
      } else {
        form.setError('root', { message: 'Something went wrong. Try again.' })
      }
    }
  }

  if (sent) {
    return (
      <div className="flex flex-col gap-4">
        <p className="text-sm text-muted-foreground">
          If an account with that email exists, we&apos;ve sent a reset code. Check your inbox.
        </p>
        {countdown.isRunning && (
          <p className="text-center text-xs text-muted-foreground">
            Resend in {countdown.remaining}s
          </p>
        )}
      </div>
    )
  }

  return (
    <form id="forgot-password-form" onSubmit={form.handleSubmit(onSubmit)} noValidate>
      <FieldGroup>
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

        {form.formState.errors.root && (
          <FieldError>{form.formState.errors.root.message}</FieldError>
        )}

        <Button
          type="submit"
          form="forgot-password-form"
          className="h-10 w-full"
          disabled={form.formState.isSubmitting}
        >
          {form.formState.isSubmitting ? 'Sending…' : 'Send reset code'}
        </Button>
      </FieldGroup>
    </form>
  )
}
