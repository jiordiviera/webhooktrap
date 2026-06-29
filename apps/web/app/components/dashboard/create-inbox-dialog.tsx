'use client'

import * as React from 'react'
import { zodResolver } from '@hookform/resolvers/zod'
import { Controller, useForm } from 'react-hook-form'
import { useState } from 'react'
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
  onOpenChangeAction: (open: boolean) => void
  onCreatedAction: (inbox: InboxSummary) => void
}

export function CreateInboxDialog({
  open,
  onOpenChangeAction,
  onCreatedAction,
}: CreateInboxDialogProps) {
  const [submitError, setSubmitError] = useState<string | null>(null)

  const form = useForm<CreateInboxValues>({
    resolver: zodResolver(createInboxSchema),
    defaultValues: { name: '' },
  })

  async function onSubmit(values: CreateInboxValues) {
    setSubmitError(null)

    try {
      const inbox = await createInbox({ name: values.name.trim() })
      onCreatedAction(inbox)
      form.reset()
      onOpenChangeAction(false)
    } catch (error) {
      setSubmitError(
        error instanceof ApiError ? error.message : 'Could not create inbox. Try again.'
      )
    }
  }

  function handleOpenChange(next: boolean) {
    if (!next) {
      form.reset()
      setSubmitError(null)
    }
    onOpenChangeAction(next)
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

        <form id="create-inbox-form" onSubmit={form.handleSubmit(onSubmit)}>
          <FieldGroup>
            <Controller
              name="name"
              control={form.control}
              render={({ field, fieldState }) => (
                <Field data-invalid={fieldState.invalid}>
                  <FieldLabel htmlFor="inboxName">Title</FieldLabel>
                  <Input
                    id="inboxName"
                    placeholder="Stripe checkout webhooks"
                    autoFocus
                    {...field}
                  />
                  {fieldState.invalid && (
                    <FieldError errors={[fieldState.error]} />
                  )}
                </Field>
              )}
            />
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
            <Button
              type="submit"
              form="create-inbox-form"
              disabled={form.formState.isSubmitting}
            >
              {form.formState.isSubmitting ? (
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