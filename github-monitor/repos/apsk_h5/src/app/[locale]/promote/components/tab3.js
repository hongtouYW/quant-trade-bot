"use client";

import { useTranslations } from "next-intl";
import { useGetCommissionTotalListQuery } from "@/services/authApi";
import { getMemberInfo } from "@/utils/utility";
import { useGetMyCommissionTotalListQuery } from "@/services/referralApi";
import SharedLoading from "@/components/shared/LoadingBar/ShareLoading";
import EmptyRecord from "@/components/shared/EmptyRecord";

export default function MyPerformanceTab() {
  const t = useTranslations();
  const info = getMemberInfo();

  const { data, isLoading, isFetching } = useGetMyCommissionTotalListQuery(
    {
      member_id: info?.member_id,
      startdate: null,
      enddate: null,
    },
    {
      skip: !info?.member_id,
      // ⭐ Keep cache but still refetch when needed
      keepUnusedDataFor: 60,

      // ⭐ SAFE: Always refetch when page mounts
      refetchOnMountOrArgChange: true,

      // ⭐ SAFE: Refetch when tab comes back into focus
      refetchOnWindowFocus: true,

      // ⭐ Optional: Refetch when reconnect network
      refetchOnReconnect: true,
    }
  );

  // API returns array in data
  const list = Array.isArray(data?.data) ? data.data : [];

  return (
    <div className="space-y-4 px-4">
      {/* Loading */}
      {isLoading && <SharedLoading />}

      {/* Empty */}
      {!isLoading && list.length === 0 && <EmptyRecord />}

      {/* List */}
      {list.map((item, idx) => (
        <div
          key={idx}
          className="rounded-md border border-white/40 p-4 text-sm leading-6"
        >
          <div className="flex items-center justify-between">
            <span>{t("myPerformance.date")}</span>
            <span>{item.date}</span>
          </div>

          <div className="flex mt-2  items-center justify-between">
            <span>{t("myPerformance.totalCommission")}</span>
            <span>
              RM {Number(item.total_commission || 0).toLocaleString()}
            </span>
          </div>

          <div className="flex mt-2  items-center justify-between">
            <span>{t("myPerformance.people")}</span>
            <span>{item.total_people}</span>
          </div>
        </div>
      ))}
    </div>
  );
}
