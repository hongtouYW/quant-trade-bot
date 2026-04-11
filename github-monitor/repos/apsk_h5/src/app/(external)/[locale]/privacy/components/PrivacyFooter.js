"use client";

import { useTranslations } from "next-intl";
import Link from "next/link";

export default function PrivacyFooter() {
  const t = useTranslations("privacy");

  return (
    <footer className="w-full bg-[#DDE4ED]">
      <div className="mx-auto max-w-6xl px-4 py-8 text-xs text-black/50">
        <div className="flex items-center gap-6 mb-4">
          <Link href="/privacy" className="hover:opacity-70">
            {t("title")}
          </Link>
        </div>

        <div className="text-black/50">© {new Date().getFullYear()}</div>
      </div>
    </footer>
  );
}
