export interface ApiTokenDTO {
  id: number
  name: string | null
  abilities: string[]
  createdAt: string | null
  lastUsedAt: string | null
  expiresAt: string | null
}

export interface ApiTokenCreatedDTO extends ApiTokenDTO {
  token: string
}
