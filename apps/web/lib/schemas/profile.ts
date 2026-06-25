import { z } from 'zod'

export const profileSchema = z.object({
  fullName: z
    .string()
    .trim()
    .min(1, 'Name is required')
    .max(255, 'Name is too long'),
})

export type ProfileValues = z.infer<typeof profileSchema>