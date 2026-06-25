export function resolvePublicMediaUrl(url: string | null | undefined): string | null {
  if (!url) return null
  if (url.includes('/media-local') || url.includes('media-local/')) return null
  return url
}
