"use client";

import Image from "next/image";
import Link from "next/link";
import { useTranslations } from "next-intl";
import { IMAGES } from "@/constants/images";
import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { clearAllCookie } from "@/utils/cookie";

export default function PublicHeader() {
  const t = useTranslations("landing");
  const router = useRouter();
  const [active, setActive] = useState("");

  useEffect(() => {
    if (typeof window !== "undefined") {
      setActive(window.location.pathname);
    }
  }, []);

  // underline helper
  const underline = (active) =>
    active
      ? "after:absolute after:left-0 after:right-0 after:bottom-0 after:h-[2px] after:bg-black"
      : "";

  const handleRegister = () => {
    router.push("/login");
    setTimeout(() => {
      router.push("/register"); // no blink navigation
    }, 800);
  };

  // home detection
  const isHome = () =>
    current === "/public" ||
    current === "/public/" ||
    current.endsWith("/public");

  return (
    <header className="sticky top-0 z-50 w-full bg-white/95 backdrop-blur">
      <div className="mx-auto max-w-6xl h-14 flex items-center justify-between px-4">
        {/* ===== LEFT LOGO ===== */}
        <div className="flex items-center gap-2">
          <Link href="/public">
            <Image
              src={IMAGES.intro.logo}
              alt="logo"
              width={120}
              height={40}
              className="h-8 w-auto"
              priority
            />
          </Link>
        </div>

        {/* ===== NAV ===== */}
        <nav className="hidden md:flex items-center gap-8 text-sm font-medium text-black">
          {/* HOME */}
          <Link
            href="/public"
            onClick={() => setActive("/public")}
            className={`hover:opacity-70 relative pb-1 ${underline(
              active === "/public" || active === "/public/"
            )}`}
          >
            {t("nav.home")}
          </Link>

          {/* HELP */}
          <Link
            href="/public/help"
            onClick={() => setActive("/public/help")}
            className={`hover:opacity-70 relative pb-1 ${underline(
              active.startsWith("/public/help")
            )}`}
          >
            {t("nav.help")}
          </Link>

          {/* PRIVACY */}
          <Link
            href="/public/privacy"
            onClick={() => setActive("/public/privacy")}
            className={`hover:opacity-70 relative pb-1 ${underline(
              active.startsWith("/public/privacy")
            )}`}
          >
            {t("nav.privacy")}
          </Link>

          {/* TERMS */}
          <Link
            href="/public/terms"
            onClick={() => setActive("/public/terms")}
            className={`hover:opacity-70 relative pb-1 ${underline(
              active.startsWith("/public/terms")
            )}`}
          >
            {t("nav.terms")}
          </Link>
        </nav>

        {/* ===== ACTION BUTTONS ===== */}
        <div className="flex items-center gap-3">
          <Link
            href="/login"
            className="px-6 py-1.5 rounded-full bg-gradient-to-b from-[#FFE27A] to-[#F7C843] text-black text-sm font-semibold shadow-sm"
          >
            {t("nav.login")}
          </Link>

          <button
            onClick={handleRegister}
            className="px-6 py-1.5 rounded-full border border-black/25 text-sm font-semibold text-black"
          >
            {t("nav.register")}
          </button>
        </div>
      </div>
    </header>
  );
}
