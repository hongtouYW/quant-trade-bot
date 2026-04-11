"use client";

import { useContext, useEffect, useMemo, useRef, useState } from "react";
import Image from "next/image";
import { useRouter } from "next/navigation";
import { useTranslations } from "next-intl";
import { IMAGES } from "@/constants/images";
import { QRCodeCanvas } from "qrcode.react";
import {
  useCheckWithdrawStatusQuery,
  useWithdrawQrMutation,
} from "@/services/transactionApi";
import { skipToken } from "@reduxjs/toolkit/query";
import { toast } from "react-hot-toast"; // ✅ make sure you import this
import { useGetMemberViewQuery } from "@/services/authApi";
import { getMemberInfo, maskPhoneCompact } from "@/utils/utility";
import { refreshBalancesCore } from "@/utils/TransferUti";
import { useBalanceStore } from "@/store/zustand/balanceStore";
import { UIContext } from "@/contexts/UIProvider";
import {
  useLazyGetMemberViewQuery,
  useLazyGetPlayerViewQuery,
} from "@/services/authApi";
import { useMarqueeStore } from "@/store/zustand/marqueStore";

export default function QRCashWithdrawPage() {
  const t = useTranslations();
  const router = useRouter();
  const addItem = useMarqueeStore((s) => s.addItem);
  const [info, setInfo] = useState(null);
  const marqueeIdRef = useRef(crypto.randomUUID());

  const [getWithdrawQr] = useWithdrawQrMutation();
  const [data, setData] = useState(null);
  const [seconds, setSeconds] = useState(300);
  const [isRefreshing, setIsRefreshing] = useState(false);
  const { setIsGlobalLoading, isGlobalLoading } = useContext(UIContext);
  const {
    credits,
    points,
    setCredits,
    setPoints,
    isTransferring,
    setIsTransferring,
    markTransferDone,
  } = useBalanceStore();

  // member API
  const {
    data: user,
    isLoading,
    isFetching,
    refetch: refetchMember,
  } = useGetMemberViewQuery(info ? { member_id: info.member_id } : undefined, {
    skip: !info?.member_id,
  });

  // Lazy queries
  const [
    triggerGetMemberView,
    { isLoading: memberLoading, isFetching: memberFetching },
  ] = useLazyGetMemberViewQuery();
  const [
    triggerGetPlayerView,
    { isLoading: playerLoading, isFetching: playerFetching },
  ] = useLazyGetPlayerViewQuery();

  // read cookies on client
  useEffect(() => {
    const member = getMemberInfo();
    setInfo(member);
  }, []);

  // 🧩 Load QR data from sessionStorage
  useEffect(() => {
    const stored = sessionStorage.getItem("withdrawQrData");
    if (!stored) {
      router.back();
      return;
    }
    setData(JSON.parse(stored));
  }, [router]);

  // 🔁 Poll withdraw status every 5s
  const { data: statusRes } = useCheckWithdrawStatusQuery(
    data
      ? { user_id: data.credit.member_id, credit_id: data.credit.credit_id }
      : skipToken,
    { pollingInterval: 5000 },
  );

  const refreshBalance = async () => {
    if (!info?.member_id || isTransferring) return;

    try {
      setIsTransferring(true);
      setIsGlobalLoading(true);

      await refreshBalancesCore({
        info,
        gameMemberId: null, // ✅ Correct usage
        triggerGetMemberView,
        triggerGetPlayerView,
        setCredits,
        setPoints,
      });
    } catch (err) {
      console.error("Refresh error:", err);
    } finally {
      setIsGlobalLoading(false);
      markTransferDone();
      setIsTransferring(false);
    }
  };

  useEffect(() => {
    console.log(marqueeIdRef.current);
    if (!statusRes?.credit) return;
    const s = statusRes.credit.status;

    if (s == 1) {
      refreshBalance();
      addItem({
        id: marqueeIdRef.current,
        text: `${maskPhoneCompact(info?.phone)} ${"${withdraw}"} ${amountText}`,
        time: Date.now(),
      });

      router.back();
      setTimeout(() => {
        toast.success(t("withdraws.success"));
      }, 2000);
    }
  }, [addItem, info?.phone, refreshBalance, router, statusRes, t]);

  // 🕒 Countdown logic + auto refresh when reach 0
  useEffect(() => {
    if (seconds <= 0) {
      handleRefresh();
      return;
    }
    const id = setInterval(() => setSeconds((s) => s - 1), 1000);
    return () => clearInterval(id);
  }, [seconds]);

  // 🌀 Refresh QR from API
  const handleRefresh = async () => {
    if (!data?.credit?.member_id || !data?.credit?.amount) return;
    try {
      setIsRefreshing(true);
      const res = await getWithdrawQr({
        member_id: data.credit.member_id,
        amount: data.credit.amount,
      }).unwrap();
      if (res?.status) {
        sessionStorage.setItem("withdrawQrData", JSON.stringify(res));
        setData(res);
        setSeconds(300); // reset countdown
      }
    } catch (err) {
      console.error(err);
    } finally {
      setIsRefreshing(false);
    }
  };

  const secsText = useMemo(
    () => (seconds > 0 ? `${seconds}s` : t("withdraws.refreshing")),
    [seconds, t],
  );

  const amountText = data?.credit?.amount
    ? `RM${Number(data.credit.amount).toFixed(2)}`
    : "RM0.00";

  const qrUrl = data?.qr;

  if (!data) return null;

  return (
    <div className="mx-auto max-w-[480px] min-h-dvh bg-[#00143D] text-white px-4 pb-8">
      {/* ===== Header ===== */}
      <div className="relative flex items-center h-14">
        <button onClick={() => router.back()} className="z-10 cursor-pointer">
          <Image
            src={IMAGES.arrowLeft}
            alt="back"
            width={22}
            height={22}
            className="object-contain"
          />
        </button>
        <h1 className="absolute inset-x-0 text-lg font-semibold text-center">
          {t("withdraws.qrPay")}
        </h1>
      </div>

      {/* Subtitle */}
      <p className="mt-1 text-center text-sm text-white/70">
        {t("withdraws.qrSubtitle")}
      </p>

      {/* ===== Amount Pill ===== */}
      <div className="mt-3">
        <div className="rounded-2xl bg-[#0B1F52] p-1 ring-1 ring-[#738AE6]/50">
          <div className="rounded-xl px-6 py-8">
            <p className="text-center text-xl font-semibold text-[#FFC000]">
              {amountText}
            </p>
          </div>
        </div>
      </div>

      {/* Divider */}
      <div className="mt-6 h-px bg-white/12" />

      {/* ===== Info Text ===== */}
      <div className="mt-5 text-center">
        <p className="text-sm text-white/80">{t("withdraws.qrInstruction")}</p>
        <p className="mt-2 text-[13px] text-white/50 italic">
          {t("withdraws.autoRefreshLabel")}
        </p>
      </div>

      {/* ===== QR Card ===== */}
      <div className="relative mt-6">
        {/* Countdown chip */}
        <div className="absolute left-1/2 top-0 -translate-x-1/2 -translate-y-1/2 z-10">
          <div
            className="inline-flex items-center rounded-full px-4 py-1.5 text-sm font-medium text-[#00143D]"
            style={{
              background: "linear-gradient(180deg, #F8AF07 0%, #FFFC86 100%)",
            }}
          >
            {secsText}
          </div>
        </div>

        {/* White QR card */}
        <div className="rounded-[24px] bg-white p-4 m-5">
          <div className="flex flex-col items-center justify-center bg-white py-6 rounded-2xl">
            {isRefreshing ? (
              <div className="flex flex-col items-center justify-center py-24 text-black/60">
                <div className="h-8 w-8 animate-spin rounded-full border-2 border-black/20 border-t-[#FFC000] mb-3" />
                <p className="text-sm">{t("withdraws.refreshing")}</p>
              </div>
            ) : qrUrl ? (
              <QRCodeCanvas
                value={qrUrl}
                size={300} // ✅ slightly smaller improves scan
                bgColor="#FFFFFF"
                fgColor="#000000"
                level="M"
                marginSize={4} // ✅ new prop replaces includeMargin
              />
            ) : (
              <div className="flex flex-col items-center justify-center py-24 text-black/60">
                <div className="h-8 w-8 animate-spin rounded-full border-2 border-black/20 border-t-[#FFC000] mb-3" />
                <p className="text-sm">{t("common.loading")}</p>
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
