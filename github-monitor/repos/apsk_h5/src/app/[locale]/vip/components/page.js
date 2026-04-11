"use client";

import Image from "next/image";
import { useTranslations } from "next-intl";
import { IMAGES } from "@/constants/images";
import { useContext, useEffect, useMemo, useState } from "react";
import { useRouter } from "next/navigation";
import {
  useClaimFirstBonusMutation,
  useClaimMonthlyBonusMutation,
  useClaimWeeklyBonusMutation,
  useGetVipRemainTargetQuery,
  useVipListQuery,
} from "@/services/vipApi";
import { extractError, getMemberInfo, getUserLevel } from "@/utils/utility";
import SharedLoading from "@/components/shared/LoadingBar/ShareLoading";
import { skipToken } from "@reduxjs/toolkit/query";
import { UIContext } from "@/contexts/UIProvider";
import { toast } from "react-hot-toast";
import EmptyRecord from "@/components/shared/EmptyRecord";
import { LOCAL_STORAGE_NAMES } from "@/constants/cookies";
import { useLazyGetMemberViewQuery } from "@/services/authApi";
export default function VipPage() {
  const t = useTranslations();
  const router = useRouter();
  const [claimFirstBonus] = useClaimFirstBonusMutation();
  const [claimWeeklyBonus] = useClaimWeeklyBonusMutation();
  const [claimMonthlyBonus] = useClaimWeeklyBonusMutation();

  const [activeTab, setActiveTab] = useState("firstbonus");
  const info = useMemo(() => getMemberInfo(), []);
  const { setLoading } = useContext(UIContext);

  const {
    data: remainTargetData,
    refetch: refetchRemainTarget,
    isLoading: isRemainLoading,
    isFetching: isRemainFetching,
  } = useGetVipRemainTargetQuery(info?.member_id || skipToken);
  const [triggerGetMemberView] = useLazyGetMemberViewQuery();
  const [level, setLevel] = useState(0);

  // 1️⃣ Load from localStorage on client-side first
  useEffect(() => {
    if (typeof window === "undefined") return; // prevent SSR errors

    const saved = localStorage.getItem(LOCAL_STORAGE_NAMES.LEVEL);
    if (saved) {
      setLevel(Number(saved)); // load initial level from storage
    }
  }, []);

  // 2️⃣ Call API AFTER member_id exists (refresh newer value)
  useEffect(() => {
    if (!info?.member_id) return;

    triggerGetMemberView({ member_id: info.member_id })
      .unwrap()
      .then((res) => {
        if (res?.data?.vip) {
          setLevel(res.data.vip); // update UI
          localStorage.setItem(LOCAL_STORAGE_NAMES.LEVEL, res.data.vip); // save
        }
      })
      .catch((err) => {
        console.error("Failed to fetch member view:", err);
      });
  }, [info?.member_id]);
  // useEffect(() => {
  //   const member = getMemberInfo();
  //   setInfo(member);
  // }, []);

  const handleRefreshRemain = () => {
    if (!info?.member_id) return;
    refetchRemainTarget();
  };

  // ✅ Fetch VIP list from API (only when member_id exists)
  const {
    data,
    error,
    isLoading,
    isFetching,
    refetch: refetchVipList,
  } = useVipListQuery(
    {
      member_id: info?.member_id,
      type: activeTab,
    },
    { skip: !info?.member_id }
  );

  const vipList = data?.data || [];

  const handleOneClickClaim = async () => {
    if (!info?.member_id) return;

    try {
      setLoading(true);

      let res;

      // 🟡 CASE: First Bonus
      if (activeTab === "firstbonus") {
        res = await claimFirstBonus({
          member_id: info?.member_id,
          vip_id: null,
        }).unwrap();
      }

      // 🔵 CASE: Daily Bonus  <<-- no API you provided
      else if (activeTab === "dailybonus") {
        toast.error(t("vips.dailyBonusNotImplemented"));
        return;
      }

      // 🟣 CASE: Weekly Bonus
      else if (activeTab === "weeklybonus") {
        res = await claimWeeklyBonus(info.member_id).unwrap();
      }

      // 🟢 CASE: Monthly Bonus
      else if (activeTab === "monthlybonus") {
        res = await claimMonthlyBonus(info.member_id).unwrap();
      }

      // 🚫 No valid tab
      else {
        toast.error(t("vips.invalidBonusType"));
        return;
      }

      // 🎉 Success
      if (res?.status === true) {
        toast.success(res?.message || t("common.success"));

        // Refresh data
        await Promise.all([refetchVipList?.(), refetchRemainTarget?.()]);
      } else {
        toast.error(res?.message || t("common.error"));
      }
    } catch (err) {
      const result = extractError(err);
      toast.error(result.message || t("common.error"));
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="mx-auto max-w-[480px] min-h-dvh bg-[#00143D] text-white px-4 pb-8">
      {/* Action buttons */}
      <div className="flex justify-end gap-2 py-3">
        <button
          onClick={handleOneClickClaim}
          disabled={isRemainLoading || isRemainFetching}
          className="rounded-md bg-gradient-to-b from-[#FFC000] to-[#FE9121] px-4 py-2 text-sm font-semibold text-[#01133C]"
        >
          {t("vips.oneClickClaim")}
        </button>
        <button
          onClick={() => router.push("/transactions")}
          className="rounded-md bg-gradient-to-b from-[#FFC000] to-[#FE9121] px-4 py-2 text-sm font-semibold text-[#01133C]"
        >
          {t("vips.claimRecords")}
        </button>
      </div>

      {/* Current VIP box */}
      <div
        className="relative rounded-[21px] p-[3px]"
        style={{
          background: "linear-gradient(180deg, #F8AF07 0%, #FFFC86 100%)",
        }}
      >
        {/* Inner box */}
        <div className="rounded-[18px] bg-[#1B2D72] p-3">
          <div className="relative flex h-[92px] items-center justify-between p-3">
            {(() => {
              const currentLvl = Number(level || 0);
              const nextRow = vipList.find(
                (v) => Number(v.lvl) === currentLvl + 1
              );

              // --- use remain (from API)
              const remain = Number(remainTargetData?.remain || 0);

              return (
                <>
                  {/* ----- Left: Current VIP icon ----- */}
                  <div className="flex w-[20%] flex-col items-center gap-1 mt-4">
                    <Image
                      src={IMAGES.vip[`greenDiamond`]}
                      alt="current-vip"
                      width={42}
                      height={42}
                    />
                    <Image
                      src={IMAGES.vip[`vip${currentLvl}`]}
                      alt="vip-label"
                      width={46}
                      height={16}
                      className="object-contain"
                    />
                  </div>

                  {/* ----- Center: remain text ----- */}
                  <div className="z-10 flex w-[60%] flex-col">
                    <div className="flex justify-start mt-5 items-center gap-1 text-sm font-bold text-[#FFC000]">
                      <span className="text-white/80 font-normal">
                        {t("vips.upgradeNeed")}
                      </span>

                      {/* remain value */}
                      <span className="text-[#FFC000]">
                        {remain.toLocaleString()}
                      </span>

                      {/* refresh button */}
                      <Image
                        src={IMAGES.vip.refresh}
                        alt="refresh"
                        width={14}
                        height={14}
                        className={`cursor-pointer active:scale-95 ${
                          isRemainFetching ? "animate-spin opacity-60" : ""
                        }`}
                        onClick={() => {
                          if (info?.member_id && !isRemainFetching) {
                            refetchRemainTarget();
                          }
                        }}
                      />
                    </div>
                  </div>

                  {/* ----- Right: Next VIP icon ----- */}
                  <div className="flex w-[20%] flex-col items-center gap-1 mt-4">
                    {nextRow ? (
                      <>
                        <Image
                          src={IMAGES.vip[`greenDiamond`]}
                          alt="next-vip"
                          width={42}
                          height={42}
                        />
                        <Image
                          src={IMAGES.vip[`vip${currentLvl + 1}`]}
                          alt="next-vip-label"
                          width={46}
                          height={16}
                          className="object-contain"
                        />
                      </>
                    ) : (
                      <>
                        <Image
                          src={IMAGES.vip[`greenDiamond`]}
                          alt="next-vip"
                          width={42}
                          height={42}
                        />
                        <Image
                          src={IMAGES.vip[`vip${currentLvl + 1}`]}
                          alt="next-vip-label"
                          width={46}
                          height={16}
                          className="object-contain"
                        />
                      </>
                    )}
                  </div>

                  {/* Horizontal line */}
                  <div className="pointer-events-none absolute left-[19%] right-[19%] top-[80%]">
                    <div className="h-[2px] w-full rounded-full bg-[#F8AF07]" />
                  </div>
                </>
              );
            })()}
          </div>
        </div>
      </div>

      {/* Tabs */}
      <div className="mt-4 flex items-center justify-around text-sm">
        {[
          { key: "firstbonus", label: t("vips.section.firstbonus") },
          // { key: "dailybonus", label: t("vips.section.dailybonus") },
          { key: "weeklybonus", label: t("vips.section.weeklybonus") },
          { key: "monthlybonus", label: t("vips.section.monthlybonus") },
        ].map((tab) => {
          const isActive = activeTab === tab.key;
          return (
            <button
              key={tab.key}
              onClick={() => setActiveTab(tab.key)}
              className={`relative pb-2 text-sm whitespace-nowrap transition-colors duration-200 ${
                isActive ? "text-[#F8AF07]" : "text-[#F8AF0780]"
              }`}
            >
              {tab.label}
              {isActive && (
                <span className="absolute left-0 -bottom-[2px] h-[3px] w-full rounded-sm bg-[#F8AF07]" />
              )}
            </button>
          );
        })}
      </div>

      {/* Table */}
      <div className="mt-3 px-4 py-3 bg-[#162344] rounded-md min-h-[300px]">
        <table className="w-full text-xs table-fixed">
          <thead className="text-white/60">
            <tr>
              <th className="pb-2 text-left text-[#1877F2] w-1/4">
                {t("vips.col.level")}
              </th>
              <th className="pb-2 text-center text-[#1877F2] w-2/4">
                {t("vips.col.needNormal")}
              </th>
              <th className="pb-2 text-center text-[#1877F2] w-1/4">
                {t("vips.col.reward")}
              </th>
            </tr>
          </thead>

          <tbody>
            {vipList.map((row) => {
              const score = parseFloat(row.score || 0);
              const max = parseFloat(row.max_amount || 0);
              const pct = max > 0 ? Math.min((score / max) * 100, 100) : 0;
              const reward = parseFloat(row[activeTab] || 0).toFixed(2);

              const isCurrentLevel = row.lvl === level;
              const showProgress = score > 0 && isCurrentLevel;

              return (
                <tr key={row.vip_id} className="border-t border-white/10">
                  {/* VIP icon (1/4) */}
                  <td className="py-3 w-1/4">
                    <div className="flex items-center gap-2">
                      <Image
                        src={IMAGES.vip[`vip${row.lvl}`]}
                        alt={`vip${row.lvl}`}
                        width={50}
                        height={50}
                        className="w-[50px] h-auto object-contain"
                      />
                    </div>
                  </td>

                  {/* Progress or max (2/4) */}
                  <td className="py-2 text-center align-middle w-2/4">
                    {showProgress ? (
                      <div className="flex items-center justify-center w-full">
                        <div className="w-[120px]">
                          <div
                            className="relative h-[14px] w-full overflow-hidden rounded-full bg-white shadow-[inset_0_0_0_1px_rgba(0,0,0,0.06)]"
                            role="progressbar"
                            aria-valuenow={score}
                            aria-valuemin={0}
                            aria-valuemax={max}
                          >
                            <div
                              className="h-full rounded-full transition-[width] duration-500 ease-out"
                              style={{
                                width: `${pct}%`,
                                background:
                                  "linear-gradient(180deg,#F8AF07 0%,#FFFC86 100%)",
                              }}
                            />

                            <div className="absolute inset-0 grid place-items-center">
                              <span className="text-[11px] font-semibold text-[#00143D] tabular-nums">
                                {score.toLocaleString()}/{max.toLocaleString()}
                              </span>
                            </div>
                          </div>
                        </div>
                      </div>
                    ) : (
                      max.toLocaleString()
                    )}
                  </td>

                  {/* Reward (1/4) */}
                  <td className="py-2 text-center text-[#FFC000] font-semibold w-1/4">
                    {reward}
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>

        {isLoading && <SharedLoading />}
        {!isLoading && !isFetching && vipList.length === 0 && <EmptyRecord />}
      </div>

      {/* Rules */}
      <div className="mt-3 px-4 py-5 bg-[#041A44]">
        <h2 className="text-base font-bold mb-4">{t("vips.rules.title")}</h2>
        <ol className="list-decimal pl-5 space-y-3 text-xs leading-5 text-white/80">
          <li>{t("vips.rules.item1")}</li>
          <li>{t("vips.rules.item2")}</li>
          <li>{t("vips.rules.item3")}</li>
          <li>{t("vips.rules.item4")}</li>
          <li>{t("vips.rules.item5")}</li>
          <li>{t("vips.rules.item6")}</li>
          <li>{t("vips.rules.item7")}</li>
        </ol>
      </div>
    </div>
  );
}
