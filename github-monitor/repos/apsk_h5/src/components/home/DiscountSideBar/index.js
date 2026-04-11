"use client";

import Image from "next/image";
import { useTranslations } from "next-intl";
import { IMAGES } from "@/constants/images";

// ✅ Promotion types mapped correctly
export const PROMOTION_ITEMS = [
  {
    id: "newmemberreload",
    iconActive: IMAGES.home.sidebar.iconSmile,
    iconInactive: IMAGES.home.sidebar.iconSmileInactive,
    labelKey: "newMember",
  },
  {
    id: "cryptoreload",
    iconActive: IMAGES.home.sidebar.iconBitcoin,
    iconInactive: IMAGES.home.sidebar.iconBitcoinInactive,
    labelKey: "crypto",
  },
  {
    id: "agent",
    iconActive: IMAGES.home.sidebar.iconGraph,
    iconInactive: IMAGES.home.sidebar.iconGraphInactive,
    labelKey: "agent", // 代理
  },
  {
    id: "hot",
    iconActive: IMAGES.home.sidebar.iconFire,
    iconInactive: IMAGES.home.sidebar.iconFireInactive,
    labelKey: "hotActivity",
  },
  {
    id: "others",
    iconActive: IMAGES.home.sidebar.iconSetting,
    iconInactive: IMAGES.home.sidebar.iconSettingInactive,
    labelKey: "other",
  },
];

export default function DiscountSideBar({
  items = PROMOTION_ITEMS,
  activeId,
  onSelect = () => {},
  className = "",
}) {
  const t = useTranslations("promotion"); // ✅ use promotion namespace for translation

  return (
    <aside
      className={[
        "relative w-[84px]  px-2 py-3",
        "rounded-[15px] overflow-hidden",
        "flex flex-col items-center gap-3",
        className,
      ].join(" ")}
    >
      {/* 🔹 Background image */}
      <div className="absolute inset-0 z-0">
        <Image
          src={IMAGES.home.sidebar.sidebarBg}
          alt=""
          fill
          sizes="84px"
          className="object-cover pointer-events-none select-none"
          priority
        />
      </div>

      {/* 🔹 Top overlay */}
      <div className="absolute top-0 left-0 right-0 h-[50px] pointer-events-none">
        <Image
          src={IMAGES.home.sidebar.sidebarTop}
          alt="Sidebar top"
          fill
          sizes="(max-width: 480px) calc(100vw - 32px), 480px"
          className="object-cover"
        />
      </div>

      {/* 🔹 Sidebar Buttons */}
      <div className="relative z-10 flex flex-col items-center gap-1 w-full">
        {items.map((it) => (
          <button
            key={it.id}
            onClick={() => onSelect(it)}
            className="relative w-[80px] h-[80px] shrink-0"
          >
            <div
              className="absolute inset-0 m-1 p-1  flex flex-col items-center justify-center gap-1"
              style={{
                width: 75,
                height: 75,
                backgroundImage: `url(${
                  activeId === it.id
                    ? IMAGES.home.sidebar.bgActive
                    : IMAGES.home.sidebar.iconDiscountBar
                })`,
                backgroundSize: "100% 100%", // 👈 stretch both directions
                backgroundRepeat: "no-repeat",
                backgroundPosition: "center",
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
                    ? "text-white font-medium"
                    : "text-[#A49A81]",
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
