'use client'

import { zodResolver } from '@hookform/resolvers/zod'
import { useRef, useState } from 'react'
import { useForm } from 'react-hook-form'
import { IconCamera, IconCheck } from '@tabler/icons-react'
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
import { Separator } from '@workspace/ui/components/separator'
import { useAuth } from '@/contexts/auth-context'
import { useMediaUpload } from '@/hooks/use-media-upload'
import { ApiError } from '@/lib/api'
import { resolveAvatarSrc } from '@/lib/avatar'
import { updateProfile } from '@/lib/profile'
import { type ProfileValues, profileSchema } from '@/lib/schemas/profile'

function formatMemberSince(createdAt?: string) {
  if (!createdAt) return '—'
  return new Intl.DateTimeFormat('en', {
    month: 'long',
    year: 'numeric',
  }).format(new Date(createdAt))
}

export function ProfilePage() {
  const { user, token, setUser, refreshProfile } = useAuth()
  const { upload, isUploading, error: uploadError } = useMediaUpload()
  const fileInputRef = useRef<HTMLInputElement>(null)
  const [saveMessage, setSaveMessage] = useState<string | null>(null)
  const [saveError, setSaveError] = useState<string | null>(null)

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting, isDirty },
  } = useForm<ProfileValues>({
    resolver: zodResolver(profileSchema),
    values: {
      fullName: user?.fullName?.trim() || user?.email.split('@')[0] || '',
    },
  })

  if (!user || !token) return null

  const displayName = user.fullName ?? user.email
  const avatarSrc = resolveAvatarSrc(user.avatar)

  async function onSubmit(values: ProfileValues) {
    setSaveMessage(null)
    setSaveError(null)

    try {
      const updated = await updateProfile(token!, { fullName: values.fullName })
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

  return (
    <div className="mx-auto flex w-full max-w-3xl flex-col gap-8">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight text-foreground">Profile</h1>
        <p className="mt-1 text-sm text-muted-foreground">
          Manage your display name and avatar across Hookscope.
        </p>
      </div>

      <section className="rounded-2xl border border-border bg-card">
        <div className="border-b border-border px-6 py-5">
          <h2 className="text-sm font-semibold text-foreground">Public profile</h2>
          <p className="mt-1 text-sm text-muted-foreground">
            Shown in the dashboard header and anywhere your account appears.
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
                  {isUploading ? 'Uploading…' : 'Change photo'}
                </Button>
              </div>
              {uploadError ? <p className="text-sm text-destructive">{uploadError}</p> : null}
            </div>
          </div>

          <Separator />

          <form onSubmit={handleSubmit(onSubmit)} className="space-y-6">
            <FieldGroup>
              <Field>
                <FieldLabel htmlFor="fullName">Display name</FieldLabel>
                <Input id="fullName" autoComplete="name" {...register('fullName')} />
                <FieldDescription>Used in the sidebar, header, and shared views.</FieldDescription>
                <FieldError>{errors.fullName?.message}</FieldError>
              </Field>

              <Field>
                <FieldLabel htmlFor="email">Email</FieldLabel>
                <Input id="email" value={user.email} disabled readOnly />
                <FieldDescription>Email changes are not supported yet.</FieldDescription>
              </Field>
            </FieldGroup>

            <div className="flex flex-wrap items-center gap-3">
              <Button type="submit" disabled={isSubmitting || !isDirty}>
                {isSubmitting ? 'Saving…' : 'Save changes'}
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
        <h2 className="text-sm font-semibold text-foreground">Account</h2>
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
  )
}