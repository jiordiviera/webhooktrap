'use client'

import { useState } from 'react'
import Link from 'next/link'
import { Button } from '@workspace/ui/components/button'
import { CreateInboxChoiceDialog } from '@/app/components/create-inbox-choice-dialog'

export function CreateInboxCta({ showSecondaryLink = true }: { showSecondaryLink?: boolean }) {
  const [dialogOpen, setDialogOpen] = useState(false)

  return (
    <div className="flex flex-col gap-5">
      <div className="flex flex-wrap items-center gap-4">
        <Button
          size="lg"
          className="h-11 rounded-full px-6 text-[0.9375rem]"
          onClick={() => setDialogOpen(true)}
        >
          Open a free inbox
        </Button>

        {showSecondaryLink && (
          <Button variant="link" asChild className="text-muted-foreground">
            <Link href="#flow">See how it works</Link>
          </Button>
        )}
      </div>

      <CreateInboxChoiceDialog open={dialogOpen} onOpenChangeAction={setDialogOpen} />
    </div>
  )
}
