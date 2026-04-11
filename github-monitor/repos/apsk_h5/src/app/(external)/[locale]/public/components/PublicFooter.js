"use client";

import { useTranslations } from "next-intl";
import Link from "next/link";

export default function PublicFooter() {
  const t = useTranslations("landing");

  return (
    <footer className="w-full bg-[#DDE4ED]">
      <div className="mx-auto max-w-6xl px-4 py-8 text-xs text-black/50">
        {/* TOP: 3 links */}
        <div className="flex items-center gap-6 mb-4">
          <Link href="/public/help" className="hover:opacity-70">
            {t("footer.contact")}
          </Link>

          <Link href="/public/privacy" className="hover:opacity-70">
            {t("footer.privacy")}
          </Link>

          <Link href="/public/terms" className="hover:opacity-70">
            {t("footer.terms")}
          </Link>
        </div>

        {/* BOTTOM: Copyright */}
        <div className="text-black/50">
          © {new Date().getFullYear()} Expro99
        </div>
      </div>
    </footer>
  );
}
