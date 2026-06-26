import { z } from 'zod'

export const createInboxSchema = z.object({
  name: z
    .string()
    .trim()
    .min(1, 'Give your inbox a name')
    .max(255, 'Name must be 255 characters or less'),
})

export type CreateInboxValues = z.infer<typeof createInboxSchema>