'use client'

import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
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
  const {
    register,
    handleSubmit,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<ResetPasswordValues>({
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
          setError(fieldError.field as keyof ResetPasswordValues, {
            message: fieldError.message,
          })
          return
        }
        setError('root', { message: fieldError?.message ?? err.message })
      } else {
        setError('root', { message: 'Something went wrong. Try again.' })
      }
    }
  }

  return (
    <form onSubmit={handleSubmit(onSubmit)} noValidate>
      <FieldGroup>
        <Field data-invalid={!!errors.password}>
          <FieldLabel htmlFor="password">New password</FieldLabel>
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
          <FieldLabel htmlFor="passwordConfirmation">Confirm new password</FieldLabel>
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
          {isSubmitting ? 'Resetting…' : 'Reset password'}
        </Button>
      </FieldGroup>
    </form>
  )
}
