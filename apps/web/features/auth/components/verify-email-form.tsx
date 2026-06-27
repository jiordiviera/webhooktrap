"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { useForm } from "react-hook-form";
import { Button } from "@workspace/ui/components/button";
import {
  Field,
  FieldError,
  FieldGroup,
  FieldLabel,
} from "@workspace/ui/components/field";
import {
  InputOTP,
  InputOTPGroup,
  InputOTPSeparator,
  InputOTPSlot,
  REGEXP_ONLY_DIGITS,
} from "@workspace/ui/components/input-otp";
import { useAuth } from "@/contexts/auth-context";
import { ApiError } from "@/lib/api";
import { type OtpValues } from "@/lib/schemas/auth";
import { requestOtp, verifyOtp } from "@/lib/api/otp";
import { useCountdown } from "@/hooks/use-countdown";

export function VerifyEmailForm() {
  const router = useRouter();
  const { user, setEmailVerified } = useAuth();
  const [otpSent, setOtpSent] = useState(false);
  const [resending, setResending] = useState(false);
  const countdown = useCountdown();

  const {
    watch,
    setValue,
    setError,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<OtpValues>({
    defaultValues: { otp: "" },
  });

  const otpValue = watch("otp");

  async function requestCode() {
    if (!user?.email) return;
    setResending(true);
    try {
      await requestOtp(user.email, "email_verify");
      setOtpSent(true);
      countdown.start(60);
    } catch (err) {
      if (err instanceof ApiError && err.status === 429) {
        countdown.start(err.body.retryAfter ?? 60);
        setOtpSent(true);
      } else {
        setError("otp", { message: "Failed to send code. Try again." });
      }
    } finally {
      setResending(false);
    }
  }

  async function onSubmit(values: OtpValues) {
    if (!user?.email) return;
    try {
      await verifyOtp(user.email, values.otp, "email_verify");
      setEmailVerified(true);
      router.push("/");
      router.refresh();
    } catch (err) {
      if (err instanceof ApiError) {
        setError("otp", { message: err.message || "Invalid code. Try again." });
      } else {
        setError("otp", { message: "Verification failed. Please try again." });
      }
    }
  }

  if (!otpSent) {
    return (
      <div className="flex flex-col gap-6">
        <p className="text-sm text-muted-foreground">
          We'll send a 6-digit code to{" "}
          <span className="font-medium text-foreground">{user?.email}</span>.
        </p>
        <Button
          type="button"
          className="font-ui h-10 w-full"
          disabled={resending}
          onClick={requestCode}
        >
          {resending ? "Sending…" : "Send verification code"}
        </Button>
      </div>
    );
  }

  return (
    <div className="flex flex-col gap-6">
      <p className="text-sm text-muted-foreground">
        Enter the 6-digit code sent to{" "}
        <span className="font-medium text-foreground">{user?.email}</span>.
      </p>

      <form onSubmit={handleSubmit(onSubmit)}>
        <FieldGroup>
          <Field>
            <FieldLabel htmlFor="verify-email-otp">
              Verification code
            </FieldLabel>
            <InputOTP
              pattern={REGEXP_ONLY_DIGITS}
              inputMode="numeric"
              id="verify-email-otp"
              maxLength={6}
              value={otpValue}
              onChange={(v) => setValue("otp", v, { shouldValidate: true })}
              onComplete={() => handleSubmit(onSubmit)()}
            >
              <InputOTPGroup>
                {Array.from({ length: 3 }).map((_, i) => (
                  <InputOTPSlot
                    key={i}
                    index={i}
                    className="size-12 text-2xl sm:size-16 sm:text-3xl"
                  />
                ))}
              </InputOTPGroup>
              <InputOTPSeparator />
              <InputOTPGroup>
                {Array.from({ length: 3 }).map((_, i) => (
                  <InputOTPSlot
                    key={i + 3}
                    index={i + 3}
                    className="size-12 text-2xl sm:size-16 sm:text-3xl"
                  />
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
            {isSubmitting ? "Verifying…" : "Verify email"}
          </Button>
        </FieldGroup>
      </form>

      {countdown.isRunning ? (
        <p className="font-ui text-center text-sm text-muted-foreground">
          Resend in {countdown.remaining}s
        </p>
      ) : (
        <button
          type="button"
          onClick={requestCode}
          disabled={resending}
          className="font-ui text-center text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
        >
          {resending ? "Sending…" : "Resend code"}
        </button>
      )}
    </div>
  );
}
