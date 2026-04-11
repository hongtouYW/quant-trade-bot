"use client";

import { useRef } from "react";
import Image from "next/image";
import { useTranslations } from "next-intl";
import { useDraggable } from "react-use-draggable-scroll";
import { IMAGES } from "@/constants/images";
import { getCookie } from "@/utils/cookie";
const buttonWidth = 94;
const locale = getCookie("NEXT_LOCALE");
// export const DEFAULT_ITEMS = [
//   {
//     id: "all",
//     iconActive: IMAGES.home.sidebar.iconAll2,
//     iconInactive: IMAGES.home.sidebar.iconAll2,
//     labelKey: "all",
//     width: buttonWidth,
//   },
//   {
//     id: "app",
//     iconActive: IMAGES.home.sidebar.iconGame2,
//     iconInactive: IMAGES.home.sidebar.iconGame2,
//     labelKey: "app",
//     width: buttonWidth,
//   },
//   {
//     id: "slot",
//     iconActive: IMAGES.home.sidebar.iconSlot2,
//     iconInactive: IMAGES.home.sidebar.iconSlot2,
//     labelKey: "slot",
//     width: buttonWidth,
//   },
//   {
//     id: "live",
//     iconActive: IMAGES.home.sidebar.iconLive2,
//     iconInactive: IMAGES.home.sidebar.iconLive2,
//     labelKey: "live",
//     width: buttonWidth,
//   },
//   {
//     id: "sport",
//     iconActive: IMAGES.home.sidebar.iconSport2,
//     iconInactive: IMAGES.home.sidebar.iconSport2,
//     labelKey: "sport",
//     width: buttonWidth,
//   },
//   {
//     id: "fishing",
//     iconActive: IMAGES.home.sidebar.iconFishing2,
//     iconInactive: IMAGES.home.sidebar.iconFishing2,
//     labelKey: "fishing",
//     width: buttonWidth,
//   },
//   {
//     id: "cock",
//     iconActive: IMAGES.home.sidebar.iconCockfight2,
//     iconInactive: IMAGES.home.sidebar.iconCockfight2,
//     labelKey: "cock",
//     width: buttonWidth,
//   },
//   {
//     id: "lottery",
//     iconActive: IMAGES.home.sidebar.iconCard2,
//     iconInactive: IMAGES.home.sidebar.iconCard2,
//     labelKey: "lottery",
//     width: buttonWidth,
//   },
//   {
//     id: "chess",
//     iconActive: IMAGES.home.sidebar.iconSpin2,
//     iconInactive: IMAGES.home.sidebar.iconSpin2,
//     labelKey: "chess",
//     width: buttonWidth,
//   },
// ];

export const DEFAULT_ITEMS = [
  {
    id: "all",
    icon: IMAGES.home.sidebar.iconAll2,
    labelKey: "all",
    width: buttonWidth,
    iconSize: 20,
    offsetY: 0,
  },
  {
    id: "app",
    icon: IMAGES.home.sidebar.iconGame2,
    labelKey: "app",
    width: buttonWidth,
    iconSize: 35,
    offsetY: 0,
  },
  {
    id: "slot",
    icon: IMAGES.home.sidebar.iconSlot2,
    labelKey: "slot",
    width: buttonWidth,
    iconSize: 40,
    offsetY: 0,
  },
  {
    id: "live",
    icon: IMAGES.home.sidebar.iconLive2,
    labelKey: "live",
    width: buttonWidth,
    iconSize: 30,
    offsetY: 0,
  },
  {
    id: "sport",
    icon: IMAGES.home.sidebar.iconSport2,
    labelKey: "sport",
    width: buttonWidth,
    iconSize: 20,
    offsetY: 0,
  },
  {
    id: "fishing",
    icon: IMAGES.home.sidebar.iconFishing2,
    labelKey: "fishing",
    width: buttonWidth,
    iconSize: 20,
    offsetY: 0,
  },
  {
    id: "cock",
    icon: IMAGES.home.sidebar.iconCockfight2,
    labelKey: "cock",
    width: buttonWidth,
    iconSize: 30,
    offsetY: 0,
  },
  {
    id: "lottery",
    icon: IMAGES.home.sidebar.iconCard2,
    labelKey: "lottery",
    width: buttonWidth,
    iconSize: 30,
    offsetY: 0,
  },
  {
    id: "chess",
    icon: IMAGES.home.sidebar.iconSpin2,
    labelKey: "chess",
    width: buttonWidth,
    iconSize: 30,
    offsetY: 0,
  },
];
export default function HomeSideBar({
  items = DEFAULT_ITEMS,
  activeId,
  onSelect = () => {},
  className = "",
}) {
  const t = useTranslations("nav");

  const ref = useRef(null);
  const { events } = useDraggable(ref, {
    safeDisplacement: 5,
    decayRate: 0.95,
  });

  return (
    <div
      ref={ref}
      {...events}
      className={`
    flex space-x-2 px-2
    pt-[15px]
    overflow-x-scroll
    overflow-y-visible
    scrollbar-hide
    select-none cursor-grab
    [scrollbar-width:none]
    [&::-webkit-scrollbar]:hidden
    ${className}
  `}
      style={{ touchAction: "pan-x", overflowY: "visible" }}
    >
      {items.map((it) => {
        const isActive = activeId === it.id;
        const BASE_ICON_SIZE = 35;

        return (
          <button
            key={it.id}
            onClick={() => onSelect(it)}
            className="flex-none relative h-[47px] flex items-center justify-center"
            style={{ width: it.width }}
          >
            {/* background */}
            <div className="absolute inset-[2px] z-0">
              <Image
                src={
                  isActive
                    ? IMAGES.home.sidebar.bgActive
                    : IMAGES.home.sidebar.bgInactiveBlue
                }
                alt=""
                fill
                className="object-fill"
              />
            </div>

            {/* floating icon (baseline locked) */}
            <div className="absolute bottom-full mb-[-15px] left-1/2 -translate-x-1/2 z-20 pointer-events-none">
              <div
                className="relative flex items-end justify-center overflow-visible"
                style={{
                  width: it.iconSize,
                  height: it.iconSize,
                  transform: `translateY(${it.offsetY || 0}px)`,
                }}
              >
                <Image
                  src={it.icon}
                  alt={t(it.labelKey)}
                  width={it.iconSize}
                  height={it.iconSize}
                  className="object-contain"
                />
              </div>
            </div>

            {/* text */}
            <div className="relative z-10 h-full w-full flex items-end justify-center pb-3">
              <span
                className={`
              text-[15px] font-bold leading-none
              ${isActive ? "text-white" : "text-[#C7B68A]"}
            `}
              >
                {t(it.labelKey)}
              </span>
            </div>
          </button>
        );
      })}
    </div>
  );
}
