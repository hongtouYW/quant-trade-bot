"use client";

import {
  forwardRef,
  useImperativeHandle,
  useContext,
  useEffect,
  useState,
} from "react";
import Image from "next/image";
import Link from "next/link";
import { IMAGES } from "@/constants/images";
import { useTranslations } from "next-intl";
import {
  formatPhonePretty,
  getClientIp,
  getMemberInfo,
  getUserLevel,
} from "@/utils/utility";
import { UIContext } from "@/contexts/UIProvider";
import { useBalanceStore } from "@/store/zustand/balanceStore";
import { useProviderStore } from "@/store/zustand/providerStore";
import { useLazyGetMemberViewQuery } from "@/services/authApi";
import { useTransferAllPointToCreditMutation } from "@/services/transactionApi";
import { LOCAL_STORAGE_NAMES } from "@/constants/cookies";
import { handleTransferAllPointsToCredit } from "@/utils/TransferUti";
import MarqueeTitle from "@/components/shared/MarqueeTitle";
import { useWithdrawFormStore } from "@/store/zustand/withdrawStore";
import {
  appendPage,
  resetList,
  nextPage,
} from "@/store/slice/transactionListSlice";
import { useDispatch } from "react-redux";
import {
  useTransferListQuery,
  useTransferOutMutation,
} from "@/services/transactionApi";
import { toast } from "react-hot-toast";

