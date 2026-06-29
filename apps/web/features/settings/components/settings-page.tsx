'use client'

import * as React from 'react'
import { useCallback, useEffect, useRef, useState } from 'react'
import { productName } from '@/lib/config'
import { zodResolver } from '@hookform/resolvers/zod'
import { Controller, useForm } from 'react-hook-form'
import {
  IconBell,
  IconBuilding,
  IconCamera,
  IconCheck,
  IconShieldLock,
} from '@tabler/icons-react'
import { Avatar, AvatarFallback, AvatarImage } from '@workspace/ui/components/avatar'
import { Button } from '@workspace/ui/components/button'
import {
  Field,
  FieldDescription,
  FieldError,
  FieldGroup,
  FieldLabel,
} from '@workspace/ui/components/field'
import { Input } from '@workspace/ui/components/input'
import { useAuth } from '@/contexts/auth-context'
import { useMediaUpload } from '@/hooks/use-media-upload'
import { ApiError } from '@/lib/api'
import { resolveAvatarSrc } from '@/lib/avatar'
import { updateProfile } from '@/lib/profile'
import { type ProfileValues, profileSchema } from '@/lib/schemas/profile'
import { TwoFactorSection } from './two-factor-section'

type SectionId = 'account' | 'security' | 'notifications'

interface Section {
  id: SectionId
  label: string
  icon: typeof IconBuilding
}

const sections: Section[] = [
  { id: 'account', label: 'Account', icon: IconBuilding },
  { id: 'security', label: 'Security', icon: IconShieldLock },
  { id: 'notifications', label: 'Notifications', icon: IconBell },
]

function formatMemberSince(createdAt?: string) {
  if (!createdAt) return '\u2014'
  return new Intl.DateTimeFormat('en', {
    month: 'long',
    year: 'numeric',
  }).format(new Date(createdAt))
}

