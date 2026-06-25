import { serializeUserProfile } from '#support/user_profile_payload'
import { updateProfileValidator } from '#validators/user'
import type { HttpContext } from '@adonisjs/core/http'

export default class ProfileController {
  async show({ auth, serialize }: HttpContext) {
    const user = auth.getUserOrFail()
    return serialize(await serializeUserProfile(user))
  }

  async update({ auth, request, serialize }: HttpContext) {
    const user = auth.getUserOrFail()
    const { fullName } = await request.validateUsing(updateProfileValidator)

    user.fullName = fullName
    await user.save()

    return serialize(await serializeUserProfile(user))
  }
}
