"use client";

import {
  forwardRef,
  useImperativeHandle,
  useContext,
  useEffect,
  useState,
  memo,
} from "react";
import Image from "next/image";
import { useRouter } from "next/navigation";
import { useTranslations } from "next-intl";
import { toast } from "react-hot-toast";

import { IMAGES } from "@/constants/images";
import { UIContext } from "@/contexts/UIProvider";
import { useBalanceStore } from "@/store/zustand/balanceStore";
import { useProviderStore } from "@/store/zustand/providerStore";
import {
  formatPhonePretty,
  getClientIp,
  getMemberInfo,
  getUserLevel,
} from "@/utils/utility";
import { LOCAL_STORAGE_NAMES } from "@/constants/cookies";
import { handleTransferAllPointsToCredit } from "@/utils/TransferUti";

import { useLazyGetMemberViewQuery } from "@/services/authApi";

import {
  useTransferAllPointToCreditMutation,
  useTransferOutMutation,
} from "@/services/transactionApi";

/* 🔔 notification */
import { useGetNotificationListQuery } from "@/services/notificationApi";
/* ✅ your project uses commonApi for marquee/slider */
import {
  useGetAgentIconQuery,
  useGetSliderListQuery,
} from "@/services/commonApi";
import MarqueeTitle from "@/components/shared/MarqueeTitle";
import Link from "next/link";
import { setCookie } from "@/utils/cookie";
import { COOKIE_NAMES } from "@/constants/cookies";

