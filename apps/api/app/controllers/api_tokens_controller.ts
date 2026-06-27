import User from '#models/user'
import { apiTokenValidator } from '#validators/user'
import type { HttpContext } from '@adonisjs/core/http'

export default class ApiTokensController {
  async index({ auth, serialize }: HttpContext) {
    const user = auth.getUserOrFail()
    const tokens = await User.accessTokens.all(user)

    return serialize({
      tokens: tokens.map((token) => ({
        id: token.identifier,
        name: token.name,
        abilities: token.abilities,
        createdAt: token.createdAt,
        lastUsedAt: token.lastUsedAt,
        expiresAt: token.expiresAt,
      })),
    })
  }

  async store({ auth, request, serialize }: HttpContext) {
    const user = auth.getUserOrFail()
    const { name } = await request.validateUsing(apiTokenValidator)

    const token = await User.accessTokens.create(user, ['*'], {
      name,
    })

    return serialize({
      id: token.identifier,
      name: token.name,
      abilities: token.abilities,
      token: token.value!.release(),
      createdAt: token.createdAt,
      expiresAt: token.expiresAt,
    })
  }

  async destroy({ auth, params, response }: HttpContext) {
    const user = auth.getUserOrFail()
    const tokenId = Number(params.id)

    const token = await User.accessTokens.find(user, tokenId)
    if (!token) {
      return response.notFound({ data: { message: 'Token not found' } })
    }

    await User.accessTokens.delete(user, tokenId)

    return { data: { deleted: true } }
  }
}
