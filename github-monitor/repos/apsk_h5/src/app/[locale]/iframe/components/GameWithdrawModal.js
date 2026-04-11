"use client";

import { useState, useEffect } from "react";
import Image from "next/image";
import toast from "react-hot-toast";
import { useTranslations } from "next-intl";

import { IMAGES } from "@/constants/images";
import {
  getMemberInfo,
  calcCredit,
  calcPoints,
  handleATMInput,
  extractError,
  gameName,
  getClientIp,
} from "@/utils/utility";
import { useLazyGetPlayerViewQuery } from "@/services/authApi";
import { useFromPointToCreditMutation } from "@/services/transactionApi";

export default function GameWithdrawModal({ gameName, gameMemberId, onClose }) {
  const t = useTranslations();

  // Get current logged user info internally
  const info = getMemberInfo();
  const memberId = info?.member_id;

  // RTK lazy query
  const [triggerGetPlayerView] = useLazyGetPlayerViewQuery();

  // INTERNAL STATES ONLY
  const [points, setPoints] = useState(0.0);
  const [credits, setCredits] = useState(0.0);

  const [fromAmount, setFromAmount] = useState("0.00");
  const [toAmount, setToAmount] = useState("0.00");
  const [fromPointToCredit] = useFromPointToCreditMutation();

  const [loading, setLoading] = useState(false);

  // FETCH LATEST BALANCE ON OPEN
  useEffect(() => {
    refreshBalances();
  }, []);

  const refreshBalances = async () => {
    if (!memberId || !gameMemberId) return;

    try {
      setLoading(true);

      const resp = await triggerGetPlayerView({
        gamemember_id: gameMemberId,
        member_id: memberId,
      }).unwrap();

      if (resp?.data) {
        // player points
        setPoints(Number(resp.data.balance));

        // credit (if provided by provider)
        if (resp.data.member?.balance) {
          setCredits(Number(resp.data.member.balance));
        }
      }
    } catch (err) {
      toast.error(extractError(err).message);
    } finally {
      setLoading(false);
    }
  };

  // ---------------- RENDER ----------------

  return (
    <div className="fixed inset-0 z-[300] bg-black/60 backdrop-blur-sm flex justify-center items-center">
      <div className="relative w-[92%] max-w-[420px]">
        {/* CARD – reduced padding */}
        <div className="rounded-2xl bg-[#071741] text-white px-4 py-5 shadow-2xl border border-[#FFC000]/40">
          {/* Title – reduced margin */}
          <h3 className="text-center text-base font-semibold py-2">
            {t("wallets.withdrawTitle")}
          </h3>

          {/* TOP BLUE BLOCK – reduced height & padding */}
          <div className="rounded-xl bg-[#0B1D48] px-3 py-2 mb-3">
            <div className="flex justify-between items-center">
              {/* LEFT */}
              <div>
                <p className="text-[13px] font-semibold leading-tight">
                  {gameName}
                </p>
                <p className="text-[13px] font-semibold -mt-1 leading-tight">
                  {t("common.points")}
                </p>
                <p className="text-[11px] text-white/40">ID:{gameMemberId}</p>

                <p className="text-[#FFC000] font-semibold text-base mt-1 leading-tight">
                  {points.toFixed(2)}
                </p>
              </div>

              {/* ARROW */}
              <Image
                src={IMAGES.games.iconTransfer}
                width={28}
                height={28}
                alt="arrow"
                unoptimized
                className={`opacity-80 ${loading ? "animate-spin" : ""}`}
              />

              {/* RIGHT */}
              <div className="text-right">
                <p className="text-[13px] font-semibold leading-tight">
                  {t("common.credit")}
                </p>
                <p className="text-[11px] text-white/40">ID:{memberId}</p>
                <p className="text-[#FFC000] font-semibold text-base mt-1 leading-tight">
                  {credits.toFixed(2)}
                </p>
              </div>
            </div>
          </div>

          {/* Label – smaller spacing */}
          <p className="text-center text-[13px] text-white/70 mb-1.5">
            {t("wallets.enterAmount")}
          </p>

          {/* INPUT ROW – reduced padding */}
          <div
            className="flex items-center rounded-full px-3 py-2 mb-3"
            style={{ border: "1.4px solid rgba(255,255,255,0.6)" }}
          >
            {/* Icon */}
            <span className="mr-2 grid h-6 w-6 place-items-center rounded-full bg-[#0E1F55]">
              <Image
                src={IMAGES.games.iconStar}
                width={16}
                height={16}
                alt="star"
              />
            </span>

            {/* Input */}
            <input
              inputMode="decimal"
              value={toAmount}
              onKeyDown={(e) => {
                // allow control keys
                if (
                  [
                    "Backspace",
                    "Delete",
                    "ArrowLeft",
                    "ArrowRight",
                    "Tab",
                  ].includes(e.key)
                )
                  return;

                // block non-digit
                if (!/^[0-9]$/.test(e.key)) {
                  e.preventDefault();
                }
              }}
              onChange={(e) => {
                // keep only digits
                let raw = e.target.value.replace(/\D/g, "");

                // convert to currency
                const cents = parseInt(raw || "0", 10);
                setToAmount((cents / 100).toFixed(2));
              }}
              className="flex-1 bg-transparent text-sm font-semibold text-[#FFC000] outline-none"
            />

            {/* Divider */}
            <span className="mx-3 h-4 w-px bg-white/40" />

            {/* ALL Button – reduced padding */}
            <button
              onClick={() => {
                const val = points.toFixed(2);
                setToAmount(val);
                setFromAmount(calcCredit(val));
              }}
              className="rounded-full px-3 py-1 text-xs font-semibold text-black"
              style={{
                background: "linear-gradient(180deg,#F8AF07 0%,#FFFC86 100%)",
              }}
            >
              {t("wallets.all")}
            </button>
          </div>

          {/* Convert Button – reduced height */}
          <button
            onClick={async () => {
              const amount = toAmount;
              if (!amount || amount <= 0) {
                toast.error("Please enter a valid amount");
                return;
              }
              try {
                setLoading(true);

                const payload = {
                  amount,
                  gamemember_id: gameMemberId,
                  ip: await getClientIp(),
                  member_id: info?.member_id,
                };
                var resp = await fromPointToCredit(payload).unwrap();
                if (resp.status) {
                  setCredits(Number(resp.member.balance));
                  setPoints(Number(resp.player.balance));
                }

                toast.success(t("wallets.transferSuccess"));
                setFromAmount("0.00");
                setToAmount("0.00");
              } catch (err) {
                const result = extractError(err);

                if (result.type === "validation") {
                  // Show under each field
                  Object.values(result.fieldErrors).forEach((msg) => {
                    toast.error(msg); // show each field error in a toast
                  });
                } else {
                  toast.error(result.message);

                  // Toast or global alert
                }
              } finally {
                setLoading(false);
              }
            }}
            className="w-full rounded-full py-2.5 text-black font-semibold active:scale-95"
            style={{
              background: "linear-gradient(180deg,#F8AF07 0%,#FFFC86 100%)",
            }}
          >
            {t("wallets.convert")}
          </button>
        </div>

        {/* CLOSE BUTTON – move closer to modal */}
        <button
          disabled={loading}
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
