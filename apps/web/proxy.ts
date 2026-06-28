import type { NextRequest } from 'next/server'
import { NextResponse } from 'next/server'
import { apiUrl } from '@/lib/config'

function proxyToApi(request: NextRequest, upstreamPath: string) {
  const upstream = new URL(upstreamPath, apiUrl)
  upstream.search = request.nextUrl.search
  return NextResponse.rewrite(upstream)
}

export function proxy(request: NextRequest) {
  const { pathname } = request.nextUrl

  const hooksMatch = pathname.match(/^\/hooks\/([A-Za-z0-9]{12})$/)
  if (hooksMatch) {
    return proxyToApi(request, `/i/${hooksMatch[1]}`)
  }

  return NextResponse.next()
}

export const config = {
  matcher: ['/hooks/:inboxId'],
}