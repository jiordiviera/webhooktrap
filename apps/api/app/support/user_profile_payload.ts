import MediaService from '#media/media_service'
import UserOauthAccount from '#models/user_oauth_account'
import type User from '#models/user'
import UserTransformer from '#transformers/user_transformer'

export async function serializeUserProfile(user: User) {
  const mediaAvatar = await MediaService.getMediaUrl('users', String(user.id), 'avatar')
  const oauthAccount = await UserOauthAccount.query().where('user_id', user.id).first()
  const avatar = mediaAvatar ?? oauthAccount?.avatarUrl ?? null

  user.$extras.avatar = avatar

  return UserTransformer.transform(user)
}
