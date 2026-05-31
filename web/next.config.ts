import type { NextConfig } from "next"

const nextConfig: NextConfig = {
  reactStrictMode: true,

  // Allows importing from packages/schemas without publishing
  transpilePackages: ["@hanapbahay/schemas"],

  images: {
    remotePatterns: [
      // Cloudflare R2 / CDN
      { protocol: "https", hostname: "*.r2.dev" },
      { protocol: "https", hostname: "*.cloudflare.com" },
      // S3
      { protocol: "https", hostname: "*.amazonaws.com" },
      // Developer / broker uploaded photos via our API
      { protocol: "https", hostname: "media.hanapbahay.ph" },
    ],
    formats: ["image/avif", "image/webp"],
    deviceSizes: [375, 640, 750, 828, 1080, 1200, 1920],
    minimumCacheTTL: 3600,
  },

  // Streaming SSE responses need this
  experimental: {
    serverActions: {
      allowedOrigins: [process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000"],
    },
  },

  // API passthrough for local dev (avoids CORS + cookie issues during development)
  async rewrites() {
    if (process.env.NODE_ENV !== "development") return []
    return [
      {
        source: "/api-proxy/:path*",
        destination: `${process.env.NEXT_PUBLIC_API_URL}/api/:path*`,
      },
    ]
  },

  async headers() {
    return [
      {
        // Harden headers on all routes
        source: "/:path*",
        headers: [
          { key: "X-Frame-Options", value: "DENY" },
          { key: "X-Content-Type-Options", value: "nosniff" },
          { key: "Referrer-Policy", value: "strict-origin-when-cross-origin" },
          {
            key: "Permissions-Policy",
            value: "camera=(), microphone=(), geolocation=(self)",
          },
        ],
      },
    ]
  },
}

export default nextConfig
