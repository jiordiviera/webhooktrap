export interface InboxDTO {
  id: string
  name: string
  userId: number | null
  expiresAt: string | null
  ingestUrl: string
  defaultReplayUrl: string | null
  eventsCount: number
  lastEventAt: string | null
  createdAt: string
  updatedAt: string | null
}
