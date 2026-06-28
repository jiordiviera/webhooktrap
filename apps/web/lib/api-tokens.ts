import type { ApiTokenDTO, ApiTokenCreatedDTO } from '@workspace/types'
import { apiClient } from '@/lib/api/client'

export type ApiToken = ApiTokenDTO
export type CreatedApiToken = ApiTokenCreatedDTO

export async function fetchApiTokens(): Promise<ApiTokenDTO[]> {
  const { data } = await apiClient.get<{ data: { tokens: ApiTokenDTO[] } }>('/account/tokens')
  return data.data.tokens
}

export async function createApiToken(name: string): Promise<ApiTokenCreatedDTO> {
  const { data } = await apiClient.post<{ data: ApiTokenCreatedDTO }>('/account/tokens', {
    name,
  })
  return data.data
}

export async function revokeApiToken(id: number): Promise<void> {
  await apiClient.delete(`/account/tokens/${id}`)
}
