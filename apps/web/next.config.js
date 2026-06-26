/** @type {import('next').NextConfig} */

// eslint-disable-next-line turbo/no-undeclared-env-vars, no-undef
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
