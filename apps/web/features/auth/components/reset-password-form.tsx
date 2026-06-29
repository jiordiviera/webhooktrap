'use client'

import * as React from 'react'
import { zodResolver } from '@hookform/resolvers/zod'
import { Controller, useForm } from 'react-hook-form'
import { Button } from '@workspace/ui/components/button'
import {
  Field,
  FieldDescription,
  FieldError,
  FieldGroup,
  FieldLabel,
} from '@workspace/ui/components/field'
import { Input } from '@workspace/ui/components/input'
import { ApiError } from '@/lib/api'
import {
  type ResetPasswordValues,
  resetPasswordSchema,
} from '@/lib/schemas/auth'
import { resetPassword } from '@/lib/api/otp'

type ResetPasswordFormProps = {
  resetToken: string
  onSuccess: () => void
}

export function ResetPasswordForm({ resetToken, onSuccess }: ResetPasswordFormProps) {
  const form = useForm<ResetPasswordValues>({
    resolver: zodResolver(resetPasswordSchema),
    defaultValues: { password: '', passwordConfirmation: '' },
  })

  async function onSubmit(values: ResetPasswordValues) {
    try {
      await resetPassword(resetToken, values.password, values.passwordConfirmation)
      onSuccess()
    } catch (err) {
      if (err instanceof ApiError) {
        const fieldError = err.body.errors?.[0]
        if (fieldError?.field) {
          form.setError(fieldError.field as keyof ResetPasswordValues, {
            message: fieldError.message,
          })
          return
        }
        form.setError('root', { message: fieldError?.message ?? err.message })
      } else {
        form.setError('root', { message: 'Something went wrong. Try again.' })
      }
    }
  }

  return (
    <form id="reset-password-form" onSubmit={form.handleSubmit(onSubmit)} noValidate>
      <FieldGroup>
        <Controller
          name="password"
          control={form.control}
          render={({ field, fieldState }) => (
            <Field data-invalid={fieldState.invalid}>
              <FieldLabel htmlFor="password">New password</FieldLabel>
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
              <FieldLabel htmlFor="passwordConfirmation">Confirm new password</FieldLabel>
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
          form="reset-password-form"
          className="font-ui h-10 w-full"
          disabled={form.formState.isSubmitting}
        >
          {form.formState.isSubmitting ? 'Resetting…' : 'Reset password'}
        </Button>
      </FieldGroup>
    </form>
  )
}