const HomeProfileWallet = forwardRef(function HomeProfileWallet(props, ref) {
  const t = useTranslations();
  const router = useRouter();
  const { isGlobalLoading, setIsGlobalLoading } = useContext(UIContext);

  const [info, setInfo] = useState(null);
  const [level, setLevel] = useState(0);
  const { data: agentIconData } = useGetAgentIconQuery(
    info?.member_id ? { member_id: info.member_id } : {},
    { skip: !info?.member_id },
  );
  const logoSrc =
    agentIconData?.status && agentIconData?.icon
      ? `${process.env.NEXT_PUBLIC_IMAGE_URL}/${agentIconData.icon}`
      : IMAGES.logo99;

  const { credits, setCredits, setPoints, isTransferring, setIsTransferring } =
    useBalanceStore();

  const { clearPrevGameMemberId, clearPrevProviderId } = useProviderStore();

  const [triggerGetMemberView, { isLoading, isFetching }] =
    useLazyGetMemberViewQuery();
  const [transferAllPointToCredit] = useTransferAllPointToCreditMutation();
  const [transferOut] = useTransferOutMutation();

  /* ================= INIT ================= */
  useEffect(() => {
    const member = getMemberInfo();
    const level = getUserLevel();

    setInfo(member);
    setLevel(level);

    if (!member?.member_id) return;

    (async () => {
      try {
        const res = await triggerGetMemberView({
          member_id: member.member_id,
        }).unwrap();

        if (res?.data) {
          const {
            member_id,
            member_login,
            member_name,
            phone,
            prefix,
            vip,
            balance,
          } = res.data;

          // update cookie
          setCookie(
            COOKIE_NAMES.MEMBER_INFO,
            JSON.stringify({
              member_id,
              member_login,
              member_name,
              phone,
              prefix,
            }),
          );

          if (vip != null) {
            setLevel(vip);
            localStorage.setItem(LOCAL_STORAGE_NAMES.LEVEL, vip);
          }

          // if (balance != null) {
          //   setCredits(Number(balance));
          // }
        }
      } catch (err) {
        console.error(err);
      }
    })();
  }, []);

  // useEffect(() => {
  //   console.log("HomeProfileWallet credits changed ->", credits);
  // }, [credits]);

  /* ================= REFRESH ================= */
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

      clearPrevProviderId();
      clearPrevGameMemberId();

      if (resp?.member?.balance != null) {
        setCredits(Number(resp.member.balance));
      }

      setPoints(0);

      const result = await triggerGetMemberView({
        member_id: info.member_id,
      }).unwrap();

      if (result?.data?.vip) {
        setLevel(result.data.vip);
        localStorage.setItem(LOCAL_STORAGE_NAMES.LEVEL, result.data.vip);
      }
    } catch (err) {
      console.error(err);
    } finally {
      setIsGlobalLoading(false);
    }
  };

  useImperativeHandle(ref, () => ({
    refreshBalance: handleRefresh,
  }));

  /* ======================================================
     ❗ AUTO TRANSFER + MEMBER VIEW LOGIC (UNCHANGED)
     ====================================================== */
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

      // CASE 1
      if (justLogin && prevGame) {
        if (isTransferring || isLoading) return;

        setIsGlobalLoading(true);
        setIsTransferring(true);

        try {
          const ip = await getClientIp();
          const resp = await transferOut({
            member_id: info.member_id,
            ip,
          }).unwrap();

          // if (!resp?.status) {
          //   toast.error(resp?.message || t("transfer.failed"));
          //   return;
          // }

          clearPrevProviderId();
          clearPrevGameMemberId();
          needMemberView = true;
        } catch {
          needMemberView = true;
        } finally {
          setIsTransferring(false);
          setIsGlobalLoading(false);
          localStorage.removeItem(LOCAL_STORAGE_NAMES.JUST_LOGIN);
        }
      }

      // CASE 2
      if (justLogin && !prevGame) {
        needMemberView = true;
        localStorage.removeItem(LOCAL_STORAGE_NAMES.JUST_LOGIN);
      }

      if (!needMemberView) return;

      try {
        setIsGlobalLoading(true);

        const res = await triggerGetMemberView({
          member_id: info.member_id,
        }).unwrap();

        if (res?.data?.vip) {
          setLevel(res.data.vip);
          localStorage.setItem(LOCAL_STORAGE_NAMES.LEVEL, res.data.vip);
        }

        if (res?.data?.balance != null) {
          setCredits(Number(res.data.balance));
        }
      } catch {
        // ignore
      } finally {
        setIsGlobalLoading(false);
      }

      sessionStorage.setItem(HAS_FETCHED_KEY, "1");
    })();
  }, [info?.member_id, isTransferring, isLoading]);

  /* ================= 🔔 NOTIFICATION ================= */
  const { data: noticeData } = useGetNotificationListQuery(
    info?.member_id ? { member_id: info.member_id } : {},
    { skip: !info?.member_id },
  );

  /* ✅ replace marqueeApi with commonApi slider query */
  const { data: marqueData } = useGetSliderListQuery(
    info?.member_id ? { member_id: info.member_id } : {},
    { skip: !info?.member_id },
  );

  const noticeUnread =
    noticeData?.data?.filter((n) => n.is_read === 0).length || 0;

  const marqueeUnread =
    marqueData?.data?.filter((n) => n.is_read === 0).length || 0;

  const hasUnread = noticeUnread > 0 || marqueeUnread > 0;
  const totalUnread = noticeUnread + marqueeUnread;

  /* ================= UI ================= */
  return (
    <div className="relative mx-auto h-[96px] max-w-[480px] px-3 pt-3">
      {/* Top bar */}
      <div className="relative flex items-end py-2">
        {/* Logo */}
        <Link href="/">
          <Image
            src={logoSrc}
            alt="Expro99"
            width={120}
            height={32}
            className="w-[120px] cursor-pointer object-contain"
            priority
          />
        </Link>

        {/* RIGHT controls – align to bottom */}
        <div className="absolute right-[-12px] bottom-0 flex items-center gap-1">
          {/* {info?.member_name?.trim() && (
            <span className="text-sm sm:text-base md:text-lg font-bold text-[#F8AF07] leading-none">
              {formatPhonePretty(info.member_name)}
            </span>
          )}

          <Image
            src={IMAGES.vip[`vip${level}`]}
            alt={`vip${level}`}
            width={50}
            height={30}
            className="object-contain"
          /> */}

          <button
            onClick={() => router.push("/notification")}
            className="cursor-pointer active:scale-95"
          >
            <MemoIconCircle
              src={IMAGES.iconRing2}
              alt="Notifications"
              totalUnread={totalUnread}
            />
          </button>
        </div>
      </div>

      {/* VIP Balance Card */}
      <div className="mt-1">
        <div className="rounded-xl p-[2px] bg-[linear-gradient(90deg,#F8AF07_1.47%,#FFFC86_98.68%)]">
          <div
            className="relative min-h-[86px] rounded-[12px] bg-[#1B2D72] p-3 bg-center bg-no-repeat"
            style={{
              backgroundImage: `url(${IMAGES.home.profile.p1})`,
              backgroundSize: "100% 100%",
            }}
          >
            <div className="pointer-events-none absolute inset-0 rounded-[12px] bg-black/20" />
            {/* Top row: name + vip */}
            <div className="relative z-10 flex items-center justify-between ">
              <div className="flex items-center gap-2 text-sm h-[20px]">
                {!!info?.member_name?.trim() && (
                  <span
                    className="font-semibold    font-semibold
    bg-[linear-gradient(180deg,#F8AF07_0%,#FFFC86_100%)]
    bg-clip-text text-transparent"
                  >
                    {formatPhonePretty(info.member_name)}
                  </span>
                )}

                <div className="flex items-center py-[21x]">
                  <Image
                    src={IMAGES.vip[`vip${level}`]}
                    alt={`vip${level}`}
                    width={45}
                    height={45}
                    className="object-contain"
                  />
                </div>
              </div>
            </div>

            {/* Label row: 账户余额 + refresh */}

            <div className="relative z-10 mt-1 flex items-center justify-between text-[11px] text-white/80">
              {/* LEFT */}
              <div className="flex items-center gap-2">
                {/* label */}
                <span className="text-[11px] text-white/80">
                  {t("wallets.balance")}
                </span>

                {/* money block */}
                <div className="flex items-start gap-1">
                  <span
                    className="
        text-xs
        mt-[2px]
        bg-[linear-gradient(180deg,#F8AF07_0%,#FFFC86_100%)]
        bg-clip-text text-transparent
      "
                  >
                    RM
                  </span>

                  <span
                    className="
        text-2xl font-extrabold tabular-nums leading-none
        bg-[linear-gradient(180deg,#F8AF07_0%,#FFFC86_100%)]
        bg-clip-text text-transparent
      "
                  >
                    {credits.toFixed(2)}
                  </span>
                </div>
              </div>

              {/* RIGHT */}
              <div className="rounded-full bg-[linear-gradient(180deg,#F8AF07_0%,#FFFC86_100%)] p-[1px]">
                <button
                  onClick={handleRefresh}
                  disabled={isGlobalLoading}
                  className="flex items-center gap-1.5 h-8 px-3 rounded-full bg-[#101563]"
                >
                  <img
                    src={IMAGES.home.profile.currency}
                    alt="restore wallet"
                    className={`w-4 h-4 object-contain shrink-0 ${
                      isGlobalLoading || isTransferring
                        ? "animate-[spin_0.6s_linear_infinite]"
                        : ""
                    }`}
                  />

                  <span
                    className="
          text-xs font-semibold whitespace-nowrap
          bg-[linear-gradient(180deg,#F8AF07_0%,#FFFC86_100%)]
          bg-clip-text text-transparent
        "
                  >
                    {t("transfer.oneClick")}
                  </span>
                </button>
              </div>
            </div>

            {/*          
            <div className="mt-0.5 flex items-center justify-between gap-2">
           
              <div className="flex gap-2">
                <span
                  className="
        relative -top-[-3px]
        text-xs
        bg-[linear-gradient(180deg,#F8AF07_0%,#FFFC86_100%)]
        bg-clip-text text-transparent
      "
                >
                  RM
                </span>

                <span
                  className="
        text-xl font-extrabold tabular-nums
        bg-[linear-gradient(180deg,#F8AF07_0%,#FFFC86_100%)]
        bg-clip-text text-transparent
      "
                >
                  {credits.toFixed(2)}
                </span>
              </div>

      
              <div className="rounded-full bg-[linear-gradient(180deg,#F8AF07_0%,#FFFC86_100%)] p-[1px]">
                <button
                  onClick={handleRefresh}
                  disabled={isGlobalLoading}
                  className="flex items-center gap-1.5 h-8 px-3 rounded-full bg-[#101563] "
                >
                  <img
                    src={IMAGES.home.profile.currency}
                    alt="restore wallet"
                    className={`w-4 h-4 object-contain shrink-0 ${
                      isGlobalLoading || isTransferring
                        ? "animate-[spin_0.6s_linear_infinite]"
                        : ""
                    }`}
                  />

                  <span
                    className="
          text-xs font-semibold whitespace-nowrap
          bg-[linear-gradient(180deg,#F8AF07_0%,#FFFC86_100%)]
          bg-clip-text text-transparent
        "
                  >
                    {t("transfer.oneClick")}
                  </span>
                </button>
              </div>
            </div> */}

            {/* Optional decorative glow */}
          </div>
        </div>
      </div>
    </div>
  );
});

export default HomeProfileWallet;

function IconCircle({ src, alt, totalUnread }) {
  const hasUnread = totalUnread > 0;

  return (
    <div className="relative grid h-12 w-12 place-items-center">
      <Image
        src={src}
        alt={alt}
        width={30}
        height={30}
        className="w-5 h-5 object-contain"
      />

      {hasUnread && (
        <span
          className="
        absolute
        top-1/2 right-3
        translate-x-1/2 -translate-y-1/2
        min-w-[18px] h-[18px]
        px-[4px]
        rounded-full
        bg-red-500
        text-white
        text-[12px] font-bold
        flex items-center justify-center
      "
        >
          {totalUnread > 99 ? "99+" : totalUnread}
        </span>
      )}
    </div>
  );
}

const MemoIconCircle = memo(IconCircle);
