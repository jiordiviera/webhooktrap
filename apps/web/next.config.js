/** @type {import('next').NextConfig} */

const mediaCdnHostname = process.env.MEDIA_CDN_HOSTNAME ?? 'media.hookscope.dev'

const nextConfig = {
  transpilePackages: ["@workspace/ui"],
  images: {
    remotePatterns: [
      {
        protocol: 'https',
        hostname: mediaCdnHostname,
        pathname: '/**',
      },
      {
        protocol: 'https',
        hostname: '*.public.blob.vercel-storage.com',
        pathname: '/**',
      },
    ],
  },
}

export default nextConfig
