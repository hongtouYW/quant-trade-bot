"use client";
import { useContext, useEffect, useMemo, useState } from "react";
import Image from "next/image";
import { useRouter } from "next/navigation";
import { useTranslations } from "next-intl";
import { useGetNotificationListQuery } from "@/services/notificationApi";
import { formatDate, getMemberInfo } from "@/utils/utility";
import { IMAGES } from "@/constants/images";
import { UIContext } from "@/contexts/UIProvider";
import EmptyRecord from "@/components/shared/EmptyRecord";
import SharedLoading from "@/components/shared/LoadingBar/ShareLoading";

export default function NotificationList() {
  const router = useRouter();
  const t = useTranslations();
  const info = useMemo(() => getMemberInfo(), []);

  const { setConfirmConfig } = useContext(UIContext);

  // 🧩 Fetch notification list when member_id is available
  const { data, isLoading } = useGetNotificationListQuery(
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
              key={item.notification_id}
              onClick={() => {
                sessionStorage.setItem(
                  "detail_item",
                  JSON.stringify({ ...item, _type: "notification" })
                );

                router.push(`/notification/detail/${item.notification_id}`);
              }}
              className="flex items-start justify-between border-b border-white/5 pb-3"
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
