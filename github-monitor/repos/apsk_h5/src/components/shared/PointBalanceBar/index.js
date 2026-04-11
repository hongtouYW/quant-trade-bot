"use client";

import {
  forwardRef,
  useImperativeHandle,
  useState,
  useEffect,
  useContext,
  useRef,
} from "react";
import Image from "next/image";
import { useTranslations } from "next-intl";

import { IMAGES } from "@/constants/images";
import { UIContext } from "@/contexts/UIProvider";
import { extractError, getClientIp, getMemberInfo } from "@/utils/utility";
import { useBalanceStore } from "@/store/zustand/balanceStore";
import toast from "react-hot-toast";

import {
  useGetPlayerViewQuery,
  useLazyGetPlayerViewQuery,
  useLazyGetMemberViewQuery,
} from "@/services/authApi";
import {
  useFromPointToCreditMutation,
  useTransferOutMutation,
} from "@/services/transactionApi";
import { handleTransferAllPointsToCredit } from "@/utils/TransferUti";
import {
  useTransferAllCreditToPointMutation,
  useTransferAllPointToCreditMutation,
} from "@/services/transactionApi";
import { useProviderStore } from "@/store/zustand/providerStore";
const PointBalanceBar = forwardRef(
  ({ gameMemberId, isPreload = true, autoTransfer = false }, ref) => {
    const t = useTranslations();
    const [info, setInfo] = useState(null);
    const [loading, setLoading] = useState(false);
    // const { setLoading } = useContext(UIContext);
    const [fromPointToCredit] = useFromPointToCreditMutation();
    const [transferAllPointToCredit] = useTransferAllPointToCreditMutation();
    const {
      reset: resetProvider,
      setSelectedProvider,
      clearPrevGameMemberId,
      clearPrevProviderId,
      setPrevGameMemberId,
    } = useProviderStore.getState();

    const [
      triggerGetPlayerView,
      { data: player, isLoading: playerLoading, isFetching: playerFetching },
    ] = useLazyGetPlayerViewQuery();
    const { setIsGlobalLoading, isGlobalLoading } = useContext(UIContext);
    // const [triggerGetMemberView] = useLazyGetMemberViewQuery();
    const [uiPoints, setUiPoints] = useState(0);
    const points = useBalanceStore((s) => s.points);
    const credits = useBalanceStore((s) => s.credits);
    const setPoints = useBalanceStore((s) => s.setPoints);
    const setCredits = useBalanceStore((s) => s.setCredits);
    const isTransferring = useBalanceStore((s) => s.isTransferring);
    const setIsTransferring = useBalanceStore((s) => s.setIsTransferring);
    const markTransferDone = useBalanceStore((s) => s.markTransferDone);
    const hasRun = useRef(false);
    const [transferAllCreditToPoint] = useTransferAllCreditToPointMutation();
    const [transferOut] = useTransferOutMutation();
    // 🧩 read cookies on client
    useEffect(() => {
      const member = getMemberInfo();
      setInfo(member);
    }, []);

    // 🟡 AUTO-TRANSFER (only if autoTransfer=true)
    useEffect(() => {
      if (!autoTransfer) return; // only run when enabled
      if (hasRun.current) return; // prevent double run
      if (!info?.member_id || !gameMemberId) return;

      // const doTransfer = async () => {
      //   try {
      //     setIsTransferring(true);

      //     const resp = await transferAllCreditToPoint({
      //       member_id: info.member_id,
      //       gamemember_id: gameMemberId,
      //       ip: await getClientIp(),
      //     }).unwrap();

      //     if (resp.status) {
      //       // setCredits(Number(parseFloat(resp.member.balance)));
      //       setPoints(Number(parseFloat(resp.player.balance)));
      //     }
      //   } catch (err) {
      //     // silent same as your logic
      //   } finally {
      //     setIsTransferring(false);
      //     markTransferDone?.();
      //   }
      // };

      // doTransfer();
      hasRun.current = true;
    }, [autoTransfer, info?.member_id, gameMemberId]);

    // 🧩 auto fetch current player balance
    // const {
    //   data: player,
    //   isLoading: playerLoading,
    //   isFetching: playerFetching,
    //   refetch: refetchPlayer,
    // } = useGetPlayerViewQuery(
    //   gameMemberId && info?.member_id
    //     ? { gamemember_id: gameMemberId, member_id: info.member_id }
    //     : undefined,
    //   { skip: !gameMemberId || !info?.member_id }
    // );

    // 🧩 update Zustand whenever player data changes

    // 🟡 Auto-fetch player (blocked during transfer)
    useEffect(() => {
      if (autoTransfer) return; // ❌ skip when autoTransfer
      if (!isPreload || isTransferring) return;

      if (info?.member_id && gameMemberId) {
        triggerGetPlayerView({
          gamemember_id: gameMemberId,
          member_id: info.member_id,
        });
      }
    }, [
      autoTransfer,
      info?.member_id,
      isPreload,
      gameMemberId,
      isTransferring,
      triggerGetPlayerView,
    ]);
    // 🟢 Update Zustand (blocked during transfer)
    useEffect(() => {
      if (autoTransfer) return; // ❌ skip when autoTransfer
      if (!isPreload) return;

      if (player?.data && gameMemberId) {
        setPoints(Number(player.data.balance || 0));
        //  setCredits(Number(player.data.member.balance || 0));
      }
    }, [
      autoTransfer,
      player,
      isTransferring,
      isPreload,
      gameMemberId,
      setPoints,
      setCredits,
    ]);

    useEffect(() => {
      setUiPoints(points);
    }, [points]);

    // 🧩 manual refresh
    const handleRefresh = async () => {
      if (!info?.member_id || !gameMemberId || isTransferring) return;

      try {
        setIsTransferring(true);
        setLoading(true);

        const resp = await triggerGetPlayerView({
          gamemember_id: gameMemberId,
          member_id: info.member_id,
        }).unwrap();

        // 🚀 IMMEDIATE update — no need to wait for effect
        if (resp?.data) {
          setPoints(Number(resp.data.balance));
          // setCredits(Number(resp.data.member.balance));
        }

        // optional pause to let UI settle
        await new Promise((r) => setTimeout(r, 300));

        // if (credits == 0) {
        //   const resp = await transferAllCreditToPoint({
        //     member_id: info.member_id,
        //     gamemember_id: gameMemberId,
        //     ip: await getClientIp(),
        //   }).unwrap();
        //   clearPrevGameMemberId();
        //   if (resp.status) {
        //     //   setCredits(Number(parseFloat(resp.member.balance)));
        //     setPoints(Number(parseFloat(resp.player.balance)));
        //   }
        // } else {
        //   const resp = await triggerGetPlayerView({
        //     gamemember_id: gameMemberId,
        //     member_id: info.member_id,
        //   }).unwrap();

        //   // 🚀 IMMEDIATE update — no need to wait for effect
        //   if (resp?.data) {
        //     setPoints(Number(resp.data.balance));
        //     // setCredits(Number(resp.data.member.balance));
        //   }

        //   // optional pause to let UI settle
        //   await new Promise((r) => setTimeout(r, 300));
        // }

        // optional parent refetch
        // if (onRefetchTotalPoints) await onRefetchTotalPoints();
      } catch (err) {
        const extracted = extractError(err); // 👈 use your extract function
        toast.error(extracted.message, {
          duration: 8000,
        });
      } finally {
        markTransferDone();
        setIsTransferring(false);
        setLoading(false);
      }
    };

    // 🧩 transfer all points → credit
    const handleTransferAllPoints = async () => {
      try {
        setIsTransferring(true);
        setLoading(true);

        const ip = await getClientIp();
        const resp = await transferOut({
          member_id: info.member_id,
          ip,
        }).unwrap();
        if (resp?.status) {
          setPoints(0);
          const memberBalance = resp?.member?.balance;
          const parsedMemberBalance =
            memberBalance == null
              ? Number.NaN
              : Number.parseFloat(memberBalance);

          if (!Number.isNaN(parsedMemberBalance)) {
            setCredits(parsedMemberBalance);
          }

          clearPrevGameMemberId();
          clearPrevProviderId();
        }
      } catch (err) {
        // const extracted = extractError(err); // 👈 use your extract function
        // toast.error(extracted.message, {
        //   duration: 8000,
        // });
      } finally {
        markTransferDone();
        setIsTransferring(false);
        setLoading(false);
      }

      // const success = await handleTransferAllPointsToCredit({
      //   info,
      //   gameMemberId,
      //   triggerGetPlayerView,
      //   transferAllPointToCredit,
      //   setIsGlobalLoading,
      // });
      // // ⭐ Only clear if transfer OUT actually succeeded
      // if (success) {
      //   clearPrevGameMemberId();
      //   clearPrevProviderId();
      // }
    };
    // expose methods to parent
    useImperativeHandle(ref, () => ({
      handleRefresh,
      handleTransferAllPoints,
    }));

    return (
      <div className="mt-4 flex items-center gap-3">
        {/* Points */}
        {gameMemberId && (
          <div className="w-[90%]">
            {/* <p className="mb-1 text-[13px] text-white/70">
            {t("wallets.points")}
          </p> */}
            <div className="flex items-center gap-2 rounded-full bg-[#4F6BDE]">
              <div
                className="flex h-8 w-8 items-center justify-center rounded-full"
                style={{
                  background:
                    "linear-gradient(180deg, #F8AF07 0%, #FFFC86 100%)",
                }}
              >
                <Image
                  src={IMAGES.withdraw.buttonstar}
                  alt="points"
                  width={20}
                  height={20}
                  className="h-5 w-auto"
                />
              </div>
              <span className="mx-5 flex items-center justify-center px-3 py-0.5 leading-none">
                {uiPoints.toFixed(2)}
              </span>
            </div>
          </div>
        )}

        {/* Refresh */}
        <div className="w-[14%]  flex items-end justify-center ">
          <button
            className={`relative h-6 w-6 active:scale-95 ${
              isGlobalLoading || isTransferring
                ? "animate-[spin_0.5s_linear_infinite]"
                : ""
            }`}
            onClick={handleRefresh}
            disabled={loading}
          >
            <Image
              src={IMAGES.withdraw.iconRefresh}
              alt="refresh"
              width={50}
              height={50}
              className={`transition-transform ${
                loading ? "animate-[spin_0.5s_linear_infinite]" : ""
              }`}
            />
          </button>
        </div>
      </div>
    );
  },
);

PointBalanceBar.displayName = "PointBalanceBar";
export default PointBalanceBar;
