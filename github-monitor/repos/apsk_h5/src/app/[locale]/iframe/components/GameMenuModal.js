"use client";

import { useSearchParams } from "next/navigation";
import { useRouter } from "next/navigation";
import { useEffect, useRef, useState } from "react";
import { useTranslations } from "next-intl";
import Image from "next/image";
export default function GameMenuModal({
  gameMemberId,

  onClose,
  onRefresh,
  onWithdraw,
  onExit,
}) {
  const t = useTranslations("gameMenu");

  return (
    <div className="fixed inset-0 z-[200] bg-black/60 backdrop-blur-sm flex justify-center items-center">
      {/* WRAPPER – modal + close button */}
      <div className="relative w-[90%] max-w-[420px]">
        {/* Modal panel */}
        <div className="rounded-2xl bg-[#071741] text-white pb-8 pt-6 px-6 text-center shadow-2xl">
          <h3 className="text-lg font-semibold mb-6">{t("choose")}</h3>

          <div className="flex justify-center gap-16 mb-8">
            <button
              onClick={() => onRefresh(gameMemberId)}
              className="flex flex-col items-center gap-2"
            >
              <Image
                src="/images/games/icon-refresh-yellow.webp"
                width={55}
                height={55}
                alt="refresh"
              />
              <span className="text-sm">{t("refresh")}</span>
            </button>

            <button
              onClick={() => onWithdraw(gameMemberId)}
              className="flex flex-col items-center gap-2"
            >
              <Image
                src="/images/games/icon-withdraw-yellow.webp"
                width={46}
                height={46}
                alt="withdraw"
              />
              <span className="text-sm">{t("withdraw")}</span>
            </button>
          </div>

          <button
            onClick={() => onExit(gameMemberId)}
            className="w-full py-3 rounded-full border border-[#F8AF07] text-[#F8AF07]
        text-sm font-semibold active:scale-95"
          >
            {t("exitGame")}
          </button>
        </div>

        {/* 🔵 CLOSE BUTTON right under modal */}
        <button
          onClick={onClose}
          className="
    absolute 
    top-0 
    right-0
    w-10 h-10 
    flex items-center justify-center
  "
        >
          <Image
            src="/images/games/icon-close.webp"
            width={26}
            height={26}
            alt="close"
            className="object-contain"
          />
        </button>
      </div>
    </div>
  );
}
