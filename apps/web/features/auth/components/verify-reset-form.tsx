'use client'

import * as React from 'react'
import { zodResolver } from '@hookform/resolvers/zod'
import { Controller, useForm } from 'react-hook-form'
import { Button } from '@workspace/ui/components/button'
import {
  Field,
  FieldError,
  FieldGroup,
  FieldLabel,
} from '@workspace/ui/components/field'
import {
  InputOTP,
  InputOTPGroup,
  InputOTPSeparator,
  InputOTPSlot,
  REGEXP_ONLY_DIGITS,
} from '@workspace/ui/components/input-otp'
import { ApiError } from '@/lib/api'
import { challengeOtpSchema, type OtpValues } from '@/lib/schemas/auth'
import { verifyOtp } from '@/lib/api/otp'

type VerifyResetFormProps = {
  email: string
  onVerified: (resetToken: string) => void
}

export function VerifyResetForm({ email, onVerified }: VerifyResetFormProps) {
  const form = useForm<OtpValues>({
    resolver: zodResolver(challengeOtpSchema),
    defaultValues: { otp: '' },
  })

  async function onSubmit(values: OtpValues) {
    try {
      const body = await verifyOtp(email, values.otp, 'password_reset')
      if (!body.data.reset_token) {
        form.setError('otp', { message: 'Invalid response. Try again.' })
        return
      }
      onVerified(body.data.reset_token)
    } catch (err) {
      if (err instanceof ApiError) {
        form.setError('otp', { message: err.message || 'Invalid code. Try again.' })
      } else {
        form.setError('otp', { message: 'Verification failed. Please try again.' })
      }
    }
  }

  return (
    <form id="reset-otp-form" onSubmit={form.handleSubmit(onSubmit)}>
      <FieldGroup>
        <Controller
          name="otp"
          control={form.control}
          render={({ field, fieldState }) => (
            <Field data-invalid={fieldState.invalid}>
              <FieldLabel htmlFor="reset-otp">Reset code</FieldLabel>
              <InputOTP
                pattern={REGEXP_ONLY_DIGITS}
                inputMode="numeric"
                id="reset-otp"
                maxLength={6}
                {...field}
                onComplete={() => form.handleSubmit(onSubmit)()}
              >
                <InputOTPGroup>
                  {Array.from({ length: 3 }).map((_, i) => (
                    <InputOTPSlot key={i} index={i} className="size-12 text-2xl sm:size-16 sm:text-3xl" />
                  ))}
                </InputOTPGroup>
                <InputOTPSeparator />
                <InputOTPGroup>
                  {Array.from({ length: 3 }).map((_, i) => (
                    <InputOTPSlot key={i} index={i} className="size-12 text-2xl sm:size-16 sm:text-3xl" />
                  ))}
                </InputOTPGroup>
              </InputOTP>
              {fieldState.invalid && (
                <FieldError errors={[fieldState.error]} />
              )}
            </Field>
          )}
        />

        <Button
          type="submit"
          form="reset-otp-form"
          className="font-ui h-10 w-full"
          disabled={form.formState.isSubmitting || (form.watch('otp')?.length ?? 0) < 6}
        >
          {form.formState.isSubmitting ? 'Verifying…' : 'Verify code'}
        </Button>
      </FieldGroup>
    </form>
  )
}
