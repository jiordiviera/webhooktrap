/* eslint-disable turbo/no-undeclared-env-vars */
/* eslint-disable no-undef */
/** @type {import('next').NextConfig} */

const mediaCdnHostname = process.env.MEDIA_CDN_BASE_URL ?? "cdn.jiordiviera.me";

const apiUrl =
  process.env.API_URL ??
  process.env.NEXT_PUBLIC_API_URL ??
  "http://localhost:3333";

const nextConfig = {
  transpilePackages: ["@workspace/ui"],
  images: {
    remotePatterns: [
      {
        protocol: "https",
        hostname: mediaCdnHostname,
        pathname: "/**",
      },
    ],
  },
  async rewrites() {
    return {
      beforeFiles: [
        {
          // Webhook ingest: POST/GET (curl, Stripe, …) → API.
          // Browser navigation (Accept: text/html) skips this and serves /i/[inboxId].
          source: "/i/:inboxId",
          missing: [
            {
              type: "header",
              key: "accept",
              value: "(.*text/html.*)",
            },
          ],
          destination: `${apiUrl}/i/:inboxId`,
        },
      ],
    };
  },
};

export default nextConfig;
