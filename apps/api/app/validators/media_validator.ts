import vine from '@vinejs/vine'

export const attachFromUrlValidator = vine.compile(
  vine.object({
    model_type: vine.enum(['users', 'inboxes']),
    model_id: vine.string().trim(),
    collection: vine.enum(['avatar', 'icon', 'attachments']),
    url: vine.string().trim().url(),
  })
)
