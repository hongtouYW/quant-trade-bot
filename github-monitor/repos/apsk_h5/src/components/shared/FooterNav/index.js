"use client";

import Image from "next/image";
import Link from "next/link";
import { useTranslations } from "next-intl";
import { useEffect, useState } from "react";
import { IMAGES } from "@/constants/images";
import { usePathname } from "next/navigation";

export default function FooterNav({ hrefs = {} }) {
  const t = useTranslations();

  const pathname = usePathname(); // e.g. "/en/setting"

  // 🔑 remove locale prefix: "/en/setting" -> "/setting"
  const getCleanPath = (path) => {
    const parts = path.split("/"); // ["", "en", "setting"]
    if (parts.length <= 2) return "/"; // "/en" -> "/"
    return `/${parts.slice(2).join("/")}`;
  };

  const pathToKey = (p) => {
    if (p === "/" || p === "") return "home";
    if (p.startsWith("/discount")) return "promo";
    if (p.startsWith("/support") || p.startsWith("/help")) return "cs";
    if (p.startsWith("/setting")) return "me";
    return "home";
  };

  const [active, setActive] = useState(() => pathToKey(getCleanPath(pathname)));

  // ✅ keep state synced when URL changes (back/forward, locale switch, etc.)
  useEffect(() => {
    setActive(pathToKey(getCleanPath(pathname)));
  }, [pathname]);

  const items = [
    {
      key: "home",
      label: t("footer.home"),
      icon: IMAGES?.footer?.home,
      href: hrefs.home ?? "/",
    },
    {
      key: "promo",
      label: t("footer.promo"),
      icon: IMAGES?.footer?.gift,
      href: hrefs.promo ?? "/discount",
    },
    {
      key: "cs",
      label: t("footer.cs"),
      icon: IMAGES?.footer?.headphone,
      href: hrefs.cs ?? "/help",
    },
    {
      key: "me",
      label: t("footer.me"),
      icon: IMAGES?.footer?.avatar,
      href: hrefs.me ?? "/setting",
    },
  ];

  const cleanPath = getCleanPath(pathname);
  const allowedPaths = items.map((it) => it.href); // ["/", "/promo", "/support", "/setting"]
  const shouldShow = allowedPaths.includes(cleanPath);

  if (!shouldShow) return null; // 👈 hide footer if not in main tabs

  return (
    <footer className="fixed inset-x-0 bottom-0 z-50">
      <div
        className="relative mx-auto max-w-[480px] rounded-t-[28px] px-2 pb-[calc(env(safe-area-inset-bottom)+8px)] pt-2"
        style={{
          background:
            "linear-gradient(90deg, #00143D 2.2%, #1B2D72 50.66%, #000422 99.69%)",
        }}
      >
        <div
          className="rounded-t-[20px] pt-[2px]"
          style={{
            background: "linear-gradient(90deg, #F8AF07 0%, #FFFC86 100%)",
          }}
        >
          <div
            className="px-2 pt-2"
            style={{
              background:
                "linear-gradient(90deg, #00143D 2.2%, #1B2D72 50.66%, #000422 99.69%)",
              borderTopLeftRadius: "18px",
              borderTopRightRadius: "18px",
            }}
          />
        </div>

        <div className="rounded-t-[26px]">
          <nav className="grid grid-cols-4">
            {items.map((it) => {
              const isActive = active === it.key;

              return (
                <Link
                  key={it.key}
                  href={it.href}
                  onClick={() => setActive(it.key)} // 👈 update state only
                  className={[
                    "group mx-1 flex flex-col items-center gap-1 rounded-2xl px-3 py-2 focus:outline-none",
                    isActive ? "bg-white/10" : "bg-transparent",
                  ].join(" ")}
                  aria-current={isActive ? "page" : undefined}
                >
                  <div className="relative h-7 w-7">
                    <Image
                      src={it.icon}
                      alt={it.label}
                      fill
                      sizes="28px"
                      className={
                        isActive
                          ? "object-contain"
                          : "object-contain opacity-60"
                      }
                      priority
                    />
                  </div>
                  <span
                    className={[
                      "text-xs tracking-wide text-center",
                      isActive ? "text-white" : "text-white/70",
                    ].join(" ")}
                  >
                    {it.label}
                  </span>
                </Link>
              );
            })}
          </nav>
        </div>
      </div>
    </footer>
  );
}
