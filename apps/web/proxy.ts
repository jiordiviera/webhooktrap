import type { NextRequest } from 'next/server'
import { NextResponse } from 'next/server'

/** Internal API origin — server-only, never shown in the browser. */
const apiUrl = process.env.APP_URL ?? 'http://localhost:3333'

function proxyToApi(request: NextRequest, upstreamPath: string) {
  const upstream = new URL(upstreamPath, apiUrl)
  upstream.search = request.nextUrl.search
  return NextResponse.rewrite(upstream)
}

export function proxy(request: NextRequest) {
  const { pathname } = request.nextUrl

  if (pathname.startsWith('/api/v1/')) {
    return proxyToApi(request, pathname)
  }

  const hooksMatch = pathname.match(/^\/hooks\/([A-Za-z0-9]{12})$/)
  if (hooksMatch) {
    return proxyToApi(request, `/i/${hooksMatch[1]}`)
  }

  return NextResponse.next()
}

export const config = {
  matcher: ['/api/v1/:path*', '/hooks/:inboxId'],
}