"use client";

import Image from "next/image";
import { useRouter } from "next/navigation";
import { useTranslations } from "next-intl";
import { IMAGES } from "@/constants/images";
import { useContext, useEffect, useState } from "react";

// ⭐ API imports

import { getMemberInfo } from "@/utils/utility";
import { useGetPerformanceSummaryQuery } from "@/services/referralApi";
import { UIContext } from "@/contexts/UIProvider";

export default function MyDataPage() {
  const router = useRouter();
  const t = useTranslations();
  const { setLoading } = useContext(UIContext);

  // -------------------------
  // Quick filter tabs
  // -------------------------
  const filterTabs = [
    { key: "today", label: t("myData.filter.today") },
    { key: "yesterday", label: t("myData.filter.yesterday") },
    { key: "thisWeek", label: t("myData.filter.thisWeek") },
    { key: "lastWeek", label: t("myData.filter.lastWeek") },
    { key: "thisMonth", label: t("myData.filter.thisMonth") },
    { key: "lastMonth", label: t("myData.filter.lastMonth") },
  ];

  // -------------------------
  // Date helper functions
  // -------------------------
  function truncateDay(d) {
    const x = new Date(d);
    x.setHours(0, 0, 0, 0);
    return x;
  }
  function addDays(d, n) {
    const x = new Date(d);
    x.setDate(x.getDate() + n);
    return x;
  }
  function startOfWeek(d) {
    const x = truncateDay(d);
    const dow = x.getDay();
    const delta = (dow + 6) % 7;
    return addDays(x, -delta);
  }
  function endOfWeek(d) {
    return addDays(startOfWeek(d), 6);
  }
  function startOfMonth(d) {
    return new Date(d.getFullYear(), d.getMonth(), 1);
  }
  function endOfMonth(d) {
    return new Date(d.getFullYear(), d.getMonth() + 1, 0);
  }
  function toApiDate(d) {
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(
      2,
      "0"
    )}-${String(d.getDate()).padStart(2, "0")}`;
  }

  // -------------------------
  // State setup
  // -------------------------
  const now = new Date();
  const [activeQuick, setActiveQuick] = useState("today");
  const [startDate, setStartDate] = useState(truncateDay(now));
  const [endDate, setEndDate] = useState(truncateDay(now));

  // -------------------------
  // Quick filter logic
  // -------------------------
  function setQuick(key) {
    const now = new Date();
    setActiveQuick(key);

    switch (key) {
      case "today": {
        const d = truncateDay(now);
        setStartDate(d);
        setEndDate(d);
        break;
      }
      case "yesterday": {
        const d = addDays(truncateDay(now), -1);
        setStartDate(d);
        setEndDate(d);
        break;
      }
      case "thisWeek": {
        setStartDate(startOfWeek(now));
        setEndDate(endOfWeek(now));
        break;
      }
      case "lastWeek": {
        const s = addDays(startOfWeek(now), -7);
        setStartDate(s);
        setEndDate(addDays(s, 6));
        break;
      }
      case "thisMonth": {
        setStartDate(startOfMonth(now));
        setEndDate(endOfMonth(now));
        break;
      }
      case "lastMonth": {
        const d = new Date(now.getFullYear(), now.getMonth() - 1, 1);
        setStartDate(startOfMonth(d));
        setEndDate(endOfMonth(d));
        break;
      }
    }
  }

  // -------------------------
  // API call
  // -------------------------
  const info = getMemberInfo();
  const userId = info?.member_id;

  const { data: summaryResp, isLoading } = useGetPerformanceSummaryQuery({
    member_id: userId,
    startdate: toApiDate(startDate),
    enddate: toApiDate(endDate),
  });

  // ⭐ Global loading bar logic
  useEffect(() => {
    setLoading(isLoading); // ❗ only first load shows loading
  }, [isLoading, setLoading]);

  // ⭐ handle loading bar
  // useEffect(() => {
  //   setLoading(isLoading);
  // }, [isLoading, setLoading]);

  const api = summaryResp?.data;

  // -------------------------
  // Map direct data (INCLUDING missing fields = "0")
  // -------------------------

  const directMapped = {
    // 新增直属人数
    newMember: api?.now?.recruit?.new?.totalcount ?? "0",

    // 首充
    firstTopupAmount: api?.now?.vip?.firstbonus?.totalamount ?? "0.00",
    firstTopupCount: api?.now?.vip?.firstbonus?.totalcount ?? "0",

    // 充值
    rechargeAmount: api?.now?.deposit?.totalamount ?? "0.00",
    rechargeCount: api?.now?.deposit?.totalcount ?? "0",

    // 提现
    withdrawAmount: api?.now?.withdraw?.totalamount ?? "0.00",
    withdrawCount: api?.now?.withdraw?.totalcount ?? "0",

    // 有效投注 & 输赢（ONLY from downline.game in new API）
    validBet: api?.downline?.game?.totalbetamount ?? "0",
    directWin: api?.downline?.game?.winloss ?? "0",

    // 已领取奖励
    rewardClaimed: api?.now?.vip?.reward ?? "0",

    // 直属业绩
    directPerformance: api?.now?.vip?.sales?.totalamount ?? "0.00",

    // 总佣金（直属佣金）
    totalCommission: api?.now?.vip?.commission?.totalamount ?? "0.00",
  };
  // -------------------------
  // Direct Data (ALL items visible)
  // -------------------------
  const directData = [
    { key: "newMember", value: directMapped.newMember },
    { key: "firstTopupAmount", value: directMapped.firstTopupAmount },
    // { key: "firstTopupTotal", value: directMapped.firstTopupTotal },
    { key: "firstTopupCount", value: directMapped.firstTopupCount },

    { key: "rechargeAmount", value: directMapped.rechargeAmount },
    { key: "rechargeCount", value: directMapped.rechargeCount },

    { key: "withdrawAmount", value: directMapped.withdrawAmount },
    { key: "withdrawCount", value: directMapped.withdrawCount },

    { key: "validBet", value: directMapped.validBet },

    { key: "rewardClaimed", value: directMapped.rewardClaimed },

    { key: "directWin", value: directMapped.directWin },

    { key: "directPerformance", value: directMapped.directPerformance },

    {
      key: "totalCommission",
      value: directMapped.totalCommission,
      highlight: true,
    },
  ];

  const teamMapped = {
    // 人数
    teamCount: api?.team?.recruit?.main?.totalcount ?? "0", // 团队人数
    directCount: api?.team?.recruit?.downline?.totalcount ?? "0", // 直属人数

    // 业绩
    totalPerformance: api?.team?.recruit?.main?.totalsales ?? "0.00", // 总业绩
    directPerformance: api?.team?.recruit?.downline?.totalsales ?? "0.00", // 直属业绩

    // 佣金
    totalCommission: api?.team?.recruit?.main?.totalcommission ?? "0.00", // 总佣金
    directCommission: api?.team?.recruit?.downline?.totalcommission ?? "0.00", // 直属佣金
    commissionAccumulated:
      api?.team?.recruit?.accumulate?.totalcommission ?? "0.00", // 累计佣金

    // 领取奖励
    rewardReceived: api?.team?.recruit?.main?.reward ?? "0", // 已领取
    directReward: api?.team?.recruit?.downline?.reward ?? "0", // 直属领取

    // 财务数据（直属）
    directRecharge: api?.team?.recruit?.downline?.totaldeposit ?? "0.00", // 直属累计充值金额
    directWithdraw: api?.team?.recruit?.downline?.totalwithdraw ?? "0.00", // 直属累计提现金额
    directValidBet: api?.team?.recruit?.downline?.betamount ?? "0", // 直属累计有效投注
    directWin: api?.team?.recruit?.downline?.winloss ?? "0", // 直属累计输赢
  };

  // const teamMapped = {
  //   // 人数
  //   teamCount: "1", // 团队人数 (main.totalcount)
  //   directCount: "2", // 直属人数 (downline.totalcount)

  //   // 业绩
  //   totalPerformance: "3.00", // 总业绩 (main.totalsales)
  //   directPerformance: "4.00", // 直属业绩 (downline.totalsales)

  //   // 佣金
  //   totalCommission: "5.00", // 总佣金 (main.totalcommission)
  //   directCommission: "6.00", // 直属佣金 (downline.totalcommission)
  //   commissionAccumulated: "7.00", // 累计佣金 (accumulate.totalcommission)

  //   // 领取奖励
  //   rewardReceived: "8", // 已领取 (main.reward)
  //   directReward: "9", // 累计直属领取 (downline.reward)

  //   // 财务数据（直属）
  //   directRecharge: "10.00", // 累计直属充值 (downline.totaldeposit)
  //   directWithdraw: "11.00", // 直属累计提现金额 (downline.totalwithdraw)
  //   directValidBet: "12", // 直属累计有效投注 (downline.betamount)
  //   directWin: "13", // 直属累计输赢 (downline.winloss)
  // };

  // -------------------------
  // Team Data (always show all)
  // -------------------------
  // const teamData = [
  //   // 人数
  //   { key: "teamCount", value: "0" }, // ❌ API not provided
  //   {
  //     key: "directCount",
  //     value: teamMapped.newMember, // ✔ 新增直属人数
  //   },
  //   // { key: "otherCount", value: "0" }, // ❌ API not provided

  //   // 业绩
  //   { key: "totalPerformance", value: "0.00" }, // ❌ API not provided
  //   {
  //     key: "directPerformance",
  //     value: teamMapped.directPerformance, // ✔ 直属业绩
  //   },
  //   { key: "otherPerformance", value: "0.00" }, // ❌ API not provided

  //   // 佣金
  //   {
  //     key: "totalCommission",
  //     value: teamMapped.totalCommission, // ✔ 总佣金
  //     highlight: true,
  //   },
  //   {
  //     key: "directCommission",
  //     value: "0.00", // ❌ API not provided separately
  //     highlight: true,
  //   },
  //   // {
  //   //   key: "otherCommission",
  //   //   value: "0.00", // ❌ API not provided
  //   //   highlight: true,
  //   // },

  //   // 累计佣金
  //   {
  //     key: "commissionAccumulated",
  //     value: "0.00", // ❌ API not provided
  //     highlight: true,
  //   },
  //   {
  //     key: "commissionReceived",
  //     value: teamMapped.rewardClaimed, // ✔ 已领取奖励
  //     highlight: true,
  //   },
  //   // {
  //   //   key: "commissionPending",
  //   //   value: "0.00", // ❌ API not provided
  //   //   highlight: true,
  //   // },

  //   // 财务数据（直属）
  //   {
  //     key: "directRecharge",
  //     value: teamMapped.rechargeAmount, // ✔ 充值金额
  //   },
  //   {
  //     key: "directWithdraw",
  //     value: teamMapped.withdrawAmount, // ✔ 提现金额
  //   },
  //   {
  //     key: "directReward",
  //     value: teamMapped.rewardClaimed, // ✔ 领取奖励
  //   },
  //   {
  //     key: "directValidBet",
  //     value: teamMapped.validBet, // ✔ 有效投注
  //   },
  //   {
  //     key: "directWin",
  //     value: teamMapped.directWin, // ✔ 输赢
  //   },
  // ];

  const teamData = [
    // 人数
    { key: "teamCount", value: teamMapped.teamCount },
    { key: "directCount", value: teamMapped.directCount },

    // 业绩
    { key: "totalPerformance", value: teamMapped.totalPerformance },
    { key: "directPerformance", value: teamMapped.directPerformance },

    // 佣金（总 / 直属）
    {
      key: "totalCommission",
      value: teamMapped.totalCommission,
      highlight: true,
    },
    {
      key: "directCommission",
      value: teamMapped.directCommission,
      highlight: true,
    },

    // 累计佣金
    {
      key: "commissionAccumulated",
      value: teamMapped.commissionAccumulated,
      highlight: true,
    },

    // 已领取奖励
    {
      key: "commissionReceived",
      value: teamMapped.rewardReceived,
      highlight: true,
    },

    // 财务数据（直属）
    {
      key: "directRecharge",
      value: teamMapped.directRecharge,
    },
    {
      key: "directWithdraw",
      value: teamMapped.directWithdraw,
    },
    {
      key: "directReward",
      value: teamMapped.directReward,
    },
    {
      key: "directValidBet",
      value: teamMapped.directValidBet,
    },
    {
      key: "directWin",
      value: teamMapped.directWin,
    },
  ];

  return (
    <div className="mx-auto max-w-[480px] min-h-dvh bg-[#00143D] text-white px-4 pb-8">
      {/* Filter Tabs */}
      {/* <div className="mt-4 flex flex-wrap gap-2">
        {filterTabs.map((tab) => (
          <button
            key={tab.key}
            onClick={() => setQuick(tab.key)}
            className={`
              rounded-full px-4 py-1 text-sm border
              ${
                activeQuick === tab.key
                  ? "bg-[#FFC000] text-[#00143D] border-[#FFC000]"
                  : "text-[#FFC000] border-[#FFC000]"
              }
            `}
          >
            {tab.label}
          </button>
        ))}
      </div> */}

      {/* Direct Data Section */}
      <h2 className="mt-6 mb-2 font-semibold">
        {t("myData.directData.title")}
      </h2>
      <div className="grid grid-cols-3 gap-3">
        {directData.map((item) => (
          <div
            key={item.key}
            className="rounded-lg bg-[#0A1F58] p-3 text-center text-sm"
          >
            <p>{t(`myData.directData.${item.key}`)}</p>
            <p
              className={`mt-1 font-semibold ${
                item.highlight ? "text-[#FFC000]" : "text-white"
              }`}
            >
              {item.value}
            </p>
          </div>
        ))}
      </div>

      {/* Team Data Section */}
      <h2 className="mt-6 mb-2 font-semibold">{t("myData.teamData.title")}</h2>
      <div className="grid grid-cols-3 gap-3">
        {teamData.map((item) => (
          <div
            key={item.key}
            className="rounded-lg bg-[#0A1F58] p-3 text-center text-sm"
          >
            <p>{t(`myData.teamData.${item.key}`)}</p>
            <p
              className={`mt-1 font-semibold ${
                item.highlight ? "text-[#FFC000]" : "text-white"
              }`}
            >
              {item.value}
            </p>
          </div>
        ))}
      </div>
    </div>
  );
}
