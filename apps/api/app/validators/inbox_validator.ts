import vine from '@vinejs/vine'

const inboxName = () => vine.string().trim().minLength(1).maxLength(255)

export const createInboxValidator = vine.create(
  vine.object({
    name: inboxName(),
  })
)

export const updateInboxValidator = vine.create(
  vine.object({
    name: inboxName().optional(),
    default_replay_url: vine.string().url().maxLength(2048).nullable().optional(),
  })
)