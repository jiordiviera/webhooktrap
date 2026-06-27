export interface UserProfileDTO {
  id: number
  email: string
  fullName: string | null
  initials: string
  avatar: string | null
  createdAt: string
  updatedAt: string | null
}

export interface LoginResponseDTO {
  user: UserProfileDTO
  token: string
}

export interface SignupResponseDTO {
  user: UserProfileDTO
  token: string
}
