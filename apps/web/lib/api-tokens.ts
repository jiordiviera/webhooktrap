import { apiClient } from '@/lib/api/client'

export interface ApiToken {
  id: number
  name: string | null
  abilities: string[]
  createdAt: string | null
  lastUsedAt: string | null
  expiresAt: string | null
}

export interface CreatedApiToken extends ApiToken {
  token: string
}

export async function fetchApiTokens(): Promise<ApiToken[]> {
  const { data } = await apiClient.get<{ data: { tokens: ApiToken[] } }>('/api/v1/account/tokens')
  return data.data.tokens
}

export async function createApiToken(name: string): Promise<CreatedApiToken> {
  const { data } = await apiClient.post<{ data: CreatedApiToken }>('/api/v1/account/tokens', {
    name,
  })
  return data.data
}

export async function revokeApiToken(id: number): Promise<void> {
  await apiClient.delete(`/api/v1/account/tokens/${id}`)
}