const HomeProfilePanel = forwardRef(function HomeProfilePanel(props, ref) {
  const {
    username = "",
    vipLevel = 1,
    type = "Home",
    routes = {
      topup: "/topup",
      transactions: "/transactions",
      withdraw: "/withdraw",
      wallet: "/transfer",
      vip: "/vip",
      discount: "/discount",
      bank: "/bank",
      promote: "/promote",
    },
  } = props;

  const t = useTranslations();
  const { isGlobalLoading, setIsGlobalLoading } = useContext(UIContext);

  const [info, setInfo] = useState(null);

  const {
    credits,
    setCredits,
    setPoints,
    isTransferring,
    lastTransferDoneAt,
    setIsTransferring,
  } = useBalanceStore();

  // const { selectedProvider, clearPrevGameMemberId } = useProviderStore();
  const { prevGameMemberId } = useProviderStore();
  const dispatch = useDispatch();
  const [triggerGetMemberView, { isLoading, isFetching }] =
    useLazyGetMemberViewQuery();
  const [transferAllPointToCredit] = useTransferAllPointToCreditMutation();
  const [level, setLevel] = useState(0);
  const [transferOut] = useTransferOutMutation();

  const {
    selectedProvider,
    reset: resetProvider,
    setSelectedProvider,
    clearPrevGameMemberId,
    setPrevProviderId,
    prevProviderId,
    clearPrevProviderId,
    setPrevGameMemberId,
  } = useProviderStore.getState();

  useEffect(() => {
    const member = getMemberInfo();
    setInfo(member);
  }, []);

  useEffect(() => {
    setLevel(getUserLevel()); // read latest value
  }, []);

  const handleRefresh = async () => {
    if (!info?.member_id || isLoading || isFetching) return;

    if (isTransferring) return;

    try {
      setIsGlobalLoading(true);
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

      setPoints(0);

      const result = await triggerGetMemberView({
        member_id: info.member_id,
      }).unwrap();
      if (result?.data?.vip) {
        setLevel(result?.data?.vip);
        localStorage.setItem(LOCAL_STORAGE_NAMES.LEVEL, result?.data?.vip);
      }

      // setIsGlobalLoading(true);

      // if (prevGameMemberId) {
      //   await handleTransferAllPointsToCredit({
      //     info,
      //     gameMemberId: prevGameMemberId,
      //     transferAllPointToCredit,
      //     setIsGlobalLoading,
      //   });
      // } else {
      //   const result = await triggerGetMemberView({
      //     member_id: info.member_id,
      //   }).unwrap();
      //   if (result?.data?.vip) {
      //     setLevel(result?.data?.vip);
      //     localStorage.setItem(LOCAL_STORAGE_NAMES.LEVEL, result?.data?.vip);
      //   }

      //   if (!isTransferring && result?.data?.balance != null) {
      //     setCredits(Number(result.data.balance));
      //   }
      // }
    } catch (err) {
      console.error("Failed to refresh balance:", err);
    } finally {
      setIsGlobalLoading(false);
    }
  };

  useImperativeHandle(ref, () => ({
    refreshBalance: () => handleRefresh(),
  }));

  const HAS_FETCHED_KEY = "wallet_member_fetched";

  useEffect(() => {
    if (!info?.member_id) return;

    const hasFetched = sessionStorage.getItem(HAS_FETCHED_KEY) === "1";
    if (hasFetched) return;

    (async () => {
      const justLogin =
        localStorage.getItem(LOCAL_STORAGE_NAMES.JUST_LOGIN) === "1";
      const prevGame = localStorage.getItem("prevGameMemberId");

      let needMemberView = true;

      // -----------------------------------
      // CASE 1: Auto-transfer after login
      // -----------------------------------
      if (justLogin && prevGame) {
        if (isTransferring || isLoading) return;

        setIsGlobalLoading(true);
        setIsTransferring(true);

        try {
          // 1️⃣ Perform the transfer first
          await handleTransferAllPointsToCredit({
            info,
            gameMemberId: prevGame,
            transferAllPointToCredit,
            setIsGlobalLoading,
          });

          // 2️⃣ Transfer done → must refresh member view AFTER
          needMemberView = true;

          clearPrevGameMemberId();
        } catch (err) {
          needMemberView = true; // still try to refresh view
        } finally {
          setIsTransferring(false);
          setIsGlobalLoading(false);
          localStorage.removeItem(LOCAL_STORAGE_NAMES.JUST_LOGIN);
        }
      }

      // -----------------------------------
      // CASE 2: Just login but no prev game
      // -----------------------------------
      if (justLogin && !prevGame) {
        needMemberView = true;
        localStorage.removeItem(LOCAL_STORAGE_NAMES.JUST_LOGIN);
      }

      // -----------------------------------
      // Run MemberView only when needed
      // -----------------------------------

      if (!needMemberView) return;

      try {
        setIsGlobalLoading(true);

        const res = await triggerGetMemberView({
          member_id: info.member_id,
        }).unwrap();

        if (res?.data?.vip) {
          setLevel(res?.data?.vip);
          localStorage.setItem(LOCAL_STORAGE_NAMES.LEVEL, res?.data?.vip);
        }

        if (res?.data?.balance != null) {
          setCredits(Number(res.data.balance));
        }
      } catch (err) {
        // silently ignore
      } finally {
        setIsGlobalLoading(false);
      }

      sessionStorage.setItem(HAS_FETCHED_KEY, "1");
    })();
  }, [info?.member_id, isTransferring, isLoading]);

  return (
    <section className="px-3 py-0">
      <div className="flex items-center justify-between gap-2 ">
        {/* <div className="relative w-[210px] h-[30px]">
          <Image
            src={IMAGES.home.profile.bluebar}
            alt="bluebar"
            fill
            className="object-fill mb-1"
            sizes="180px"
            priority
          />
          <div className="absolute inset-0 flex items-center gap-1 pl-3 pr-2 text-xs">
            <span className="text-white/80 flex items-center gap-1">
              {info?.member_name?.trim() && (
                <MarqueeTitle
                  fontSize={15}
                  title={formatPhonePretty(info.member_name)}
                  selected
                  maxWidth={120}
                />
              )}
            </span>
            <span className="ml-1 inline-flex items-center gap-1">
              <Image
                src={IMAGES.vip[`vip${level ?? 0}`]}
                alt={`vip${level}`}
                width={54}
                height={22}
                className="block w-[54px] h-[20px]"
              />
            </span>
          </div>
        </div> */}

        {/* <div className="flex items-center gap-2">
          <Image
            src={IMAGES.home.profile.frameWallet}
            alt="wallet"
            width={24}
            height={24}
          />
          <div className="text-[18px] font-bold tracking-wide text-black">
            {credits.toFixed(2)}
          </div>
          <button
            onClick={handleRefresh}
            aria-label="refresh balance"
            className={`relative h-6 w-6 active:scale-95 ${
              isGlobalLoading ? "animate-[spin_0.5s_linear_infinite]" : ""
            }`}
          >
            <Image
              src={IMAGES.home.profile.frameRefresh}
              alt="refresh"
              fill
              sizes="480px"
              className="object-contain"
            />
          </button>
        </div> */}

        <div className="relative mt-4 flex items-center">
          {/* Points */}
          <div className="w-full pr-10">
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
                {credits.toFixed(2)}
              </span>
            </div>
          </div>

          {/* Refresh */}
          <button
            className={`absolute right-0 top-1/2 h-6 w-6 -translate-y-1/2 active:scale-95 ${
              isGlobalLoading || isTransferring
                ? "animate-[spin_0.5s_linear_infinite]"
                : ""
            }`}
            onClick={handleRefresh}
            disabled={isGlobalLoading}
          >
            <Image
              src={IMAGES.withdraw.iconRefresh}
              alt="refresh"
              width={24}
              height={24}
              className="h-6 w-6"
            />
          </button>
        </div>
      </div>

      <div className="rounded-xl p-[2px] bg-[linear-gradient(90deg,#F8AF07_1.47%,#FFFC86_98.68%)]">
        <div className="rounded-[12px] bg-[#1B2D72]">
          <div
            className="
            w-full
            h-[2px]
            bg-[linear-gradient(90deg,#F8AF07_1.47%,#FFFC86_98.68%)]

          "
          />
          {/* GRID FEATURES */}
          <div className="relative rounded-xl p-3">
            <Image
              src={IMAGES.home.profile.frame2}
              alt=""
              fill
              className="pointer-events-none -z-10 rounded-xl object-cover"
              priority
            />

            <div className="grid grid-cols-4 gap-y-5 py-2 text-center text-white">
              {type === "Home" ? (
                <>
                  {/* ⭐ TOPUP (Badge removed) */}
                  <Feature
                    href={routes.topup}
                    img={IMAGES.home.profile.topup2}
                    label={t("topup")}
                  />

                  <Feature
                    href={routes.transactions}
                    onClick={() => {
                      dispatch(resetList("credit"));
                      dispatch(resetList("point"));
                      dispatch(resetList("history"));

                      sessionStorage.removeItem("txActiveTab");
                      sessionStorage.setItem("txForceRefresh", "1");
                    }}
                    img={IMAGES.home.profile.transaction2}
                    label={t("transactions")}
                  />
                  <Feature
                    onClick={() => {
                      useWithdrawFormStore.getState().clearShouldReloadBanks();
                      useWithdrawFormStore.getState().resetAmount(); // 🔥 reset all form data first
                    }}
                    href={routes.withdraw}
                    img={IMAGES.home.profile.withdrawal2}
                    label={t("withdraw")}
                  />
                  <Feature
                    href={routes.wallet}
                    img={IMAGES.home.profile.wallet2}
                    label={t("wallet")}
                  />
                </>
              ) : null}

              {type === "Setting" ? (
                <>
                  <Feature
                    href={routes.topup}
                    img={IMAGES.home.profile.topup2}
                    label={t("topup")}
                  />

                  <Feature
                    href={routes.transactions}
                    onClick={() => {
                      sessionStorage.removeItem("txActiveTab");
                    }}
                    img={IMAGES.home.profile.transaction2}
                    label={t("transactions")}
                  />

                  <Feature
                    onClick={() => {
                      useWithdrawFormStore.getState().clearShouldReloadBanks();
                      useWithdrawFormStore.getState().resetAmount(); // 🔥 reset all form data first
                    }}
                    href={routes.withdraw}
                    img={IMAGES.home.profile.withdrawal2}
                    label={t("withdraw")}
                  />

                  <Feature
                    href={routes.wallet}
                    img={IMAGES.home.profile.wallet2}
                    label={t("wallet")}
                  />

                  <Feature
                    href={routes.vip}
                    img={IMAGES.home.profile.vip2}
                    label={t("vipPrivileges")}
                  />
                  <Feature
                    href={routes.discount}
                    img={IMAGES.home.profile.event2}
                    label={t("discount")}
                  />
                  <Feature
                    href={routes.bank}
                    img={IMAGES.home.profile.bank2}
                    label={t("bank")}
                  />
                  <Feature
                    href={routes.promote}
                    img={IMAGES.home.profile.promo2}
                    label={t("promote")}
                  />
                </>
              ) : null}
            </div>
          </div>
        </div>
      </div>
    </section>
  );
});

function Feature({ href, img, label, badge, onClick }) {
  return (
    <Link href={href} onClick={onClick} className="group inline-block">
      <div className="mx-auto h-15 w-15">
        <div className="relative h-full w-full">
          <Image src={img} alt={label} fill className="object-contain" />
          {badge ? (
            <span className="absolute -right-5 -bottom-0 rounded-full bg-[#FF3B3B] px-1.5 py-[1px] text-[10px] font-semibold text-white shadow">
              {badge}
            </span>
          ) : null}
        </div>
      </div>
      <div className="mt-2 text-[12px]  text-centerleading-4 text-white/90 group-active:opacity-80">
        {label}
      </div>
    </Link>
  );
}

export default HomeProfilePanel;
