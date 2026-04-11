import { Geist, Geist_Mono } from "next/font/google";
import "./globals.css";
import FooterNav from "@/components/shared/FooterNav";

const geistSans = Geist({
  variable: "--font-geist-sans",
  subsets: ["latin"],
});

const geistMono = Geist_Mono({
  variable: "--font-geist-mono",
  subsets: ["latin"],
});
//testing
// ✅ only SEO-related info here
export const metadata = {
  title: "Expro99",
  description: "Expro99",
};

// ✅ viewport must be its own export
export const viewport = {
  width: "device-width",
  initialScale: 1,
  maximumScale: 1,
  viewportFit: "cover",
  themeColor: "#01133C",
};

export default function RootLayout({ children }) {
  return (
    <html lang="zh">
      <body
        className={`${geistSans.variable} ${geistMono.variable} antialiased bg-neutral-900`}
      >
        <div className="mx-auto min-h-dvh">{children}</div>
      </body>
    </html>
  );
}
