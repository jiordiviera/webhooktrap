export interface EventSummaryDTO {
  id: string
  inboxId: string
  method: string
  path: string
  contentType: string | null
  sizeBytes: number
  receivedAt: string
}

export interface EventDetailDTO extends EventSummaryDTO {
  query: Record<string, string>
  headers: Record<string, string | string[]>
  bodyText: string | null
  bodyJson: Record<string, unknown> | unknown[] | null
  ip: string | null
  createdAt: string
}

export interface ReplayDTO {
  id: string
  eventId: string
  targetUrl: string
  statusCode: number | null
  responseHeaders: Record<string, string> | null
  responseBody: string | null
  durationMs: number | null
  errorCode: string | null
  errorMessage: string | null
  createdAt: string
}
