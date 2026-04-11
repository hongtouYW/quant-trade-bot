"use client";

import Image from "next/image";
import { useParams, useRouter } from "next/navigation";
import { useTranslations } from "next-intl";
import { useContext, useEffect, useMemo, useRef, useState } from "react";
import { IMAGES } from "@/constants/images";
import SubmitButton from "@/components/shared/SubmitButton";
import { extractError, getClientIp, getMemberInfo } from "@/utils/utility";
import {
  useTransferListQuery,
  useTransferOutMutation,
} from "@/services/transactionApi";
import { UIContext } from "@/contexts/UIProvider";
import { toast } from "react-hot-toast";
import TransferBalanceBar from "@/components/shared/TransferBalanceBar";
import { useBalanceStore } from "@/store/zustand/balanceStore";
import { useProviderStore } from "@/store/zustand/providerStore";

export default function TransferPage() {
  const t = useTranslations();
  const router = useRouter();
  const [mounted, setMounted] = useState(false);
  const [info, setInfo] = useState(null);
  const [openId, setOpenId] = useState(null);
  const balanceRef = useRef(null);
  const { parts } = useParams();
  const gmemberId = Array.isArray(parts) ? parts[0] : null;
  const { setLoading } = useContext(UIContext);
  const [transferOut] = useTransferOutMutation();

  const {
    setCredits,
    setPoints,
    isTransferring,
    setIsTransferring,
    markTransferDone,
  } = useBalanceStore();

  const {
    reset: resetProvider,
    setSelectedProvider,
    clearPrevGameMemberId,
    setPrevProviderId,
    prevProviderId,
    clearPrevProviderId,
    setPrevGameMemberId,
  } = useProviderStore.getState();

  // ⭐ NEW — controls when listing resets to zero
  const [resetMode, setResetMode] = useState(false);

  useEffect(() => setMounted(true), []);
  useEffect(() => {
    if (!mounted) return;
    const member = getMemberInfo();
    setInfo(member || null);
  }, [mounted]);

  // ================= FETCH LIST =================
  const { data, isLoading, isError, refetch, error, isFetching } =
    useTransferListQuery(info ? { member_id: info.member_id } : {}, {
      skip: !info?.member_id,
      refetchOnMountOrArgChange: true,
    });

  // ⭐ When data refreshes → turn off resetMode
  useEffect(() => {
    setResetMode(false);
  }, [data]);

  useEffect(() => {
    if (isError) {
      toast.error(error?.data?.message || t("common.loadFailed"));
    }
  }, [isError, error, t]);

  const [totalPoint, setTotalPoint] = useState(0);

  useEffect(() => {
    setTotalPoint(0);
    if (gmemberId) return;
    if (!data?.data?.length) return;

    const sum = data.data.reduce((sum, item) => {
      const balance = parseFloat(item?.player?.balance || 0);
      return sum + balance;
    }, 0);

    setTotalPoint(sum);
  }, [data, gmemberId]);

  // ================= BUILD PROVIDERS =================
  const providers = useMemo(() => {
    if (!data?.data?.length) return [];

    return data.data.map((item) => {
      const hasPlayer =
        item.player && !Array.isArray(item.player) && item.player.gamemember_id;

      const currentValue = hasPlayer ? parseFloat(item.player.balance || 0) : 0;

      return {
        id: item.provider_id,
        name: item.provider_name || "Unknown",
        hasPlayer,

        // ⭐ RESET to zero when resetMode = true
        current: resetMode ? 0 : currentValue,
        transfer: resetMode ? 0 : currentValue,
      };
    });
  }, [data, resetMode]);

  // ================= SUBMIT =================
  const handleSubmit = async () => {
    if (!info?.member_id) return;
    if (isTransferring || isLoading) return;

    setLoading(true);
    setIsTransferring(true);

    try {
      const ip = await getClientIp();

      const resp = await transferOut({
        member_id: info.member_id,
        ip,
      }).unwrap();

      if (!resp?.status) {
        toast.error(resp?.message || t("transfer.failed"));
        return;
      }

      if (resp?.member?.balance != null) {
        clearPrevProviderId();
        clearPrevGameMemberId();
        setCredits(Number(parseFloat(resp.member.balance)));
      }

      if (gmemberId) {
        setPoints(0);
      } else {
        setTotalPoint(0);
      }

      // ⭐ RESET LISTING TO ZERO HERE
      setResetMode(true);

      toast.success(resp?.message || t("transfer.success"));
    } catch (err) {
      const result = extractError(err);
      toast.error(result.message || t("transfer.failed"));
    } finally {
      setIsTransferring(false);
      markTransferDone?.();
      setLoading(false);
    }
  };

  // ================= UI =================
  return (
    <div className="mx-auto max-w-[480px] min-h-dvh bg-[#00143D] text-white pb-8">
      {/* Header */}
      <div className="relative flex items-center h-14 px-4">
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
          {t("transfer.title")}
        </h1>
        <button
          onClick={() => handleSubmit()}
          className="absolute right-4 rounded-[12px] px-3 py-2 text-sm font-semibold text-[#00143D]"
          style={{
            background: "linear-gradient(180deg, #F8AF07 0%, #FFFC86 100%)",
          }}
        >
          {t("transfer.oneClick")}
        </button>
      </div>

      {/* Wallet balances */}
      <div className="relative px-4">
        <TransferBalanceBar
          ref={balanceRef}
          point={!mounted || !gmemberId ? !isFetching && totalPoint : 0}
          gameMemberId={mounted ? gmemberId : ""}
          onRefetchTotalPoints={refetch}
        />
      </div>

      {/* Table header */}
      <div className="mt-4 grid grid-cols-[1fr_120px_1fr] items-center px-3 py-3 text-sm font-semibold text-white/80 bg-white/5">
        <span>{t("transfer.table.game")}</span>
        <span className="text-center">{t("transfer.table.currentPoints")}</span>
        <div className="flex items-center justify-end gap-2 min-w-[60px]">
          <span>{t("transfer.table.toTransfer")}</span>
          <div className="w-5 h-5" />
        </div>
      </div>

      {/* Body */}
      {isLoading ? (
        <div className="py-12 text-center text-sm text-red-400"></div>
      ) : isError ? (
        <div className="py-12 text-center text-sm text-red-400">
          {t("common.loadFailed")}
        </div>
      ) : (
        <div className="overflow-hidden rounded-b-xl mb-30">
          {providers.map((p, idx) => {
            const zebra = idx % 2 === 0 ? "bg-[#0B1D48]" : "bg-[#0A1E4A]";
            const open = openId === p.id;

            return (
              <div key={p.id} className={`${zebra} border-t border-white/5`}>
                {/* Main row */}
                <div className="grid grid-cols-[1fr_120px_1fr] items-center px-3 py-4">
                  <p className="truncate text-sm">{p.name}</p>
                  <div className="text-center text-sm">
                    {p.current.toFixed(2)}
                  </div>
                  <div className="flex items-center justify-end gap-2 min-w-[60px]">
                    <span className="text-sm">{p.transfer.toFixed(2)}</span>
                    {p.hasPlayer ? (
                      <button className="grid place-items-center w-5 h-5"></button>
                    ) : (
                      <div className="w-5 h-5" />
                    )}
                  </div>
                </div>

                {open && p.hasPlayer && (
                  <div className="grid grid-cols-[1fr_120px_1fr] itemscenter px-3 py-3 bg-[#10285F] text-[13px] text-white/80">
                    <p>{p.name}</p>
                    <div className="text-center">{p.current.toFixed(2)}</div>
                    <div className="flex items-center justify-end gap-2 min-w-[60px]">
                      <span className="text-sm">{p.transfer.toFixed(2)}</span>
                      <div className="w-5 h-5" />
                    </div>
                  </div>
                )}
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}
