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

  return (
    <section className="px-3 mt-1">
      <div className="rounded-xl p-[2px] bg-[linear-gradient(90deg,#F8AF07_1.47%,#FFFC86_98.68%)]">
        <div
          className={`relative rounded-[12px] bg-[#1B2D72] bg-no-repeat min-h-[86px] ${
            type === "Setting" ? "bg-bottom" : "bg-center"
          }`}
          style={{
            backgroundImage: `url(${
              type === "Setting"
                ? IMAGES.home.profile.p3
                : IMAGES.home.profile.p2
            })`,
            backgroundSize: type === "Setting" ? "220% 100%" : "100% 100%",
          }}
        >
          <div className="pointer-events-none absolute inset-0 rounded-[12px] bg-black/20" />
          <div />
          {/* GRID FEATURES */}
          <div className="relative rounded-xl p-1">
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
      <div className="mx-auto h-10 w-10">
        <div className="relative h-full w-full">
          <Image src={img} alt={label} fill className="object-cover" />
          {badge ? (
            <span className="absolute -right-2 -bottom-1 rounded-full bg-[#FF3B3B] px-1.5 py-[1px] text-[10px] font-semibold text-white shadow">
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
