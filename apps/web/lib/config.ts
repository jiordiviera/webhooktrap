import { env } from '@/env'

export const siteUrl = env.NEXT_PUBLIC_WEB_URL ?? env.WEB_URL
export const apiUrl = env.APP_URL
export const productName = 'Hookscope'
export const productTagline = 'Webhook debugging, simplified.'
