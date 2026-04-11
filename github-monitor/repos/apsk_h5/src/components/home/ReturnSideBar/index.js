// components/SidebarLeft.js
"use client";

import Image from "next/image";
import { useTranslations } from "next-intl";
import { IMAGES } from "@/constants/images";

// default items (you can override via props)
export const DEFAULT_ITEMS = [
  {
    id: "newMember",
    iconActive: IMAGES.home.sidebar.iconHot,
    iconInactive: IMAGES.home.sidebar.iconHotInactive,
    labelKey: "newMember",
  },
  {
    id: "crypto",
    iconActive: IMAGES.home.sidebar.iconApp,
    iconInactive: IMAGES.home.sidebar.iconAppInactive,
    labelKey: "crypto",
  },
  {
    id: "agent",
    iconActive: IMAGES.home.sidebar.iconApp,
    iconInactive: IMAGES.home.sidebar.iconAppInactive,
    labelKey: "agent",
  },
  {
    id: "hotActivity",
    iconActive: IMAGES.home.sidebar.iconApp,
    iconInactive: IMAGES.home.sidebar.iconAppInactive,
    labelKey: "hotActivity",
  },
  {
    id: "other",
    iconActive: IMAGES.home.sidebar.iconApp,
    iconInactive: IMAGES.home.sidebar.iconAppInactive,
    labelKey: "other",
  },
];

export default function ReturnSideBar({
  items = DEFAULT_ITEMS,
  activeId,
  onSelect = () => {},
  className = "",
}) {
  const t = useTranslations("nav");

  return (
    <aside
      className={[
        "relative w-[84px]  px-2 py-3",
        "rounded-[15px] overflow-hidden", // mask group effect
        "flex flex-col items-center gap-3",
        className,
      ].join(" ")}
    >
      {/* Background image (Rectangle 931) */}
      <div className="absolute inset-0 z-0">
        <Image
          src={IMAGES.home.sidebar.sidebarBg} // MUST be like "/images/..."
          alt=""
          fill
          sizes="84px" // width of the rail
          className="object-cover pointer-events-none select-none"
          priority
        />
      </div>

      {/* Rectangle 936 overlay */}
      <div className="absolute top-0 left-0 right-0 h-[50px] pointer-events-none">
        <Image
          src={IMAGES.home.sidebar.sidebarTop}
          alt="Sidebar top"
          fill
          sizes="(max-width: 480px) calc(100vw - 32px), 480px"
          className="object-cover"
          priority={false}
        />
      </div>

      {/* Sidebar content */}
      <div className="relative z-10 flex flex-col items-center gap-1 w-full">
        {items.map((it) => (
          <button
            key={it.id}
            onClick={() => onSelect(it)}
            className="relative w-[72px] h-[72px] shrink-0"
          >
            <div
              className="absolute inset-0 m-1  p-1 rounded-2xl flex flex-col items-center justify-center gap-1"
              style={{
                width: 65,
                height: 65,
                backgroundImage: `url(${
                  activeId === it.id
                    ? IMAGES.home.sidebar.bgActive
                    : IMAGES.home.sidebar.bgInactive
                })`,
                backgroundSize: "cover",
                backgroundRepeat: "no-repeat",
              }}
            >
              <Image
                src={activeId === it.id ? it.iconActive : it.iconInactive}
                alt={t(it.labelKey)}
                width={22}
                height={22}
              />
              <span
                className={[
                  "text-[12px] leading-none",
                  activeId === it.id
                    ? "text-black font-medium"
                    : "text-black/60",
                ].join(" ")}
              >
                {t(it.labelKey)}
              </span>
            </div>
          </button>
        ))}
      </div>
    </aside>
  );
}
