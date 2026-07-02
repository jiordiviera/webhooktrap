'use client'

import { Button } from '@workspace/ui/components/button'
import { GithubLight } from '@workspace/ui/components/svgs/githubLight'
import { Google } from '@workspace/ui/components/ui/svgs/google'
import { getOAuthRedirectUrl } from '@/lib/auth'

type OAuthButtonsProps = {
  returnTo?: string
}

export function OAuthButtons({ returnTo }: OAuthButtonsProps) {
  const redirectBase = returnTo ?? (typeof window !== 'undefined' ? window.location.origin : '')

  function startOAuth(provider: 'github' | 'google') {
    window.location.href = getOAuthRedirectUrl(provider, redirectBase)
  }

  return (
    <div className="grid gap-2">
      <Button
        type="button"
        variant="outline"
        className="h-10 w-full justify-center gap-2.5"
        onClick={() => startOAuth('github')}
      >
        <GithubLight className="size-4" aria-hidden />
        Continue with GitHub
      </Button>
      <Button
        type="button"
        variant="outline"
        className="h-10 w-full justify-center gap-2.5"
        onClick={() => startOAuth('google')}
      >
        <Google className="size-4" aria-hidden />
        Continue with Google
      </Button>
    </div>
  )
}