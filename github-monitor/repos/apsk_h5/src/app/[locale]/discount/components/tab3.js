"use client";

import Image from "next/image";
import { useContext, useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { useTranslations } from "next-intl";
import { IMAGES } from "@/constants/images";
import ReturnSideBar from "@/components/home/ReturnSideBar";
import { UIContext } from "@/contexts/UIProvider";

/* ---------- One card row ---------- */
function ReturnCard({
  progress = 1327,
  target = 3000,
  ratio = 0,
  claimable = 0,
}) {
  const t = useTranslations();
  const pct = Math.min(100, Math.max(0, Math.round((progress / target) * 100)));

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
              0.00
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
        <svg
          className="justify-self-end w-4 h-4 opacity-70"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          strokeWidth="2"
          strokeLinecap="round"
          strokeLinejoin="round"
        >
          <path d="M9 6l6 6-6 6" />
        </svg>

        {/* Row 2 — left (progress bar) */}
        <div className="mt-3">
          <div
            className="relative h-5 w-full overflow-hidden rounded-full bg-white shadow-[inset_0_0_0_1px_rgba(0,0,0,0.06)]"
            role="progressbar"
            aria-valuenow={progress}
            aria-valuemin={0}
            aria-valuemax={target}
          >
            {/* fill */}
            <div
              className="h-full rounded-full transition-[width] duration-500 ease-out"
              style={{
                width: `${pct}%`,
                background: "linear-gradient(180deg,#F8AF07 0%,#FFFC86 100%)",
              }}
            />

            {/* centered label (dark text works on both white + yellow) */}
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

        {/* Row 2 — spacer to keep column structure */}
        <div className="mt-3" />
      </div>
    </div>
  );
}

/* ---------- Page ---------- */
export default function DiscountsReturnScreen() {
  const t = useTranslations(); // <- no namespace
  const router = useRouter();
  const [activeId, setActiveId] = useState("newMember");

  const list = Array.from({ length: 6 }).map((_, i) => ({
    id: i + 1,
    progress: 1327,
    target: 3000,
    ratio: 0,
    claimable: 0,
  }));

  const { setConfirmConfig } = useContext(UIContext);

  useEffect(() => {
    // 🔔 show notice on first render
    setConfirmConfig({
      titleKey: "common.underMaintenanceTitle",
      messageKey: "common.underMaintenanceMessage",
      confirmKey: "common.ok",
      displayMode: "center", // ✅ <— show in center
      showCancel: true, // ✅ one-button mode
    });
  }, [setConfirmConfig]);

  return (
    <div className="mx-auto max-w-[480px] min-h-dvh bg-[#00143D] text-white">
      {/* Header (standard) */}

      {/* Body: left ReturnSideBar + right list */}
      <div className="flex">
        <div className="w-[72px] shrink-0">
          <ReturnSideBar
            activeId={activeId}
            onSelect={(it) => setActiveId(it.id)}
          />
        </div>

        <div className="flex-1 px-3 pb-24 space-y-4">
          {list.map((x) => (
            <ReturnCard
              key={x.id}
              progress={x.progress}
              target={x.target}
              ratio={x.ratio}
              claimable={x.claimable}
            />
          ))}
        </div>
      </div>
    </div>
  );
}
