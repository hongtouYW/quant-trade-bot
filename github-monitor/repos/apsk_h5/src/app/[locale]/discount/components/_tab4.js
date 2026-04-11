"use client";

import { useContext, useEffect, useState } from "react";
import Image from "next/image";
import { useTranslations } from "next-intl";
import { useRouter } from "next/navigation";
import { IMAGES } from "@/constants/images";
import SubmitButton from "@/components/shared/SubmitButton";
import { UIContext } from "@/contexts/UIProvider";
import { getMemberInfo } from "@/utils/utility";
import { useGetVipBonusHistoryQuery } from "@/services/vipApi";

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
function RecordCard({
  validBet = 0,
  ratio = 0,
  progress = 0,
  target = 3000,
  claimable = 0,
  items = [],
  defaultOpen = true,
}) {
  const t = useTranslations();
  const [open, setOpen] = useState(defaultOpen);
  const pct = Math.max(0, Math.min(100, Math.round((progress / target) * 100)));

  return (
    <div className="rounded-2xl bg-[#0B245C] px-4 py-3 shadow-[0_6px_18px_rgba(0,0,0,0.25)] ring-1 ring-white/5">
      {/* unified grid so every right label lines up vertically */}
      <div className="grid grid-cols-[1fr_148px_16px] items-center gap-x-2">
        {/* Row 1 — left */}
        <div className="min-w-0 flex items-center gap-2">
          <div className="grid h-4 w-4 place-items-center">
            <Image
              src={IMAGES.discount.refreshCircle}
              alt="refresh"
              width={15}
              height={15}
              className="w-full"
            />
          </div>
          <p className="text-sm text-white/90 truncate">
            {t("discounts.validBet")}{" "}
            <span className="font-semibold text-[#FFC000] tabular-nums">
              {validBet.toFixed(2)}
            </span>
          </p>
        </div>

        {/* Row 1 — right (LEFT aligned; fixed label width so % starts at same x) */}
        <div className="justify-self-start grid grid-cols-[72px_auto] items-center text-sm text-white/90">
          <span className="truncate">{t("discounts.returnRatio")}</span>
          <span className="font-semibold tabular-nums">
            {ratio.toFixed(2)}%
          </span>
        </div>

        {/* Row 1 — chevron */}
        <button
          aria-label="toggle"
          onClick={() => setOpen((o) => !o)}
          className="justify-self-end"
        >
          <Chevron open={open} />
        </button>

        {/* Row 2 — left (progress bar, white track) */}
        <div className="mt-3">
          <div
            className="relative h-5 w-full overflow-hidden rounded-full bg-white shadow-[inset_0_0_0_1px_rgba(0,0,0,0.06)]"
            role="progressbar"
            aria-valuenow={progress}
            aria-valuemin={0}
            aria-valuemax={target}
          >
            <div
              className="h-full rounded-full transition-[width] duration-500 ease-out"
              style={{
                width: `${pct}%`,
                background: "linear-gradient(180deg,#F8AF07 0%,#FFFC86 100%)",
              }}
            />
            <div className="absolute inset-0 grid place-items-center">
              <span className="text-[13px] font-semibold text-[#00143D] tabular-nums">
                {progress.toLocaleString()}/{target.toLocaleString()}
              </span>
            </div>
          </div>
        </div>

        {/* Row 2 — right (LEFT aligned; same fixed label width so 0.00 lines up) */}
        <div className="mt-3 justify-self-start grid grid-cols-[72px_auto] items-center text-sm text-white/90">
          <span className="truncate">{t("discounts.claimable")}</span>
          <span className="font-semibold text-[#FFC000] tabular-nums">
            {claimable.toFixed(2)}
          </span>
        </div>

        {/* Row 2 — spacer */}
        <div className="mt-3" />
      </div>

      {/* Expanded list */}
      {open && (
        <div className="mt-4 space-y-4">
          {items.map((it, idx) => (
            <div
              key={it.id ?? idx}
              className="grid grid-cols-[28px_1fr_auto] items-center gap-3"
            >
              {/* left icon */}
              <div className="h-9 w-9 grid place-items-center ">
                <Image
                  src={IMAGES.discount.deposit}
                  alt="deposit"
                  width={30}
                  height={30}
                />
              </div>

              {/* text */}
              <div className="min-w-0">
                <p className="text-sm leading-6">
                  {/* examples: 待领取 +30 points / 已领取 +50 points 进 eWallet */}
                  {it.title}
                </p>
                <p className="text-[13px] text-white/60 leading-5">{it.time}</p>
              </div>

              {/* action / status */}
              <div className="ml-2">
                {it.status === "pending" ? (
                  <button
                    onClick={it.onClaim}
                    className="rounded-md px-4 py-2 text-sm font-medium text-[#00143D]"
                    style={{
                      background:
                        "linear-gradient(180deg, #F8AF07 0%, #FFFC86 100%)",
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
              {idx !== items.length - 1 && (
                <div className="col-span-3 mt-3 border-t border-white/10" />
              )}
            </div>
          ))}

          {/* one-click claim */}
          <div className="pt-2">
            <SubmitButton onClick={() => console.log("one-click claim")}>
              {t("discounts.oneClickClaim")}
            </SubmitButton>
          </div>
        </div>
      )}
    </div>
  );
}

export default function DiscountRecordsPage() {
  const t = useTranslations();
  const router = useRouter();

  // user id for API
  const info = getMemberInfo();
  const userId = info?.member_id ? String(info.member_id) : null;

  const { data, isLoading } = useGetVipBonusHistoryQuery({
    member_id: info?.member_id,
    startdate: null,
    enddate: null,
  });

  console.log(data);

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

  // demo data
  const cards = [
    {
      validBet: 232,
      ratio: 0,
      progress: 232,
      target: 3000,
      claimable: 60,
      defaultOpen: true,
      items: [
        {
          title: t("discounts.toClaimPoints", { points: 30 }),
          time: "21 Dec 2025 , 10:50PM",
          status: "pending",
        },
        {
          title: t("discounts.toClaimPoints", { points: 30 }),
          time: "20 Dec 2025 , 3:21PM",
          status: "pending",
        },
        {
          title: t("discounts.claimedIntoWallet", { points: 50 }),
          time: "13 Dec 2025 , 2:59AM",
          status: "success",
        },
        {
          title: t("discounts.claimedIntoWallet", { points: 20 }),
          time: "13 Dec 2025 , 1:29AM",
          status: "success",
        },
        {
          title: t("discounts.claimedIntoWallet", { points: 40 }),
          time: "13 Dec 2025 , 12:02AM",
          status: "success",
        },
      ],
    },
    {
      validBet: 500,
      ratio: 0,
      progress: 500,
      target: 3000,
      claimable: 58,
      defaultOpen: false,
      items: [],
    },
    {
      validBet: 1000,
      ratio: 0,
      progress: 1000,
      target: 3000,
      claimable: 86,
      defaultOpen: false,
      items: [],
    },
    {
      validBet: 0,
      ratio: 0,
      progress: 0,
      target: 3000,
      claimable: 0,
      defaultOpen: false,
      items: [],
    },
    {
      validBet: 0,
      ratio: 0,
      progress: 0,
      target: 3000,
      claimable: 0,
      defaultOpen: false,
      items: [],
    },
    {
      validBet: 0,
      ratio: 0,
      progress: 0,
      target: 3000,
      claimable: 0,
      defaultOpen: false,
      items: [],
    },
  ];

  return (
    <div className="mx-auto max-w-[480px] min-h-dvh bg-[#00143D] text-white px-4 pb-8">
      {/* Cards */}
      <div className="space-y-4">
        {cards.map((c, i) => (
          <RecordCard key={i} {...c} />
        ))}
      </div>
    </div>
  );
}
