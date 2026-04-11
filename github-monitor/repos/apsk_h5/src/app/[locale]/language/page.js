"use client";

import { useEffect, useState } from "react";
import Image from "next/image";
import Link from "next/link";
import { usePathname, useRouter } from "next/navigation";
import { useLocale, useTranslations } from "next-intl";
import { IMAGES } from "@/constants/images";
import { setCookie } from "@/utils/cookie";

const COOKIE = "NEXT_LOCALE";
const ONE_YEAR = 60 * 60 * 24 * 365;

const LOCALES = [
  { key: "zh", labelKey: "zh" },
  { key: "ms", labelKey: "ms" },
  { key: "en", labelKey: "en" },
  //   { key: "th", labelKey: "th" },
  //   { key: "vi", labelKey: "vi" },
  //   { key: "my", labelKey: "my" }
];

export default function LanguagePage() {
  const t = useTranslations();
  const current = useLocale(); // e.g. "zh"
  const router = useRouter();
  const pathname = usePathname();
  // const searchParams = useSearchParams();

  const [selected, setSelected] = useState(null);

  // sync current locale into state on mount
  useEffect(() => {
    setSelected(current);
  }, [current]);

  const selectLocale = (next) => {
    if (next === selected) return;
    setSelected(next);

    // 1) persist cookie
    setCookie(COOKIE, next, ONE_YEAR);

    // 2) rebuild path with new locale
    const parts = pathname.split("/");
    parts[1] = next;
    const nextUrl = `${parts.join("/")}${window.location.hash || ""}`;

    // 3) navigate + refresh

    window.location.replace("/");
  };

  return (
    <div className="mx-auto max-w-[480px] min-h-dvh bg-[#00143D] text-white py-6">
      {/* Header */}
      <div className="relative flex items-center px-4 py-4">
        <button onClick={() => router.back()} className="z-10 cursor-pointer">
          <Image src={IMAGES.arrowLeft} alt="back" width={20} height={20} />
        </button>
        <h1 className="absolute inset-x-0 text-lg font-semibold text-center">
          {t("language.title")}
        </h1>
      </div>

      {/* List */}

      <div className="bg-[#162344]  flex flex-col items-center text-center">
        {LOCALES.map((item, idx) => {
          const active = selected === item.key;
          return (
            <label
              key={item.key}
              className="relative w-full flex items-center justify-between px-4 py-5 cursor-pointer"
            >
              <input
                type="radio"
                name="language"
                value={item.key}
                checked={active}
                onChange={() => selectLocale(item.key)}
                className="sr-only"
              />

              {/* Left */}
              <div className="flex items-center gap-3">
                <Image
                  src={IMAGES.setting.iconGlobe ?? "/icons/globe-yellow.svg"}
                  alt="globe"
                  width={20}
                  height={20}
                />
                <span className="text-sm">
                  {t(`language.${item.labelKey}`)}
                </span>
              </div>

              {/* Right: visual switch */}
              <div
                aria-hidden
                className={`h-7 w-12 rounded-full transition-colors ${
                  active ? "bg-[#22C55E]" : "bg-white/30"
                }`}
              >
                <div
                  className={`h-7 w-7 rounded-full bg-white shadow transform transition-transform ${
                    active ? "translate-x-5" : "translate-x-0"
                  }`}
                />
              </div>

              {/* Divider */}
              {idx !== LOCALES.length - 1 && (
                <div className="absolute bottom-0 left-3 right-3 h-px bg-[#354B9C]" />
              )}
            </label>
          );
        })}
      </div>
    </div>
  );
}
