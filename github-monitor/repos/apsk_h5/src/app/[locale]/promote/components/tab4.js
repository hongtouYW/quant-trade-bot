"use client";

import { useTranslations } from "next-intl";
import { useEffect, useState } from "react";
import { skipToken } from "@reduxjs/toolkit/query";

import SharedLoading from "@/components/shared/LoadingBar/ShareLoading";
import EmptyRecord from "@/components/shared/EmptyRecord";
import { getMemberInfo } from "@/utils/utility";
import { useGetCommissionListQuery } from "@/services/referralApi";

export default function MyCommissionTab() {
  const t = useTranslations();
  const [info, setInfo] = useState(null);

  useEffect(() => {
    setInfo(getMemberInfo());
  }, []);

  const { data, isLoading, isFetching } = useGetCommissionListQuery(
    info?.member_id ? { member_id: info.member_id } : skipToken,
    {
      // ⭐ Cache for 60 seconds
      keepUnusedDataFor: 60,

      // ⭐ Always refetch when component mounts (safe)
      refetchOnMountOrArgChange: true,

      // ⭐ Always refetch when user returns to page/tab
      refetchOnWindowFocus: true,

      // ⭐ If network drops & reconnect → refresh
      refetchOnReconnect: true,
    }
  );

  const items = data?.data || [];

  return (
    <div className="space-y-4 px-4">
      {/* Loading */}
      {isLoading && <SharedLoading />}

      {/* Empty */}
      {!isLoading && items.length === 0 && <EmptyRecord />}

      {/* List */}
      {!isLoading &&
        items.length > 0 &&
        items.map((it, i) => (
          <div
            key={i}
            className="rounded-md border border-white/40 p-4 text-[15px] leading-7"
          >
            <div className="flex items-center justify-between">
              <span className="text-white/90">
                {t("myCommission.username")}
              </span>
              <span className="text-white">{it.username}</span>
            </div>

            <div className="flex items-center justify-between">
              <span className="text-white/90">{t("myCommission.userId")}</span>
              <span className="text-white">{it.member_id}</span>
            </div>

            <div className="flex items-center justify-between">
              <span className="text-white/90">
                {t("myCommission.recharge")}
              </span>
              <span className="text-white">{it.recharge}</span>
            </div>

            <div className="flex items-center justify-between">
              <span className="text-white/90">{t("myCommission.time")}</span>
              <span className="text-white">{it.created_on || it.time}</span>
            </div>

            <div className="mt-1 flex items-center justify-between">
              <span className="text-white/90">
                {t("myCommission.commission")}
              </span>
              <span className="font-semibold text-[#FFC000]">
                {it.commission}
              </span>
            </div>
          </div>
        ))}
    </div>
  );
}
