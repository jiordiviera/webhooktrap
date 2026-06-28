import { apiFetch } from '@/lib/api'
import { type AuthUser, normalizeAuthUser } from '@/lib/auth'

type ProfileResponse = {
  data: AuthUser
}

export async function fetchProfile(): Promise<AuthUser> {
  const body = await apiFetch<ProfileResponse>('/account/profile')
  return normalizeAuthUser(body.data)
}

export async function updateProfile(
  payload: { fullName: string }
): Promise<AuthUser> {
  const body = await apiFetch<ProfileResponse>('/account/profile', {
    method: 'PATCH',
    body: JSON.stringify(payload),
  })
  return normalizeAuthUser(body.data)
}