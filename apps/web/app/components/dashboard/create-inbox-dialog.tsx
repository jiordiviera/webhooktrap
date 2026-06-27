'use client'

import { zodResolver } from '@hookform/resolvers/zod'
import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { Button } from '@workspace/ui/components/button'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@workspace/ui/components/dialog'
import {
  Field,
  FieldError,
  FieldGroup,
  FieldLabel,
} from '@workspace/ui/components/field'
import { Input } from '@workspace/ui/components/input'
import { Loader } from '@workspace/ui/components/loader'
import { ApiError } from '@/lib/api'
import { createInbox, type InboxSummary } from '@/lib/inboxes'
import { type CreateInboxValues, createInboxSchema } from '@/lib/schemas/inbox'

type CreateInboxDialogProps = {
  open: boolean
  onOpenChange: (open: boolean) => void
  onCreated: (inbox: InboxSummary) => void
}

export function CreateInboxDialog({
  open,
  onOpenChange,
  onCreated,
}: CreateInboxDialogProps) {
  const [submitError, setSubmitError] = useState<string | null>(null)

  const {
    register,
    handleSubmit,
    reset,
    formState: { errors, isSubmitting },
  } = useForm<CreateInboxValues>({
    resolver: zodResolver(createInboxSchema),
    defaultValues: { name: '' },
  })

  async function onSubmit(values: CreateInboxValues) {
    setSubmitError(null)

    try {
      const inbox = await createInbox({ name: values.name.trim() })
      onCreated(inbox)
      reset()
      onOpenChange(false)
    } catch (error) {
      setSubmitError(
        error instanceof ApiError ? error.message : 'Could not create inbox. Try again.'
      )
    }
  }

  function handleOpenChange(next: boolean) {
    if (!next) {
      reset()
      setSubmitError(null)
    }
    onOpenChange(next)
  }

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>New inbox</DialogTitle>
          <DialogDescription>
            Name the integration you are debugging — Stripe, GitHub, Shopify, etc.
          </DialogDescription>
        </DialogHeader>

        <form onSubmit={handleSubmit(onSubmit)}>
          <FieldGroup>
            <Field>
              <FieldLabel htmlFor="inboxName">Title</FieldLabel>
              <Input
                id="inboxName"
                placeholder="Stripe checkout webhooks"
                autoFocus
                {...register('name')}
              />
              <FieldError>{errors.name?.message}</FieldError>
            </Field>
          </FieldGroup>

          {submitError ? (
            <p className="mt-3 text-sm text-destructive" role="alert">
              {submitError}
            </p>
          ) : null}

          <DialogFooter className="mt-6">
            <Button type="button" variant="outline" onClick={() => handleOpenChange(false)}>
              Cancel
            </Button>
            <Button type="submit" disabled={isSubmitting}>
              {isSubmitting ? (
                <>
                  <Loader size="sm" tone="inherit" />
                  Creating…
                </>
              ) : (
                'Create inbox'
              )}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}