import { NextRequest, NextResponse } from "next/server";
import { env } from "@/config/env";

async function handler(req: NextRequest) {
  const backendUrl = env.api.url;
  const pathname = req.nextUrl.pathname.replace(/^\/api\/backend/, "/api/v1");
  const url = `${backendUrl}${pathname}${req.nextUrl.search}`;

  const token = req.cookies.get("token")?.value;

  const headers: Record<string, string> = {
    "Content-Type": req.headers.get("content-type") || "application/json",
  };

  if (token) {
    headers["Authorization"] = `Bearer ${token}`;
  }

  const accept = req.headers.get("accept");
  if (accept) headers["Accept"] = accept;

  const acceptLanguage = req.headers.get("accept-language");
  if (acceptLanguage) headers["Accept-Language"] = acceptLanguage;

  const tz =
    req.cookies.get("timezone")?.value ||
    Intl.DateTimeFormat().resolvedOptions().timeZone;
  headers["X-Timezone"] = tz;

  try {
    const body =
      req.method !== "GET" && req.method !== "HEAD"
        ? await req.text()
        : undefined;

    const response = await fetch(url, {
      method: req.method,
      headers,
      body,
    });

    const responseHeaders = new Headers();
    const contentType = response.headers.get("content-type");
    if (contentType) responseHeaders.set("content-type", contentType);
    responseHeaders.set("x-proxy-status", "ok");

    const data = await response.text();

    return new NextResponse(data, {
      status: response.status,
      headers: responseHeaders,
    });
  } catch {
    return NextResponse.json(
      { message: "Backend service unavailable" },
      { status: 502 },
    );
  }
}

export { handler as GET, handler as POST, handler as PATCH, handler as DELETE, handler as OPTIONS, handler as HEAD };
