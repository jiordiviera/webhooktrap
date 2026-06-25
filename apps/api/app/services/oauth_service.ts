import MediaService from '#media/media_service'
import User from '#models/user'
import UserOauthAccount from '#models/user_oauth_account'
import type { OAuthProvider } from '#services/oauth_types'

export type { OAuthProvider } from '#services/oauth_types'

export type OAuthProfile = {
  id: string | number
  email: string | null
  name: string
  avatarUrl: string | null
}

export default class OAuthService {
  static async findOrCreate(provider: OAuthProvider, profile: OAuthProfile) {
    const linked = await UserOauthAccount.query()
      .where('provider', provider)
      .where('providerUserId', String(profile.id))
      .preload('user')
      .first()

    if (linked) {
      await linked
        .merge({
          providerEmail: profile.email,
          avatarUrl: profile.avatarUrl,
        })
        .save()
      await this.importAvatar(linked.user, profile.avatarUrl)
      return linked.user
    }

    if (profile.email) {
      const existingUser = await User.findBy('email', profile.email)
      if (existingUser) {
        await UserOauthAccount.create({
          userId: existingUser.id,
          provider,
          providerUserId: String(profile.id),
          providerEmail: profile.email,
          avatarUrl: profile.avatarUrl,
        })
        await this.importAvatar(existingUser, profile.avatarUrl)
        return existingUser
      }
    }

    if (!profile.email) {
      throw new Error(`OAuth provider "${provider}" did not return an email address`)
    }

    const user = await User.create({
      email: profile.email,
      fullName: profile.name,
      password: crypto.randomUUID(),
    })

    await UserOauthAccount.create({
      userId: user.id,
      provider,
      providerUserId: String(profile.id),
      providerEmail: profile.email,
      avatarUrl: profile.avatarUrl,
    })

    await this.importAvatar(user, profile.avatarUrl)
    return user
  }

  private static async importAvatar(user: User, avatarUrl: string | null) {
    if (!avatarUrl) return

    const existing = await MediaService.getFirst('users', String(user.id), 'avatar')
    if (existing) return

    try {
      await MediaService.attachFromUrl({
        modelType: 'users',
        modelId: String(user.id),
        collection: 'avatar',
        url: avatarUrl,
      })
    } catch {
      // Keep OAuth login successful even if remote avatar import fails.
    }
  }
}
