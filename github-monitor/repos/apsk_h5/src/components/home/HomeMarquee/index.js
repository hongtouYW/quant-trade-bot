"use client";

import { useEffect, useState } from "react";
import Marquee from "react-fast-marquee";
import { useTranslations } from "next-intl";
import { useMarqueeStore } from "@/store/zustand/marqueStore";
import { useGetDashboardMarqueeQuery } from "@/services/commonApi";
import { getMemberInfo } from "@/utils/utility";
import { skipToken } from "@reduxjs/toolkit/query";
import { IMAGES } from "@/constants/images";

export default function HomeMarquee() {
  const t = useTranslations();
  const info = getMemberInfo();
  const { items, init, setApiItems } = useMarqueeStore();
  const [isLocalReady, setIsLocalReady] = useState(false);
  const { data, isFetching, isUninitialized } = useGetDashboardMarqueeQuery(
    info?.member_id ? { member_id: info.member_id } : skipToken,
  );

  useEffect(() => {
    init();
    setIsLocalReady(true);
  }, [init]);

  useEffect(() => {
    setApiItems(Array.isArray(data?.slider) ? data.slider : []);
  }, [data?.slider, setApiItems]);

  const isReady =
    isLocalReady && (!info?.member_id || (!isUninitialized && !isFetching));

  const localizeMarqueeText = (text) => {
    if (!text) return "";

    return String(text).replace(/\$\{(\w+)\}/g, (_, key) => {
      try {
        return t(key);
      } catch {
        return `\${${key}}`;
      }
    });
  };

  if (!isReady || items.length === 0) {
    return (
      <section className="px-3">
        <div className="rounded-xl p-[2px] bg-[linear-gradient(90deg,#F8AF07_1.47%,#FFFC86_98.68%)]">
          <div
            className="relative rounded-[12px] bg-[#1B2D72] bg-center bg-no-repeat px-3 py-2 min-h-[33px]"
            style={{
              backgroundImage: `url(${IMAGES.home.profile.p4})`,
              backgroundSize: "100% 100%",
            }}
          >
            <div className="pointer-events-none absolute inset-0 rounded-[12px] bg-black/20" />
            <div className="relative z-10 h-[14px]" />
          </div>
        </div>
      </section>
    );
  }

  return (
    <section className="px-3">
      <div className="rounded-xl p-[2px] bg-[linear-gradient(90deg,#F8AF07_1.47%,#FFFC86_98.68%)]">
        <div
          className="relative rounded-[12px] bg-[#1B2D72] bg-center bg-no-repeat px-3 py-3 min-h-[35px]"
          style={{
            backgroundImage: `url(${IMAGES.home.profile.p4})`,
            backgroundSize: "100% 100%",
          }}
        >
          <div className="pointer-events-none absolute inset-0 rounded-[12px] bg-black/20" />
          <Marquee
            gradient={false}
            speed={35}
            pauseOnHover
            autoFill
            className="relative z-10 flex h-full items-center overflow-hidden text-[12px] font-medium leading-none text-white"
          >
            {items.map((item, index) => (
              <span
                key={item.id || `${item.text}-${item.time}-${index}`}
                className="inline-flex h-full items-center mr-6 whitespace-nowrap"
              >
                {localizeMarqueeText(item.text)}
              </span>
            ))}
          </Marquee>
        </div>
      </div>
    </section>
  );
}
