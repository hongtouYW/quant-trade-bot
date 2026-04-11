"use client";

import { useContext, useEffect, useState } from "react";
import Image from "next/image";
import { useTranslations } from "next-intl";
import { useRouter } from "next/navigation";
import { IMAGES } from "@/constants/images";
import SubmitButton from "@/components/shared/SubmitButton";
import { UIContext } from "@/contexts/UIProvider";
import { extractError, formatDate, getMemberInfo } from "@/utils/utility";
import {
  useClaimFirstBonusMutation,
  useClaimMonthlyBonusMutation,
  useClaimnVipBonusAllMutation,
  useClaimWeeklyBonusMutation,
  useGetVipBonusHistoryQuery,
} from "@/services/vipApi";
import EmptyRecord from "@/components/shared/EmptyRecord";
import SharedLoading from "@/components/shared/LoadingBar/ShareLoading";
import { toast } from "react-hot-toast";
function Chevron({ open }) {
  return (
    <svg
      className={`w-4 h-4 opacity-70 transform transition-transform ${
        open ? "rotate-180" : "rotate-0"
      }`}
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
    >
      <path d="M6 9l6 6 6-6" />
    </svg>
  );
}

/** One expandable record card */

export default function DiscountRecordsPage() {
  const t = useTranslations();
  const router = useRouter();

  // user id for API
  const info = getMemberInfo();
  const userId = info?.member_id ? String(info.member_id) : null;
  const [claimAll] = useClaimnVipBonusAllMutation();
  const { setLoading } = useContext(UIContext);
  const { data, isLoading, isFetching, refetch } = useGetVipBonusHistoryQuery({
    member_id: info?.member_id,
    startdate: null,
    enddate: null,
  });
  const list = data?.data || [];

  const [claimFirstBonus, { isLoading: loadingFirst }] =
    useClaimFirstBonusMutation();

  const [claimWeeklyBonus, { isLoading: loadingWeekly }] =
    useClaimWeeklyBonusMutation();

  const [claimMonthlyBonus, { isLoading: loadingMonthly }] =
    useClaimMonthlyBonusMutation();

  const handleOneClickClaim = async () => {
    try {
      setLoading(true);

      const res = await claimAll(info.member_id).unwrap();

      if (res?.status === true) {
        toast.success(t("discounts.claimAllSuccess")); // 成功领取
        refetch(); // refresh history list
      } else {
        toast.error(res?.message || t("discounts.claimFail"));
      }
    } catch (err) {
      const result = extractError(err);
      toast.error(result.message || t("common.error"));
    } finally {
      setLoading(false);
    }
  };

  const handleClaim = async (item) => {
    try {
      setLoading(true);

      const member_id = info?.member_id;
      if (!member_id) {
        toast.error(t("common.error"));
        return;
      }

      let res;

      // ⭐ Follow the example: call API based on type
      if (item.type === "firstbonus") {
        res = await claimFirstBonus({
          member_id: member_id,
          vip_id: item.vip_id,
        }).unwrap();
      } else if (item.type === "weeklybonus") {
        res = await claimWeeklyBonus(member_id).unwrap();
      } else if (item.type === "monthlybonus") {
        res = await claimMonthlyBonus(member_id).unwrap();
      } else {
        toast.error(t("common.error"));
        return;
      }

      // ⭐ Handle success like your example
      if (res?.status === true) {
        toast.success(t("discounts.claimSuccess"));
        refetch(); // refresh list
      } else {
        toast.error(res?.message || t("discounts.claimFail"));
      }
    } catch (err) {
      // ⭐ Same error handler style
      const result = extractError(err);
      toast.error(result.message || t("common.error"));
    } finally {
      setLoading(false);
    }
  };

  const redeemRecord = async () => {
    try {
      setLoading(true);

      const res = await claimAll(info.member_id).unwrap();

      if (res?.status === true) {
        toast.success(t("discounts.claimAllSuccess")); // 成功领取
        refetch(); // refresh history list
      } else {
        toast.error(res?.message || t("discounts.claimFail"));
      }
    } catch (err) {
      const result = extractError(err);
      toast.error(result.message || t("common.error"));
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="relative mx-auto max-w-[480px] min-h-dvh bg-[#00143D] text-white px-4 pb-4 flex flex-col">
      {/* Loading */}
      {isLoading && <SharedLoading />}

      {/* Scrollable content */}
      <div className="flex-1 overflow-y-auto pt-2 pb-[120px]">
        {/* reserve space so items never hide behind bottom button */}

        {!isLoading && !isFetching && list.length === 0 && <EmptyRecord />}

        {!isLoading && list.length > 0 && (
          <div className="space-y-2">
            {list.map((it, idx) => (
              <div
                key={idx}
                className="grid grid-cols-[28px_1fr_auto] items-center gap-2 bg-[#162344] py-3 px-3 rounded-md"
              >
                {/* icon */}
                <div className="h-9 w-9 grid place-items-center">
                  <Image
                    src={IMAGES.discount.deposit}
                    alt="deposit"
                    width={30}
                    height={30}
                  />
                </div>

                {/* text */}
                <div className="min-w-0">
                  <p className="text-sm leading-6">{it.template}</p>
                  <p className="text-[13px] text-white/60 leading-5">
                    {it.created_on ? formatDate(it.created_on) : "-"}
                  </p>
                </div>

                {/* action */}
                <div className="ml-2">
                  {it.status === 0 ? (
                    <button
                      onClick={() => handleClaim(it)}
                      className="rounded-md px-4 py-2 text-sm font-medium text-[#00143D]"
                      style={{
                        background:
                          "linear-gradient(180deg,#F8AF07 0%,#FFFC86 100%)",
                      }}
                    >
                      {t("discounts.claim")}
                    </button>
                  ) : (
                    <span className="text-sm font-medium text-[#27E16D]">
                      {t("discounts.success")}
                    </span>
                  )}
                </div>

                {/* divider */}
              </div>
            ))}
          </div>
        )}
      </div>

      {/* FIXED bottom button */}

      {list.length > 0 && list.some((it) => it.status === 0) && (
        <div className="fixed bottom-[85px] left-0 right-0 px-4 pb-3 bg-[#00143D] z-[50] mx-auto max-w-[480px]">
          <SubmitButton onClick={handleOneClickClaim}>
            {t("discounts.oneClickClaim")}
          </SubmitButton>
        </div>
      )}
    </div>
  );
}
