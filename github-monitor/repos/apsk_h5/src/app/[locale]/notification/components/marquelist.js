"use client";
import { useContext, useEffect, useState } from "react";
import Image from "next/image";
import { useRouter } from "next/navigation";
import { useTranslations } from "next-intl";
import { useGetNotificationListQuery } from "@/services/notificationApi";
import { formatDate, getMemberInfo } from "@/utils/utility";
import { IMAGES } from "@/constants/images";
import { UIContext } from "@/contexts/UIProvider";
import EmptyRecord from "@/components/shared/EmptyRecord";
import SharedLoading from "@/components/shared/LoadingBar/ShareLoading";
import { useGetSliderListQuery } from "@/services/commonApi";

export default function MarqueList() {
  const router = useRouter();
  const t = useTranslations();
  const info = getMemberInfo();

  const { setConfirmConfig } = useContext(UIContext);

  // useEffect(() => {
  //   // 🔔 show notice on first render
  //   setConfirmConfig({
  //     titleKey: "common.underMaintenanceTitle",
  //     messageKey: "common.underMaintenanceMessage",
  //     confirmKey: "common.ok",
  //     displayMode: "center", // ✅ <— show in center
  //     showCancel: true, // ✅ one-button mode
  //   });
  // }, [setConfirmConfig]);

  // 🧩 Get member info once on mount

  // 🧩 Fetch notification list when member_id is available
  const { data, isLoading } = useGetSliderListQuery(
    info?.member_id ? { member_id: info.member_id } : {},
    { skip: !info?.member_id }
  );

  const list = data?.data || [];

  return (
    <div className="mx-auto max-w-[480px] min-h-dvh bg-[#00143D] text-white px-4 pb-8">
      {/* 🔹 List */}
      <div className="mt-4 space-y-5">
        {isLoading ? (
          // 🌀 Loading spinner
          <SharedLoading />
        ) : !isLoading && list.length === 0 ? (
          // 🚫 Empty state
          <EmptyRecord></EmptyRecord>
        ) : (
          // 📋 Notification list
          list.map((item) => (
            <div
              key={item.slider_id}
              className="flex items-start justify-between border-b border-white/5 pb-3 cursor-pointer"
              onClick={() => {
                sessionStorage.setItem(
                  "detail_item",
                  JSON.stringify({ ...item, _type: "slider" })
                );

                router.push(`/notification/detail/${item.slider_id}`);
              }}
            >
              <div className="flex items-start space-x-3">
                <Image
                  src={IMAGES.iconBell}
                  alt="bell"
                  width={18}
                  height={18}
                  className="mt-[2px]"
                />
                <div>
                  <p className="text-sm">{item.title}</p>
                  <p className="text-xs text-white/50 mt-1">
                    {formatDate(item.created_on)}
                  </p>
                </div>
              </div>

              <div className="flex items-center space-x-2">
                {!item.is_read && (
                  <Image
                    src={IMAGES.iconredDot}
                    alt="unread"
                    width={8}
                    height={8}
                    className="object-contain"
                  />
                )}
                <Image
                  src={IMAGES.blueRight}
                  alt="more"
                  width={18}
                  height={18}
                  className="object-contain opacity-60"
                />
              </div>
            </div>
          ))
        )}
      </div>
    </div>
  );
}
