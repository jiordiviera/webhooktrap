'use client'

import { useForm } from 'react-hook-form'
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
import { type OtpValues } from '@/lib/schemas/auth'
import { verifyOtp } from '@/lib/api/otp'

type VerifyResetFormProps = {
  email: string
  onVerified: (resetToken: string) => void
}

export function VerifyResetForm({ email, onVerified }: VerifyResetFormProps) {
  const {
    watch,
    setValue,
    setError,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<OtpValues>({
    defaultValues: { otp: '' },
  })

  const otpValue = watch('otp')

  async function onSubmit(values: OtpValues) {
    try {
      const body = await verifyOtp(email, values.otp, 'password_reset')
      if (!body.data.reset_token) {
        setError('otp', { message: 'Invalid response. Try again.' })
        return
      }
      onVerified(body.data.reset_token)
    } catch (err) {
      if (err instanceof ApiError) {
        setError('otp', { message: err.message || 'Invalid code. Try again.' })
      } else {
        setError('otp', { message: 'Verification failed. Please try again.' })
      }
    }
  }

  return (
    <form onSubmit={handleSubmit(onSubmit)}>
      <FieldGroup>
        <Field>
          <FieldLabel htmlFor="reset-otp">Reset code</FieldLabel>
          <InputOTP
            pattern={REGEXP_ONLY_DIGITS}
            inputMode="numeric"
            id="reset-otp"
            maxLength={6}
            value={otpValue}
            onChange={(v) => setValue('otp', v, { shouldValidate: true })}
            onComplete={() => handleSubmit(onSubmit)()}
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
          <FieldError>{errors.otp?.message}</FieldError>
        </Field>

        <Button
          type="submit"
          className="font-ui h-10 w-full"
          disabled={isSubmitting || otpValue.length < 6}
        >
          {isSubmitting ? 'Verifying…' : 'Verify code'}
        </Button>
      </FieldGroup>
    </form>
  )
}
