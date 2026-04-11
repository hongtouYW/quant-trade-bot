"use client";

import Image from "next/image";
import Link from "next/link";
import { useTranslations } from "next-intl";
import { IMAGES } from "@/constants/images";
import { useEffect, useState } from "react";

export default function PrivacyHeader() {
  const navT = useTranslations("nav");
  const [active, setActive] = useState("");

  useEffect(() => {
    if (typeof window !== "undefined") {
      setActive(window.location.pathname);
    }
  }, []);

  const underline = (isActive) =>
    isActive
      ? "after:absolute after:left-0 after:right-0 after:bottom-0 after:h-[2px] after:bg-black"
      : "";

  return (
    <header className="sticky top-0 z-50 w-full bg-white/95 backdrop-blur">
      <div className="mx-auto max-w-6xl h-14 flex items-center justify-between px-4">
        <div className="flex items-center gap-2">
          <Link href="/privacy">
            {/* <Image
              src={IMAGES.intro.logo}
              alt="logo"
              width={120}
              height={40}
              className="h-8 w-auto"
              priority
            /> */}
          </Link>
        </div>

        <nav className="hidden md:flex items-center gap-8 text-sm font-medium text-black">
          <Link
            href="/privacy"
            onClick={() => setActive("/privacy")}
            className={`hover:opacity-70 relative pb-1 ${underline(
              active.includes("/privacy"),
            )}`}
          >
            {navT("privacy")}
          </Link>
        </nav>
      </div>
    </header>
  );
}
