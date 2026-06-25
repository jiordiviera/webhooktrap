import { apiFetch } from '@/lib/api'
import { type AuthUser, normalizeAuthUser } from '@/lib/auth'

type ProfileResponse = {
  data: AuthUser
}

export async function fetchProfile(token: string): Promise<AuthUser> {
  const body = await apiFetch<ProfileResponse>('/api/v1/account/profile', { token })
  return normalizeAuthUser(body.data)
}

export async function updateProfile(
  token: string,
  payload: { fullName: string }
): Promise<AuthUser> {
  const body = await apiFetch<ProfileResponse>('/api/v1/account/profile', {
    method: 'PATCH',
    token,
    body: JSON.stringify(payload),
  })
  return normalizeAuthUser(body.data)
}