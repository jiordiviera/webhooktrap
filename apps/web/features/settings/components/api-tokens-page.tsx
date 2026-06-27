'use client'

export function ApiTokensPage() {
  return (
    <div className="mx-auto w-full max-w-5xl">
      <div className="mb-8">
        <p className="mb-2 text-[0.6875rem] font-medium tracking-[0.14em] text-primary uppercase">
          Développeurs
        </p>
        <h1 className="font-heading text-[clamp(1.75rem,3vw,2.25rem)] font-semibold tracking-wide text-foreground">
          API Tokens
        </h1>
        <p className="mt-2 max-w-xl text-[0.9375rem] leading-relaxed text-muted-foreground">
          Personal access tokens for programmatic access to your inboxes and events.
        </p>
      </div>

      <div className="rounded-2xl border border-dashed border-border px-6 py-16 text-center">
        <p className="text-base font-medium text-foreground">Coming soon</p>
        <p className="mx-auto mt-2 max-w-md text-sm text-muted-foreground">
          Create and manage API tokens for integrating Hookscope into your workflows.
        </p>
      </div>
    </div>
  )
}
