import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  /* config options here */
  compress: true,

  images: {
    unoptimized: true,
    remotePatterns: [
      {
        protocol: "http", // Or 'https' if your server supports it
        hostname: "72.61.148.252",
        // If your images are only in a specific folder on that server, you can add:
        // pathname: '/path/to/my/images/**',
      },
    ],
  },
};

export default nextConfig;
