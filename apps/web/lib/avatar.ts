const FALLBACK_AVATAR = '/logo.png'

export function sanitizeAvatarUrl(avatar: string | null | undefined): string | null {
  if (!avatar) return null
  if (avatar.includes('/media-local') || avatar.includes('media-local/')) return null
  return avatar
}

export function resolveAvatarSrc(avatar: string | null | undefined): string {
  return sanitizeAvatarUrl(avatar) ?? FALLBACK_AVATAR
}