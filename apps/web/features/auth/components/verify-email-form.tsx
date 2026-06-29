"use client";

import * as React from "react";
import { useState, useEffect } from "react";
import { useRouter } from "next/navigation";
import { zodResolver } from "@hookform/resolvers/zod";
import { Controller, useForm } from "react-hook-form";

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
import { challengeOtpSchema, type OtpValues } from "@/lib/schemas/auth";
import { requestOtp, verifyOtp } from "@/lib/api/otp";
import { useCountdown } from "@/hooks/use-countdown";

export function VerifyEmailForm() {
  const router = useRouter();
  const { user, setEmailVerified, status } = useAuth();
  const [otpSent, setOtpSent] = useState(false);
  const [resending, setResending] = useState(false);
  const countdown = useCountdown();

  const form = useForm<OtpValues>({
    resolver: zodResolver(challengeOtpSchema),
    defaultValues: {
      otp: "",
    },
  });

  const otpValue = form.watch("otp");

  useEffect(() => {
    if (status === "unauthenticated") {
      router.replace("/login?returnTo=/verify-email");
    }
  }, [status, router]);

  async function requestCode() {
    if (!user?.email) return;
    setResending(true);
    form.clearErrors();
    try {
      await requestOtp(user.email, "email_verify");
      form.reset({ otp: "" });
      setOtpSent(true);
      countdown.start(60);
    } catch (err) {
      if (err instanceof ApiError && err.status === 429) {
        form.reset({ otp: "" });
        countdown.start(err.body.retryAfter ?? 60);
        setOtpSent(true);
      } else {
        form.setError("otp", { message: "Failed to send code. Try again." });
      }
    } finally {
      setResending(false);
    }
  }

  async function onSubmit(values: OtpValues) {
    if (!user?.email) return;
    form.clearErrors();
    try {
      await verifyOtp(user.email, values.otp, "email_verify");
      setEmailVerified(true);
      router.push("/");
      router.refresh();
    } catch (err) {
      if (err instanceof ApiError) {
        form.setError("otp", { message: err.message || "Invalid code. Try again." });
      } else {
        form.setError("otp", { message: "Verification failed. Please try again." });
      }
    }
  }

  if (status === "loading") {
    return (
      <div className="flex flex-col gap-6">
        <p className="text-sm text-muted-foreground">Preparing verification...</p>
      </div>
    );
  }

  if (!user?.email) {
    return (
      <div className="flex flex-col gap-6">
        <p className="text-sm text-muted-foreground">
          You need to be signed in to verify your email.
        </p>
        <Button
          type="button"
          className="font-ui h-10 w-full"
          onClick={() => router.push("/login")}
        >
          Go to sign in
        </Button>
      </div>
    );
  }

  if (!otpSent) {
    return (
      <div className="flex flex-col gap-6">
        <p className="text-sm text-muted-foreground">
          We&apos;ll send a 6-digit code to{" "}
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
        {form.formState.errors.otp?.message && (
          <FieldError errors={[form.formState.errors.otp]} />
        )}
      </div>
    );
  }

  return (
    <div className="flex flex-col gap-6">
      <p className="text-sm text-muted-foreground">
        Enter the 6-digit code sent to{" "}
        <span className="font-medium text-foreground">{user?.email}</span>.
      </p>

      <form
        id="verify-email-form"
        onSubmit={form.handleSubmit(onSubmit)}
      >
        <FieldGroup>
          <Controller
            name="otp"
            control={form.control}
            render={({ field, fieldState }) => (
              <Field data-invalid={fieldState.invalid}>
                <FieldLabel htmlFor="verify-email-otp">
                  Verification code
                </FieldLabel>
                <InputOTP
                  pattern={REGEXP_ONLY_DIGITS}
                  inputMode="numeric"
                  id="verify-email-otp"
                  maxLength={6}
                  {...field}
                  onComplete={() => form.handleSubmit(onSubmit)()}
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
                {fieldState.invalid && (
                  <FieldError errors={[fieldState.error]} />
                )}
              </Field>
            )}
          />

          <Button
            type="submit"
            form="verify-email-form"
            className="font-ui h-10 w-full"
            disabled={
              form.formState.isSubmitting || otpValue.length < 6
            }
          >
            {form.formState.isSubmitting ? "Verifying…" : "Verify email"}
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
