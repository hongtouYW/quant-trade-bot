/** @type {import('next').NextConfig} */
const nextConfig = {
  compress: true, // enables gzip + brotli
  images: {
    unoptimized: true,
    remotePatterns: [
      {
        protocol: "https",
        hostname: "api.apsk.cc",
        pathname: "/**",
      },
      {
        protocol: "http",
        hostname: "apitest.apsk.cc",
        pathname: "/**",
      },
      {
        protocol: "https",
        hostname: "**", // ✅ allow all https domains
      },
      {
        protocol: "http",
        hostname: "**", // (optional) also allow http if needed
      },
    ],
  },
  experimental: {
    scrollRestoration: true,
  },
};

export default nextConfig;
