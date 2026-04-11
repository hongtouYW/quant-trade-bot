import "@vidstack/react/player/styles/default/theme.css";
import "@vidstack/react/player/styles/default/layouts/video.css";
import "./App.css";
import "@/i18n"; // Initialize i18next (SSR-safe — LanguageDetector is browser-only)

import { useState } from "react";
import axios from "axios";
import { Links, Meta, Outlet, Scripts, ScrollRestoration } from "react-router";
import { ThemeProvider } from "@/components/theme-provider";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { ReactQueryDevtools } from "@tanstack/react-query-devtools";
import { LanguageProvider } from "@/contexts/LanguageContext";
import { ConfigProvider } from "@/contexts/ConfigContext";
import { GlobalImageProvider } from "@/contexts/GlobalImageContext";
import { UserProvider } from "@/contexts/UserContext";
import { IdentityCardProvider } from "@/contexts/IdentityCardContext";
import { AuthDialogProvider } from "@/contexts/AuthDialogContext";
import { TokenValidator } from "@/components/TokenValidator";
import { LanguageRouteSync } from "@/components/LanguageRouteSync";
import { Toaster } from "@/components/ui/sonner";
import {
  RATE_LIMIT_ERROR_CODE,
  RATE_LIMIT_MAX_RETRIES,
  RATE_LIMIT_RETRY_DELAY,
} from "@/constants/network.ts";
import { SITE_NAME, DEFAULT_DESCRIPTION, BASE_URL } from "@/hooks/useSEO";

const isRateLimitError = (error: unknown) =>
  axios.isAxiosError(error) &&
  error.response?.data?.code === RATE_LIMIT_ERROR_CODE;

export function Layout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="zh">
      <head>
        <meta charSet="UTF-8" />
        <link rel="icon" type="image/svg+xml" href="/logo-only.svg" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <Meta />
        <Links />
        {/* Google Fonts — loaded async to avoid render-blocking */}
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link rel="preconnect" href="https://fonts.gstatic.com" crossOrigin="" />
        <link
          rel="stylesheet"
          href="https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@400;500;700&display=swap"
          media="print"
          onLoad={(e) => {
            (e.target as HTMLLinkElement).media = "all";
          }}
        />
        <noscript>
          <link
            rel="stylesheet"
            href="https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@400;500;700&display=swap"
          />
        </noscript>
      </head>
      <body className="min-h-full w-full">
        {children}
        <ScrollRestoration />
        <Scripts />
      </body>
    </html>
  );
}

export function meta() {
  return [
    { title: `${SITE_NAME} - 高清日本成人视频在线观看` },
    { name: "description", content: DEFAULT_DESCRIPTION },
    { name: "robots", content: "index, follow" },
    { property: "og:type", content: "website" },
    { property: "og:site_name", content: SITE_NAME },
    { property: "og:title", content: SITE_NAME },
    { property: "og:description", content: DEFAULT_DESCRIPTION },
    { property: "og:url", content: BASE_URL },
    { property: "og:image", content: `${BASE_URL}/logo-only.svg` },
    { name: "twitter:card", content: "summary_large_image" },
    { name: "twitter:title", content: SITE_NAME },
    { name: "twitter:description", content: DEFAULT_DESCRIPTION },
    { name: "twitter:image", content: `${BASE_URL}/logo-only.svg` },
  ];
}

export default function Root() {
  const [queryClient] = useState(
    () =>
      new QueryClient({
        defaultOptions: {
          queries: {
            refetchOnWindowFocus: false,
            retry: (failureCount, error) =>
              isRateLimitError(error) && failureCount < RATE_LIMIT_MAX_RETRIES,
            retryDelay: () => RATE_LIMIT_RETRY_DELAY,
          },
        },
      }),
  );

  return (
    <QueryClientProvider client={queryClient}>
      <ThemeProvider defaultTheme="auto" storageKey="insav-ui-theme">
        <LanguageProvider>
          <ConfigProvider>
            <GlobalImageProvider>
              <UserProvider>
                <TokenValidator />
                <IdentityCardProvider>
                  <AuthDialogProvider>
                    <LanguageRouteSync />
                    <Outlet />
                    <Toaster />
                    <ReactQueryDevtools initialIsOpen={false} />
                  </AuthDialogProvider>
                </IdentityCardProvider>
              </UserProvider>
            </GlobalImageProvider>
          </ConfigProvider>
        </LanguageProvider>
      </ThemeProvider>
    </QueryClientProvider>
  );
}
