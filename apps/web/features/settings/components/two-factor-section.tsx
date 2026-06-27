'use client'

import { useCallback, useEffect, useRef, useState } from 'react'
import Image from 'next/image'
import { IconShieldCheck, IconShieldLock, IconKey, IconCopy, IconCheck } from '@tabler/icons-react'
import QRCode from 'qrcode'
import { Button } from '@workspace/ui/components/button'
import { Input } from '@workspace/ui/components/input'
import { Field, FieldError, FieldGroup, FieldLabel } from '@workspace/ui/components/field'
import { Loader } from '@workspace/ui/components/loader'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@workspace/ui/components/dialog'
import { useAuth } from '@/contexts/auth-context'
import { ApiError } from '@/lib/api'
import {
  generateTwoFactorSecret,
  verifyTwoFactorOtp,
  generateRecoveryCodes,
  disableTwoFactor,
  type TwoFactorSecret,
} from '@/lib/2fa'

type Step =
  | 'idle'
  | 'generating'
  | 'verify'
  | 'success'
  | 'confirm_disable'
  | 'recovery_loading'
  | 'recovery_display'

export function TwoFactorSection() {
  const { user, refreshProfile } = useAuth()
  const [step, setStep] = useState<Step>('idle')
  const [secret, setSecret] = useState<TwoFactorSecret | null>(null)
  const [qrDataUrl, setQrDataUrl] = useState<string | null>(null)
  const [otp, setOtp] = useState('')
  const [otpError, setOtpError] = useState<string | null>(null)
  const [recoveryCodes, setRecoveryCodes] = useState<string[]>([])
  const [copiedIndex, setCopiedIndex] = useState<number | null>(null)
  const [isVerifying, setIsVerifying] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [actionError, setActionError] = useState<string | null>(null)
  const otpInputRef = useRef<HTMLInputElement>(null)

  const isEnabled = user?.isTwoFactorEnabled ?? false

  useEffect(() => {
    if (step === 'verify') {
      const t = setTimeout(() => otpInputRef.current?.focus(), 100)
      return () => clearTimeout(t)
    }
  }, [step])

  const handleGenerate = useCallback(async () => {
    setStep('generating')
    setError(null)
    try {
      const result = await generateTwoFactorSecret()
      setSecret(result)

      const url = await QRCode.toDataURL(result.uri, {
        width: 256,
        margin: 2,
        color: {
          dark: '#1a1a1a',
          light: '#ffffff',
        },
      })
      setQrDataUrl(url)
      setStep('verify')
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Failed to generate 2FA secret')
      setStep('idle')
    }
  }, [])

  const handleVerify = useCallback(
    async (e: React.FormEvent) => {
      e.preventDefault()
      if (!otp.trim()) return

      setIsVerifying(true)
      setOtpError(null)
      try {
        await verifyTwoFactorOtp(otp.trim())
        setIsVerifying(false)
        setStep('success')
        void refreshProfile()
        try {
          const codes = await generateRecoveryCodes()
          setRecoveryCodes(codes.recovery_codes)
        } catch {
          /* user can regenerate later */
        }
      } catch (err) {
        setIsVerifying(false)
        if (err instanceof ApiError && err.status === 422) {
          setOtpError('Invalid code. Try again.')
        } else {
          setOtpError(err instanceof ApiError ? err.message : 'Verification failed')
        }
      }
    },
    [otp, refreshProfile]
  )

  const handleDisable = useCallback(async () => {
    setActionError(null)
    try {
      await disableTwoFactor()
      setStep('idle')
      setSecret(null)
      setQrDataUrl(null)
      setOtp('')
      setOtpError(null)
      setRecoveryCodes([])
      void refreshProfile()
    } catch (err) {
      setActionError(err instanceof ApiError ? err.message : 'Failed to disable 2FA')
    }
  }, [refreshProfile])

  const handleShowRecovery = useCallback(async () => {
    setStep('recovery_loading')
    setError(null)
    try {
      const codes = await generateRecoveryCodes()
      setRecoveryCodes(codes.recovery_codes)
      setStep('recovery_display')
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Failed to generate recovery codes')
      setStep('idle')
    }
  }, [])

  const handleCopyCode = useCallback(async (code: string, index: number) => {
    await navigator.clipboard.writeText(code)
    setCopiedIndex(index)
    setTimeout(() => setCopiedIndex(null), 2000)
  }, [])

  const handleCopyAll = useCallback(async () => {
    await navigator.clipboard.writeText(recoveryCodes.join('\n'))
    setCopiedIndex(-1)
    setTimeout(() => setCopiedIndex(null), 2000)
  }, [recoveryCodes])

  const handleCloseDisable = useCallback(() => {
    setStep('idle')
    setActionError(null)
  }, [])

  const handleCloseRecovery = useCallback(() => {
    setStep('idle')
    setRecoveryCodes([])
  }, [])

  if (!user) return null

  return (
    <section className="rounded-2xl border border-border bg-card">
      <div className="border-b border-border px-6 py-5">
        <h2 className="flex items-center gap-2 text-sm font-semibold text-foreground">
          <IconShieldLock className="size-4" stroke={1.8} />
          Two-factor authentication
        </h2>
        <p className="mt-1 text-sm text-muted-foreground">
          Add an extra layer of security to your account.
        </p>
      </div>

      <div className="px-6 py-5">
        <div className="mb-4 flex items-center gap-3">
          {isEnabled ? (
            <>
              <IconShieldCheck className="size-5 text-(--signal-green)" stroke={2} />
              <div>
                <p className="text-sm font-medium text-foreground">Enabled</p>
                <p className="text-xs text-muted-foreground">
                  Your account is protected by two-factor authentication.
                </p>
              </div>
            </>
          ) : (
            <>
              <IconShieldLock className="size-5 text-muted-foreground" stroke={1.5} />
              <div>
                <p className="text-sm font-medium text-foreground">Not configured</p>
                <p className="text-xs text-muted-foreground">
                  Strengthen your account security by enabling 2FA.
                </p>
              </div>
            </>
          )}
        </div>

        {step === 'generating' && (
          <div className="flex items-center gap-2 py-2">
            <Loader className="size-4 text-muted-foreground" />
            <span className="text-sm text-muted-foreground">Generating secret…</span>
          </div>
        )}

        {step === 'verify' && secret && qrDataUrl && (
          <div className="space-y-4">
            <div className="flex justify-center">
              <Image
                src={qrDataUrl}
                alt="QR code for authenticator app"
                width={256}
                height={256}
                className="rounded-lg border border-border"
                unoptimized
              />
            </div>

            <div className="text-center">
              <p className="text-xs text-muted-foreground">
                Scan this QR code with your authenticator app (e.g. Google Authenticator, 1Password).
              </p>
            </div>

            <details className="group">
              <summary className="cursor-pointer text-xs text-muted-foreground hover:text-foreground">
                Can&apos;t scan the code?
              </summary>
              <div className="mt-2 rounded-lg border border-border bg-muted px-3 py-2">
                <p className="mb-1 text-xs text-muted-foreground">Setup key:</p>
                <code className="select-all text-xs text-foreground break-all">{secret.secret}</code>
              </div>
            </details>

            <form onSubmit={handleVerify}>
              <FieldGroup>
                <Field>
                  <FieldLabel htmlFor="2fa-otp">Authentication code</FieldLabel>
                  <div className="flex items-start gap-3">
                    <div className="flex-1">
                      <Input
                        ref={otpInputRef}
                        id="2fa-otp"
                        placeholder="000 000"
                        value={otp}
                        onChange={(e) => {
                          setOtp(e.target.value.replace(/\D/g, '').slice(0, 6))
                          setOtpError(null)
                        }}
                        maxLength={6}
                        className="font-mono tracking-[0.3em] text-center"
                      />
                      <FieldError>{otpError}</FieldError>
                    </div>
                    <Button type="submit" disabled={isVerifying || otp.length < 6}>
                      {isVerifying ? 'Verifying…' : 'Verify'}
                    </Button>
                  </div>
                </Field>
              </FieldGroup>
            </form>

            <Button
              variant="ghost"
              size="sm"
              onClick={() => {
                setStep('idle')
                setSecret(null)
                setQrDataUrl(null)
                setOtp('')
                setOtpError(null)
              }}
            >
              Cancel
            </Button>
          </div>
        )}

        {step === 'success' && (
          <div className="space-y-4 py-2">
            <div className="flex items-center gap-2">
              <IconShieldCheck className="size-5 text-(--signal-green)" stroke={2} />
              <span className="text-sm font-medium text-foreground">
                Two-factor authentication is now enabled.
              </span>
            </div>

            {recoveryCodes.length > 0 && (
              <div className="rounded-lg border border-border bg-muted p-4">
                <p className="mb-1 text-sm font-medium text-foreground">Recovery codes</p>
                <p className="mb-3 text-xs text-muted-foreground">
                  Save these codes in a safe place. Each code can be used once to regain access if
                  you lose your authenticator device.
                </p>

                <div className="grid grid-cols-2 gap-1.5">
                  {recoveryCodes.map((code, i) => (
                    <div
                      key={i}
                      className="flex items-center justify-between rounded border border-border bg-card px-3 py-1.5"
                    >
                      <code className="select-all font-mono text-xs text-foreground">{code}</code>
                      <button
                        type="button"
                        onClick={() => handleCopyCode(code, i)}
                        className="ml-1 shrink-0 text-muted-foreground hover:text-foreground"
                        aria-label={`Copy code ${code}`}
                      >
                        {copiedIndex === i ? (
                          <IconCheck className="size-3.5 text-(--signal-green)" stroke={2} />
                        ) : (
                          <IconCopy className="size-3.5" stroke={1.5} />
                        )}
                      </button>
                    </div>
                  ))}
                </div>

                <Button variant="outline" size="sm" className="mt-3" onClick={handleCopyAll}>
                  {copiedIndex === -1 ? (
                    <>
                      <IconCheck className="mr-1.5 size-3.5 text-(--signal-green)" stroke={2} />
                      Copied
                    </>
                  ) : (
                    <>
                      <IconCopy className="mr-1.5 size-3.5" stroke={1.5} />
                      Copy all
                    </>
                  )}
                </Button>
              </div>
            )}

            <Button
              variant="ghost"
              size="sm"
              onClick={() => {
                setStep('idle')
                setSecret(null)
                setQrDataUrl(null)
                setOtp('')
                setOtpError(null)
                setRecoveryCodes([])
              }}
            >
              Done
            </Button>
          </div>
        )}

        {step === 'idle' && (
          <div className="flex flex-wrap gap-3">
            {isEnabled ? (
              <>
                <Button variant="outline" size="sm" onClick={handleShowRecovery}>
                  <IconKey className="mr-1.5 size-4" stroke={1.8} />
                  Recovery codes
                </Button>
                <Button
                  variant="outline"
                  size="sm"
                  className="text-destructive hover:text-destructive"
                  onClick={() => setStep('confirm_disable')}
                >
                  Disable 2FA
                </Button>
              </>
            ) : (
              <Button size="sm" onClick={handleGenerate}>
                Enable 2FA
              </Button>
            )}
          </div>
        )}

        {error && step === 'idle' && (
          <p className="mt-3 text-xs text-destructive">{error}</p>
        )}

        {step === 'recovery_loading' && (
          <div className="flex items-center gap-2 py-1">
            <Loader className="size-4 text-muted-foreground" />
            <span className="text-sm text-muted-foreground">Generating recovery codes…</span>
          </div>
        )}

        {step === 'recovery_display' && recoveryCodes.length > 0 && (
          <div className="mt-2 rounded-lg border border-border bg-muted p-4">
            <p className="mb-1 text-sm font-medium text-foreground">Recovery codes</p>
            <p className="mb-3 text-xs text-muted-foreground">
              Each code can be used once. Save them somewhere safe.
            </p>

            <div className="grid grid-cols-2 gap-1.5">
              {recoveryCodes.map((code, i) => (
                <div
                  key={i}
                  className="flex items-center justify-between rounded border border-border bg-card px-3 py-1.5"
                >
                  <code className="select-all font-mono text-xs text-foreground">{code}</code>
                  <button
                    type="button"
                    onClick={() => handleCopyCode(code, i)}
                    className="ml-1 shrink-0 text-muted-foreground hover:text-foreground"
                    aria-label={`Copy code ${code}`}
                  >
                    {copiedIndex === i ? (
                      <IconCheck className="size-3.5 text-(--signal-green)" stroke={2} />
                    ) : (
                      <IconCopy className="size-3.5" stroke={1.5} />
                    )}
                  </button>
                </div>
              ))}
            </div>

            <Button variant="outline" size="sm" className="mt-3" onClick={handleCopyAll}>
              {copiedIndex === -1 ? (
                <>
                  <IconCheck className="mr-1.5 size-3.5 text-(--signal-green)" stroke={2} />
                  Copied
                </>
              ) : (
                <>
                  <IconCopy className="mr-1.5 size-3.5" stroke={1.5} />
                  Copy all
                </>
              )}
            </Button>

            <Button variant="ghost" size="sm" className="ml-2" onClick={handleCloseRecovery}>
              Close
            </Button>
          </div>
        )}
      </div>

      <Dialog
        open={step === 'confirm_disable'}
        onOpenChange={(open) => {
          if (!open) {
            setStep('idle')
            setActionError(null)
          }
        }}
      >
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Disable two-factor authentication</DialogTitle>
            <DialogDescription>
              This will make your account less secure. Only proceed if you are sure.
            </DialogDescription>
          </DialogHeader>

          {actionError && <p className="text-sm text-destructive">{actionError}</p>}

          <p className="text-sm text-muted-foreground">
            Are you sure you want to disable two-factor authentication?
          </p>

          <DialogFooter>
            <Button variant="outline" onClick={handleCloseDisable}>
              Cancel
            </Button>
            <Button variant="destructive" onClick={handleDisable}>
              Disable
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </section>
  )
}
