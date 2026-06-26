/* eslint-disable turbo/no-undeclared-env-vars */
/* eslint-disable no-undef */
/** @type {import('next').NextConfig} */

const mediaCdnHostname = process.env.MEDIA_CDN_BASE_URL ?? "cdn.jiordiviera.me";

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
};

export default nextConfig;
