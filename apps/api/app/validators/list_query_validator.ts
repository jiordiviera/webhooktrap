import vine from '@vinejs/vine'

export const listQueryValidator = vine.create(
  vine.object({
    page: vine.number().min(1).optional(),
    page_size: vine.number().min(1).max(100).optional(),
    sort: vine.string().trim().optional(),
    sort_dir: vine.enum(['asc', 'desc']).optional(),
    search: vine.string().trim().maxLength(255).optional(),
    filters: vine.record(vine.string().trim().maxLength(255)).optional(),
  })
)
