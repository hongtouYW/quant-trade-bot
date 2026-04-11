"use client";

import {
  forwardRef,
  useImperativeHandle,
  useState,
  useEffect,
  useContext,
} from "react";
import Image from "next/image";
import { useTranslations } from "next-intl";
import { toast } from "react-hot-toast";
import { IMAGES } from "@/constants/images";
import { UIContext } from "@/contexts/UIProvider";
import { getMemberInfo } from "@/utils/utility";
import { useBalanceStore } from "@/store/zustand/balanceStore";
import {
  useLazyGetMemberViewQuery,
  useLazyGetPlayerViewQuery,
} from "@/services/authApi";

const TransferBalanceBar = forwardRef(
  ({ gameMemberId, point: parentPoint = 0, onRefetchTotalPoints }, ref) => {
    const t = useTranslations();
    const { setLoading } = useContext(UIContext);
    const { setIsGlobalLoading, isGlobalLoading } = useContext(UIContext);
    const [info, setInfo] = useState(null);
    const [localPoints, setLocalPoints] = useState(parentPoint); // used when NO gameMemberId

    // Zustand (credit always global; points only used when gameMemberId exists)
    const {
      credits,
      points,
      setCredits,
      setPoints,
      isTransferring,
      setIsTransferring,
      markTransferDone,
    } = useBalanceStore();

    // Lazy queries
    const [
      triggerGetMemberView,
      { isLoading: memberLoading, isFetching: memberFetching },
    ] = useLazyGetMemberViewQuery();
    const [
      triggerGetPlayerView,
      { isLoading: playerLoading, isFetching: playerFetching },
    ] = useLazyGetPlayerViewQuery();

    // read member from cookies
    useEffect(() => {
      const member = getMemberInfo();
      setInfo(member);
    }, []);

    // keep local points in sync with parent when NO gameMemberId
    useEffect(() => {
      if (!gameMemberId) setLocalPoints(parentPoint || 0);
    }, [parentPoint, gameMemberId]);

    // first load: fetch credit (+ points if gameMemberId provided)
    useEffect(() => {
      if (!info?.member_id) return;
      handleRefresh(); // auto refresh once when IDs ready
      // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [info?.member_id, gameMemberId]);

    const handleRefresh = async () => {
      if (!info?.member_id || isTransferring) return;

      try {
        setIsTransferring(true); // 🔒 prevent parallel updates
        // setLoading(true);
        setIsGlobalLoading(true);

        if (gameMemberId) {
          // 🎮 In-game refresh (both)
          const [memberRes, playerRes] = await Promise.all([
            triggerGetMemberView({ member_id: info.member_id }).unwrap(),
            triggerGetPlayerView({
              member_id: info.member_id,
              gamemember_id: gameMemberId,
            }).unwrap(),
          ]);

          if (memberRes?.data?.balance != null)
            setCredits(Number(memberRes.data.balance));
          if (playerRes?.data?.balance != null)
            setPoints(Number(playerRes.data.balance));
        } else {
          // 💰 Wallet-only refresh
          const res = await triggerGetMemberView({
            member_id: info.member_id,
          }).unwrap();

          if (res?.data?.balance != null) setCredits(Number(res.data.balance));

          if (onRefetchTotalPoints) await onRefetchTotalPoints();
        }
      } catch (err) {
        console.error("Balance refresh failed:", err);
      } finally {
        setIsGlobalLoading(false);
        markTransferDone(); // ✅ unify transfer completion tracking
        setIsTransferring(false); // 🔓 release
        // setLoading(false);
      }
    };

    useImperativeHandle(ref, () => ({ handleRefresh }));

    // which points to display?
    const displayPoints = gameMemberId ? points : localPoints;

    return (
      <div className="mt-4 flex items-center gap-3 ">
        {/* 💰 Credit */}
        <div className="w-[45%]">
          <p className="mb-1 text-[13px] text-white/70">
            {t("wallets.credit")}
          </p>
          <div className="flex items-center gap-2 rounded-full bg-[#4F6BDE]">
            <div
              className="flex h-8 w-8 items-center justify-center rounded-full"
              style={{
                background: "linear-gradient(180deg,#F8AF07 0%,#FFFC86 100%)",
              }}
            >
              <Image
                src={IMAGES.withdraw.buttonmoney}
                alt="credit"
                width={20}
                height={20}
              />
            </div>
            <span className="mx-5 flex items-center justify-center px-3 py-0.5 leading-none">
              {credits.toFixed(2)}
            </span>
          </div>
        </div>

        {/* ⭐ Points */}
        <div className="w-[45%]">
          <p className="mb-1 text-[13px] text-white/70">
            {t("wallets.points")}
          </p>
          <div className="flex items-center gap-2 rounded-full bg-[#4F6BDE]">
            <div
              className="flex h-8 w-8 items-center justify-center rounded-full"
              style={{
                background: "linear-gradient(180deg,#F8AF07 0%,#FFFC86 100%)",
              }}
            >
              <Image
                src={IMAGES.withdraw.buttonstar}
                alt="points"
                width={20}
                height={20}
              />
            </div>
            <span className="mx-5 flex items-center justify-center px-3 py-0.5 leading-none">
              {displayPoints.toFixed(2)}
            </span>
          </div>
        </div>

        {/* 🔄 Refresh */}
        <div className="w-[10%] mt-6 flex items-end justify-center">
          <button
            onClick={handleRefresh}
            className={`flex h-9 w-9 items-center justify-center ${
              memberLoading || playerLoading || isGlobalLoading
                ? "animate-spin"
                : ""
            }`}
            disabled={
              !info?.member_id ||
              isTransferring ||
              memberLoading ||
              memberFetching ||
              playerLoading ||
              playerFetching
            }
          >
            <Image
              src={IMAGES.withdraw.iconRefresh}
              alt="refresh"
              width={30}
              height={30}
            />
          </button>
        </div>
      </div>
    );
  }
);

TransferBalanceBar.displayName = "TransferBalanceBar";
export default TransferBalanceBar;