export function SettingsPage() {
  const { user, setUser, refreshProfile } = useAuth()
  const { upload, isUploading, error: uploadError } = useMediaUpload()
  const fileInputRef = useRef<HTMLInputElement>(null)
  const [activeSection, setActiveSection] = useState<SectionId>('account')
  const [saveMessage, setSaveMessage] = useState<string | null>(null)
  const [saveError, setSaveError] = useState<string | null>(null)

  const form = useForm<ProfileValues>({
    resolver: zodResolver(profileSchema),
    values: {
      fullName: user?.fullName?.trim() || user?.email.split('@')[0] || '',
    },
  })

  useEffect(() => {
    const hash = window.location.hash.replace('#', '') as SectionId
    if (hash && sections.some((s) => s.id === hash)) {
      setActiveSection(hash)
    }
  }, [])

  const handleNavClick = useCallback((sectionId: SectionId) => {
    setActiveSection(sectionId)
    window.history.replaceState(null, '', `#${sectionId}`)
  }, [])

  async function onSubmit(values: ProfileValues) {
    setSaveMessage(null)
    setSaveError(null)
    try {
      const updated = await updateProfile({ fullName: values.fullName })
      setUser(updated)
      setSaveMessage('Profile updated.')
    } catch (error) {
      setSaveError(error instanceof ApiError ? error.message : 'Could not save profile.')
    }
  }

  async function onAvatarSelected(file: File | undefined) {
    if (!file || !user) return
    setSaveMessage(null)
    setSaveError(null)
    const media = await upload({
      modelType: 'users',
      modelId: String(user.id),
      collection: 'avatar',
      file,
    })
    if (media) {
      await refreshProfile()
      setSaveMessage('Avatar updated.')
    }
  }

  if (!user) return null

  const displayName = user.fullName ?? user.email
  const avatarSrc = resolveAvatarSrc(user.avatar)

  return (
    <div className="mx-auto w-full max-w-5xl">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight text-foreground">Settings</h1>
        <p className="mt-1 text-sm text-muted-foreground">
          Manage your account, security, and notification preferences.
        </p>
      </div>

      <select
        value={activeSection}
        onChange={(e) => handleNavClick(e.target.value as SectionId)}
        className="sm:hidden mt-6 w-full rounded-lg border border-border bg-card px-3 py-2.5 text-sm font-medium text-foreground outline-none focus:ring-2 focus:ring-ring"
      >
        {sections.map((section) => (
          <option key={section.id} value={section.id}>
            {section.label}
          </option>
        ))}
      </select>

      <div className="mt-4 flex gap-10 lg:gap-14 sm:mt-8">
        <nav className="sticky top-24 hidden h-fit w-44 shrink-0 space-y-1 sm:block">
          {sections.map((section) => {
            const Icon = section.icon
            return (
              <button
                key={section.id}
                type="button"
                onClick={() => handleNavClick(section.id)}
                data-active={activeSection === section.id}
                className="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-left text-sm font-medium transition-colors data-[active=true]:bg-secondary data-[active=true]:text-foreground data-[active=false]:text-muted-foreground hover:text-foreground"
              >
                <Icon className="size-4 shrink-0" stroke={1.8} />
                {section.label}
              </button>
            )
          })}
        </nav>

        <div className="min-w-0 flex-1">
          {activeSection === 'account' && (
            <div className="space-y-8">
              <section className="rounded-2xl border border-border bg-card">
                <div className="border-b border-border px-6 py-5">
                  <h2 className="flex items-center gap-2 text-sm font-semibold text-foreground">
                    <IconBuilding className="size-4" stroke={1.8} />
                    Account
                  </h2>
                  <p className="mt-1 text-sm text-muted-foreground">
                    Manage your display name and avatar across {productName}.
                  </p>
                </div>

                <div className="flex flex-col gap-8 px-6 py-6">
                  <div className="flex flex-col gap-5 sm:flex-row sm:items-center">
                    <Avatar className="size-20 rounded-2xl ring-1 ring-border">
                      <AvatarImage src={avatarSrc} alt={displayName} className="object-cover" />
                      <AvatarFallback className="rounded-2xl text-lg font-semibold">
                        {user.initials}
                      </AvatarFallback>
                    </Avatar>

                    <div className="flex flex-col gap-3">
                      <div>
                        <p className="text-sm font-medium text-foreground">Profile photo</p>
                        <p className="text-sm text-muted-foreground">JPG, PNG or WebP. Max 2 MB.</p>
                      </div>
                      <div className="flex flex-wrap items-center gap-3">
                        <input
                          ref={fileInputRef}
                          type="file"
                          accept="image/jpeg,image/png,image/webp"
                          className="sr-only"
                          onChange={(event) => {
                            void onAvatarSelected(event.target.files?.[0])
                            event.target.value = ''
                          }}
                        />
                        <Button
                          type="button"
                          variant="outline"
                          size="sm"
                          disabled={isUploading}
                          onClick={() => fileInputRef.current?.click()}
                        >
                          <IconCamera className="size-4" stroke={1.8} aria-hidden />
                          {isUploading ? 'Uploading\u2026' : 'Change photo'}
                        </Button>
                      </div>
                      {uploadError ? <p className="text-sm text-destructive">{uploadError}</p> : null}
                    </div>
                  </div>

                  <form id="profile-form" onSubmit={form.handleSubmit(onSubmit)} className="space-y-6">
                    <FieldGroup>
                      <Controller
                        name="fullName"
                        control={form.control}
                        render={({ field, fieldState }) => (
                          <Field data-invalid={fieldState.invalid}>
                            <FieldLabel htmlFor="fullName">Display name</FieldLabel>
                            <Input id="fullName" autoComplete="name" {...field} />
                            <FieldDescription>
                              Used in the sidebar, header, and shared views.
                            </FieldDescription>
                            {fieldState.invalid && (
                              <FieldError errors={[fieldState.error]} />
                            )}
                          </Field>
                        )}
                      />

                      <Field>
                        <FieldLabel htmlFor="email">Email</FieldLabel>
                        <Input id="email" value={user.email} disabled readOnly />
                        <FieldDescription>
                          Email changes are not supported yet.
                        </FieldDescription>
                      </Field>
                    </FieldGroup>

                    <div className="flex flex-wrap items-center gap-3">
                      <Button
                        type="submit"
                        form="profile-form"
                        disabled={form.formState.isSubmitting || !form.formState.isDirty}
                      >
                        {form.formState.isSubmitting ? 'Saving\u2026' : 'Save changes'}
                      </Button>
                      {saveMessage ? (
                        <p className="inline-flex items-center gap-1.5 text-sm text-primary">
                          <IconCheck className="size-4" aria-hidden />
                          {saveMessage}
                        </p>
                      ) : null}
                      {saveError ? <p className="text-sm text-destructive">{saveError}</p> : null}
                    </div>
                  </form>
                </div>
              </section>

              <section className="rounded-2xl border border-border bg-card px-6 py-5">
                <h2 className="text-sm font-semibold text-foreground">Details</h2>
                <dl className="mt-4 grid gap-4 sm:grid-cols-2">
                  <div>
                    <dt className="text-[0.6875rem] font-medium tracking-[0.12em] text-muted-foreground uppercase">
                      Member since
                    </dt>
                    <dd className="mt-1 text-sm text-foreground">
                      {formatMemberSince(user.createdAt)}
                    </dd>
                  </div>
                  <div>
                    <dt className="text-[0.6875rem] font-medium tracking-[0.12em] text-muted-foreground uppercase">
                      User ID
                    </dt>
                    <dd className="mt-1 font-mono text-sm text-foreground">{user.id}</dd>
                  </div>
                </dl>
              </section>
            </div>
          )}

          {activeSection === 'security' && (
            <TwoFactorSection />
          )}

          {activeSection === 'notifications' && (
            <section className="rounded-2xl border border-border bg-card">
              <div className="border-b border-border px-6 py-5">
                <h2 className="flex items-center gap-2 text-sm font-semibold text-foreground">
                  <IconBell className="size-4" stroke={1.8} />
                  Notifications
                </h2>
                <p className="mt-1 text-sm text-muted-foreground">
                  Configure how and when you hear from {productName}.
                </p>
              </div>
              <div className="px-6 py-12 text-center">
                <IconBell className="mx-auto mb-3 size-8 text-muted-foreground" stroke={1.5} />
                <p className="text-sm text-muted-foreground">Coming soon.</p>
              </div>
            </section>
          )}
        </div>
      </div>
    </div>
  )
}
