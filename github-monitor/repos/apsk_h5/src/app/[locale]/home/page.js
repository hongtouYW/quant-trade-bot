"use client";
import GameTile from "@/components/shared/GameTile";
import HeroCarousel from "@/components/home/HomeBanner";
import HomeMarquee from "@/components/home/HomeMarquee";
import HomeProfilePanel from "@/components/home/HomeProfilePanel";
import HomeSideBar from "@/components/home/HomeSideBar";
import ChangePasswordSuccessDialog from "@/components/ChangePasswordSuccessDialog";
import { IMAGES } from "@/constants/images";
import { useTranslations } from "next-intl";
import Image from "next/image";
import { useContext, useEffect, useMemo, useRef, useState } from "react";
import SelectPlayerDialog from "./components/SelectPlayerDialog";
import AddPlayerPopup from "./components/AddPlayerDialog";
import { useRouter } from "next/navigation";

import {
  useGetGameListQuery,
  useGetPlatormListQuery,
  useGetProviderListQuery,
} from "@/services/gameApi";
import {
  extractError,
  getClientIp,
  getMemberInfo,
  maskPhoneCompact,
  isFullHttpUrl,
  isUnauthorized,
  isValidHttpUrl,
} from "@/utils/utility";
import {
  useAddPlayerMutation,
  useFastOpenGameMutation,
  useGetPlayerListMutation,
  useGetPlayerListQuery,
  useLazyGetPlayerListQuery,
  useLazyGetPlayerViewQuery,
} from "@/services/authApi";

import { useOpenGameUrlMutation } from "@/services/gameApi";

import { UIContext } from "@/contexts/UIProvider";
import { toast } from "react-hot-toast";
import { useHomeSideBarStore } from "@/store/zustand/useHomeSideBarStore";
import { useProviderStore } from "@/store/zustand/providerStore";
import { useGameStore } from "@/store/zustand/gameStore";

import ProviderPage from "./components/ProviderPage";
import {
  useTopupStatusNowMutation,
  useTopupStatusQuery,
  useTransferAllCreditToPointMutation,
  useTransferAllPointToCreditMutation,
  useTransferOutMutation,
} from "@/services/transactionApi";
import { useBalanceStore } from "@/store/zustand/balanceStore";
import { handleTransferAllPointsToCredit } from "@/utils/TransferUti";
import { openGameFlow } from "@/utils/openGameFlow";
import { skipToken } from "@reduxjs/toolkit/query";
import { encryptObject } from "@/utils/encryption";
import { isIframeLoadGame } from "@/constants/cookies";
import { useMarqueeStore } from "@/store/zustand/marqueStore";
import { useGetAgentIconQuery } from "@/services/commonApi";

export default function Home() {
  const topupPlaceholder = "${topup}";
  const {
    activeId,
    setActiveId,
    activeTab,
    setActiveTab,
    setActivePid,
    activePid,
  } = useHomeSideBarStore();

  const { setLoading } = useContext(UIContext);
  const t = useTranslations();

  const [
    addPlayer,
    {
      data: dataAddPlayer,
      error: dataAddPlayerError,
      isLoading: dataAddPlayerLoading,
      isSuccess: dataAddPlayerSuccess,
    },
  ] = useAddPlayerMutation();

  const tabs = [
    { key: "hot", label: t("tabs.hot") }, // 热门
    { key: "recent", label: t("tabs.recent") }, // 最近
    { key: "favorite", label: t("tabs.favorite") }, // 收藏
  ];
  const info = getMemberInfo();
  const { data: agentIconData } = useGetAgentIconQuery(
    info?.member_id ? { member_id: info.member_id } : skipToken,
  );
  const logo911Src =
    agentIconData?.status && agentIconData?.icon
      ? `${process.env.NEXT_PUBLIC_IMAGE_URL}/${agentIconData.icon}`
      : IMAGES.logo911;
  const [selectedGame, setSelectedGame] = useState(null);

  const [dialogOpen, setDialogOpen] = useState(false);
  const [activeGameId, setActiveGameId] = useState(null);
  const [selectedByProvider, setselectedByProvider] = useState({});
  const [selectedByProviderId, setSelectedProviderId] = useState(null);
  const [selectedPlatformType, setSelectedPlatformType] = useState(null);
  const [selectedByProviderCat, setselectedByProviderCat] = useState({});
  const [selectedGamePid, setSelectedGamePid] = useState(null);
  const [selectedProviderBanner, setSelectedProviderBanner] = useState("");
  const [showChangePasswordSuccess, setShowChangePasswordSuccess] =
    useState(false);
  const [addPlayerOpen, setAddPlayerOpen] = useState(false);
  const router = useRouter();
  const [memberId, setMemberId] = useState(null);
  const [newGameMemberId, setNewGameMemberId] = useState(null);
  const [newGameLoginId, setNewGameLoginId] = useState(null);
  const [bookMark, setBookMark] = useState(false);
  const [gameBookMarkId, setGameBookMarkId] = useState(null);
  const [openGameUrl] = useOpenGameUrlMutation();
  const [cooldown, setCooldown] = useState(false);
  const [topupStatusNow, { isLoading, data }] = useTopupStatusNowMutation();

  const [
    triggerGetPlayerView,
    { data: player, isLoading: playerLoading, isFetching: playerFetching },
  ] = useLazyGetPlayerViewQuery();

  const [transferAllPointToCredit] = useTransferAllPointToCreditMutation();
  const [transferOut] = useTransferOutMutation();

  const resetProvider = useProviderStore((s) => s.reset);
  const resetGame = useGameStore((s) => s.reset);
  const {
    selectedProvider,
    prevGameMemberId,
    clearPrevGameMemberId,
    setPrevGameMemberId,
  } = useProviderStore();
  const setSelectedProvider = useProviderStore((s) => s.setSelectedProvider);
  const scrollRef = useRef(null);
  const {
    points,
    credits,
    setCredits,
    setPoints,
    isTransferring,
    setIsTransferring,
    markTransferDone,
  } = useBalanceStore();

  const [transferAllCreditToPoint] = useTransferAllCreditToPointMutation();
  const [fastOpenGame] = useFastOpenGameMutation();

  const [
    triggerGetPlayerList,
    {
      data: playerData,
      isLoading: isPlayerLoading,
      isFetching: isPlayerFetching,
      isError: playerError,
    },
  ] = useLazyGetPlayerListQuery();

  // const {
  //   data: playerData,
  //   isLoading: isPlayerLoading,
  //   isFetching: isPlayerFetching,
  //   isError: playerError,
  //   refetch: refetchPlayerList,
  // } = useGetPlayerListQuery(
  //   selectedByProviderId && {
  //     provider_id: selectedByProviderId,
  //     member_id: info?.member_id,
  //   },
  //   { skip: !selectedByProviderId }
  // );

  // const {
  //   data: providerList,
  //   isLoading: isProviderLoading,
  //   isFetching: isProviderFetching,
  //   isError: isProviderError,
  //   refetch: isProviderRefetch,
  // } = useGetProviderListQuery(
  //   {
  //     provider_category: activeId === "all" ? null : activeId,
  //     member_id: memberId,
  //   },
  //   {
  //     skip: !memberId,
  //     refetchOnMountOrArgChange: false, // don't refetch every time component remounts
  //     refetchOnFocus: false, // don't auto refresh when tab becomes active
  //     refetchOnReconnect: false, // don't auto refresh when network reconnects
  //     keepUnusedDataFor: 3600, // ⬅️ cache for 1 hour
  //   }
  // );

  // const {
  //   data: platformList,
  //   isLoading: isPlatformLoading,
  //   isFetching: isPlatformFetching,
  //   isError: isPlatformError,
  //   refetch: isPlatforMReferch,
  // } = useGetPlatormListQuery(
  //   {
  //     type: activeId,
  //     member_id: memberId,
  //   },
  //   {
  //     skip: !memberId,
  //     refetchOnMountOrArgChange: false, // don't refetch every time component remounts
  //     refetchOnFocus: false, // don't auto refresh when tab becomes active
  //     refetchOnReconnect: false, // don't auto refresh when network reconnects
  //     keepUnusedDataFor: 3600, // ⬅️ cache for 1 hour
  //   }
  // );

  // const platforms = platformList?.data || []; // ✅ API returns { data: [...] }

  const players = playerData?.data || []; // ✅ API returns { data: [...] }
  const [visibleGames, setVisibleGames] = useState([]);
  const cacheKey = `providerList_${memberId}_${activeId}`;
  const homeProfileMenuRef = useRef(null);
  useEffect(() => {
    const cached = localStorage.getItem(cacheKey);
    if (cached) {
      try {
        const parsed = JSON.parse(cached);
        if (parsed?.data?.length) {
          setVisibleGames(parsed.data);
        }
      } catch {}
    }
  }, [cacheKey]);

  // ✅ Step 2: Run RTK Query normally (to refresh in background)
  const {
    data: providerList,
    isFetching: isProviderFetching,
    isLoading: isProviderLoading,
    isError,
  } = useGetProviderListQuery(
    {
      provider_category: activeId === "all" ? null : activeId,
      member_id: memberId,
    },
    {
      skip: !memberId,
      refetchOnMountOrArgChange: false, // ✅ re-fetch when activeId/memberId changes
      refetchOnFocus: false, // ❌ no spam when switching tabs
      refetchOnReconnect: false, // ❌ no spam on reconnect
      keepUnusedDataFor: 3600, // ✅ cache in memory for 1h
    },
  );

  // ✅ Step 3: If new data arrives, store it quietly in localStorage (don’t change UI now)
  useEffect(() => {
    if (providerList?.data?.length) {
      localStorage.setItem(
        cacheKey,
        JSON.stringify({
          savedAt: Date.now(),
          data: providerList.data,
        }),
      );
      // ❌ Don’t call setVisibleGames() here — only update cache silently
    }
  }, [providerList, cacheKey]);

  useEffect(() => {
    const info = getMemberInfo();

    setMemberId(info?.member_id ?? null);
  }, []);

  useEffect(() => {
    if (typeof window === "undefined") return;

    const shouldShow =
      sessionStorage.getItem("show_change_password_success") === "1";

    if (!shouldShow) return;

    sessionStorage.removeItem("show_change_password_success");
    setShowChangePasswordSuccess(true);
  }, []);

  // useEffect(() => {
  //   // When either initial load or refetch in progress
  //   const isBusy = isPlayerLoading || isPlayerFetching;
  //   setLoading(isBusy);
  // }, [isPlayerLoading, isPlayerFetching, setLoading]);
  useEffect(() => {
    if (providerList?.data) {
      setVisibleGames(providerList.data);
    }
  }, [providerList]);

  useEffect(() => {
    if (!scrollRef.current || !activePid) return;

    const container = scrollRef.current;
    const item = container.querySelector(`[data-id='${activePid}']`);

    // if (item) {
    //   item.scrollIntoView({
    //     behavior: "auto",
    //     block: "center",
    //     inline: "center",
    //   });
    // }
  }, [activePid, visibleGames]);

  useEffect(() => {
    // const addItem = useMarqueeStore.getState().addItem;
    // addItem({
    //   id: `topup-${"abc" || "demo"}-${memberId || "guest"}`,
    //   text: `${maskPhoneCompact("0123456788")} ${"${topup}"} RM ${"444" || 0}`,
    //   time: Date.now(),
    // });
    (async () => {
      if (typeof window === "undefined") return;
      const params = new URLSearchParams(window.location.search);
      const txid = params.get("id");

      const method = params.get("method");

      const addItem = useMarqueeStore.getState().addItem;
      if (!txid || !method || !memberId) return;
      //   // ---- REMOVE QUERYSTRING IMMEDIATELY ----
      //   //
      setLoading(true);
      try {
        const res = await topupStatusNow({
          credit_id: txid,
          user_id: String(memberId),
        }).unwrap();
        const creditStatus = res?.credit?.status;
        const orderStatus = res?.order_status;
        const amount = res?.credit?.amount;
        homeProfileMenuRef.current?.refreshBalance();
        if (creditStatus === 1 || orderStatus === "completed") {
          sessionStorage.removeItem("wallet_member_fetched");
          addItem({
            id: `topup-${txid || "demo"}-${memberId || "guest"}`,
            text: `${maskPhoneCompact(info.phone)} ${topupPlaceholder} RM ${amount || 0}`,
            time: Date.now(),
          });
          toast.success(`${t("topups.success.title")} • +${amount || 0}`, {
            duration: 5000,
          });
        } else if (creditStatus === 0) {
          toast.error(t("topups.status.processing"));
        } else {
          toast.error(t("topups.status.failed"));
        }
      } catch (e) {
        homeProfileMenuRef.current?.refreshBalance();
        const extracted = extractError(e);
        toast.error(extracted.message, { duration: 5000 });
      } finally {
        window.history.replaceState({}, "", window.location.pathname);
        setLoading(false);
      }
    })();
  }, [memberId]);

  const handleChoosePlayer = (player) => {
    setDialogOpen(false);
    // router.push(`/game-start/${player.gamemember_id}`);
  };

  // const handleSelectProvider = async (g) => {
  //   // 🛑 Step 0 — prevent concurrent transfer
  //   if (isTransferring) {
  //     toast.error(t("transfer.processing"));
  //     return;
  //   }

  //   let gameMemberId = null; // 👈 define globally for the try block
  //   let updateCredit = credits; // 👈 define globally for the try block
  //   try {
  //     setLoading(true); // 🔄 show global loading

  //     // 🧩 Step 1 — Restore to previous value
  //     if (prevGameMemberId) {
  //       try {
  //         setIsTransferring(true);

  //         const resp = await transferAllPointToCredit({
  //           gamemember_id: prevGameMemberId,
  //           member_id: info.member_id,
  //           ip: await getClientIp(),
  //         }).unwrap();

  //         // if (!resp?.status) {
  //         //   toast.error(t("transfer.failed"));
  //         //   return;
  //         // }
  //         updateCredit = Number(resp.member.balance);
  //         setCredits(Number(resp.member.balance));
  //         setPoints(Number(resp.player.balance));

  //        clearPrevGameMemberId();

  //       } catch (err) {
  //         console.error("Return transfer failed:", err);
  //         toast.error(t("transfer.failed"));
  //         return;
  //       } finally {
  //         setIsTransferring(false);
  //         markTransferDone?.();
  //       }
  //     }

  //     // 🧩 Step 2 — extract values safely
  //     const providerId = g.provider_id;
  //     const providerName = g.provider_name;
  //     const gamePlatformId = g.gameplatform_id;
  //     const providerCategory = g.provider_category;
  //     const isBookmark = g.isBookmark;
  //     const memberId = info?.member_id;
  //     const platformType = g.platform_type ? g.platform_type.toLowerCase() : "";

  //     // 🧩 Step 3 — prepare UI state
  //     setSelectedProviderId(providerId);
  //     setselectedByProvider(providerName);
  //     setSelectedGamePid(gamePlatformId);
  //     setselectedByProviderCat(providerCategory);
  //     setSelectedPlatformType(platformType);

  //     setActivePid(providerId);
  //     setBookMark(isBookmark);

  //     // 🧩 Step 4 — clear cached games
  //     setTimeout(() => sessionStorage.removeItem("visibleGamesCache"), 0);

  //     // 🧩 Step 5 — fetch existing player list
  //     const playerData = await triggerGetPlayerList({
  //       provider_id: providerId,
  //       member_id: memberId,
  //     }).unwrap();

  //     // 🧩 Step 5A — existing player found
  //     if (playerData?.data?.length > 0) {
  //       const gameMember = playerData.data[0];
  //       gameMemberId = gameMember.gamemember_id; // 👈 assign here

  //       if (updateCredit > 0) {
  //         try {
  //           setIsTransferring(true);

  //           // 🔁 transfer only if credits > 0
  //           const resp = await transferAllCreditToPoint({
  //             member_id: memberId,
  //             gamemember_id: gameMember.gamemember_id,
  //             ip: await getClientIp(),
  //           }).unwrap();

  //           if (resp.status) {
  //             // ✅ transfer success → sync balances
  //             setCredits(Number(resp.member.balance));
  //             setPoints(Number(resp.player.balance));
  //           } else {
  //             toast.error(t("transfer.failed"));
  //             return;
  //           }
  //         } finally {
  //           setIsTransferring(false);
  //           markTransferDone();
  //         }
  //       }

  //       // ✅ either credit=0 or transfer success
  //       resetProvider();
  //       resetGame();
  // ✅ 1. update provider (per-tab, sessionStorage)
  // setSelectedProvider({
  //   gamemember_id: gameMember.gamemember_id,
  //   providerId,
  //   gamePid: gamePlatformId,
  //   providerName,
  //   providerCategory,
  //   platformType,
  // });

  // // ✅ 2. update shared prevGameMemberId (cross-tab, localStorage)
  // setPrevGameMemberId(gameMember.gamemember_id);

  //       // 🧩 Step 6 — decide open game or go game start page
  //       if (platformType === "web-lobby") {
  //         try {
  //           let gameUrl = "";

  //           const res = await openGameUrl({
  //             member_id: info.member_id,
  //             gamemember_id: gameMember.gamemember_id, // ✅ fixed: was using wrong var
  //           }).unwrap();

  //           gameUrl = res?.data ?? res?.Data;
  //           if (!gameUrl) {
  //             toast.error(t("wallets.cannotLoadGame2"));
  //             await handleTransferAllPointsToCredit({
  //               info,
  //               gameMemberId: gameMember.gamemember_id, // ✅ fixed: wrong var
  //               transferAllPointToCredit,
  //               setIsGlobalLoading: setLoading,
  //             });
  //             return;
  //           }

  //           sessionStorage.setItem("redirectUrl", gameUrl);
  //           router.push("/redirect");
  //         } catch (err) {
  //           console.error("openGame error:", err);
  //           toast.error(
  //             t("common.somethingWentWrong") || "Something went wrong"
  //           );

  //           // ✅ fallback: optional return points if error occurs
  //           await handleTransferAllPointsToCredit({
  //             info,
  //             gameMemberId: gameMember.gamemember_id,
  //             transferAllPointToCredit,
  //             setIsGlobalLoading: setLoading,
  //           });
  //         }
  //       } else {
  //         router.push("/game-start");
  //       }

  //       return; // ✅ prevent continuing to Step 5B
  //     }

  //     // 🧩 Step 5B — no player found, create new
  //     const result = await addPlayer({
  //       provider_id: providerId,
  //       member_id: memberId,
  //     }).unwrap();

  //     if (result?.status) {
  //       gameMemberId = result?.data.gamemember_id; // 👈 assign here
  //       if (updateCredit > 0) {
  //         try {
  //           setIsTransferring(true);

  //           const resp = await transferAllCreditToPoint({
  //             member_id: memberId,
  //             gamemember_id: result?.data.gamemember_id,
  //             ip: await getClientIp(),
  //           }).unwrap();

  //           if (resp.status) {
  //             setCredits(Number(resp.member.balance));
  //             setPoints(Number(resp.player.balance));
  //           } else {
  //             toast.error(t("transfer.failed"));
  //             return;
  //           }
  //         } finally {
  //           setIsTransferring(false);
  //           markTransferDone();
  //         }
  //       }

  //       // ✅ credit=0 or transfer success → start game
  //       resetProvider();
  //       resetGame();
  // ✅ 1. set per-tab provider info (sessionStorage)
  // setSelectedProvider({
  //   gamemember_id: result?.data.gamemember_id,
  //   providerId,
  //   gamePid: gamePlatformId,
  //   providerName,
  //   loginId: result?.data.loginId,
  //   providerCategory,
  //   platformType,
  // });

  // // ✅ 2. set cross-tab shared game member id (localStorage)
  // setPrevGameMemberId(result?.data.gamemember_id);

  //       // 🧩 Step 6 — decide open game or go game start page
  //       if (platformType === "web-lobby") {
  //         try {
  //           let gameUrl = "";

  //           const res = await openGameUrl({
  //             member_id: info.member_id,
  //             gamemember_id: result?.data.gamemember_id,
  //           }).unwrap();

  //           gameUrl = res?.data ?? res?.Data;
  //           if (!gameUrl) {
  //             toast.error(t("wallets.cannotLoadGame2"));
  //             await handleTransferAllPointsToCredit({
  //               info,
  //               gameMemberId: result?.data.gamemember_id,
  //               transferAllPointToCredit,
  //               setIsGlobalLoading: setLoading,
  //             });
  //             return;
  //           }

  //           sessionStorage.setItem("redirectUrl", gameUrl);
  //           router.push("/redirect");
  //         } catch (err) {
  //           console.error("openGame error:", err);
  //           toast.error(
  //             t("common.somethingWentWrong") || "Something went wrong"
  //           );

  //           // ✅ optional fallback if openGame fails
  //           await handleTransferAllPointsToCredit({
  //             info,
  //             gameMemberId: result?.data.gamemember_id,
  //             transferAllPointToCredit,
  //             setIsGlobalLoading: setLoading,
  //           });
  //         }
  //       } else {
  //         router.push("/game-start");
  //       }
  //     } else {
  //       toast.error(result?.message || t("transfer.createPlayerFailed"));
  //     }
  //   } catch (err) {
  //     // ✅ now this is always safe
  //     if (gameMemberId) {
  //       await handleTransferAllPointsToCredit({
  //         info,
  //         gameMemberId,
  //         transferAllPointToCredit,
  //         setIsGlobalLoading: setLoading,
  //       });
  //     }

  //     const error = extractError(err);
  //     toast.error(error.message || t("transfer.generalError"));
  //   } finally {
  //     setLoading(false); // ✅ centralized loading cleanup
  //   }
  // };

  const handleSelectProvider = async (g) => {
    // router.push(
    //   "/iframe?url=" + "https://google.com" + "&&platformType=web-lobby"
    // );
    if (g.platform_type === "web-lobby" || g.platform_type === "app") {
      if (cooldown || isTransferring) {
        toast.error(t("transfer.processing"));
        return;
      }
    }

    setCooldown(true);
    setTimeout(() => setCooldown(false), 3000); // 3 seconds

    // 🧩 Step 1 — Extract & normalize provider info
    const providerId = g.provider_id;
    const providerName = g.provider_name;
    const providerIcon = g.icon_sm ?? "";
    const providerBanner = g.banner ?? "";
    // const providerBanner = "images/home/profile/profilebg.webp";
    const gamePlatformId = g.gameplatform_id;
    const providerCategory = g.provider_category;
    const isBookmark = g.isBookmark;
    const memberId = info?.member_id;
    const platformType = g.platform_type ? g.platform_type.toLowerCase() : "";

    // 🧩 Step 2 — Update UI (Zustand/UI only)
    setSelectedProviderId(providerId);
    setselectedByProvider(providerName);
    setSelectedGamePid(gamePlatformId);
    setselectedByProviderCat(providerCategory);
    setSelectedPlatformType(platformType);
    setSelectedProviderBanner(providerBanner);
    setActivePid(providerId);
    setBookMark(isBookmark);

    // 🧹 Clear cached visible games
    setTimeout(() => sessionStorage.removeItem("visibleGamesCache"), 0);

    // 🧩 Step 3 — Decide flow type
    //only for use external tab
    if (platformType === "web-lobby") {
      if (!isIframeLoadGame) {
        // Open redirect tab (so popup blockers won’t block)
        const payload = encryptObject({
          provider_id: providerId,
          member_id: memberId,
          provider_name: providerName,
          provider_category: providerCategory,
          gameplatform_id: gamePlatformId,
          platform_type: platformType,
          providerIcon: providerIcon,
          providerBanner: providerBanner,
        });

        const url = `/game-redirect?payload=${encodeURIComponent(payload)}`;

        window.open(url, "_blank");
        return;
      }
    }

    // 🧩 Step 4 — Normal inline flow (backend logic)
    const opeGame = await openGameFlow({
      providerId,
      info,
      platformType,
      providerName,
      providerIcon,
      providerBanner,
      gamePlatformId,
      providerCategory,
      addPlayer,
      triggerGetPlayerList,
      openGameUrl,
      transferAllCreditToPoint,
      transferAllPointToCredit,
      transferOut,
      fastOpenGame,
      setLoading,
      router,
      prevGameMemberId,
      t,
    });
    if (opeGame?.success === false) {
      const msg =
        opeGame?.message || opeGame?.data?.data?.message || "Operation failed";

      // CASE 1 — access token expired (should trigger refresh flow)
      if (isUnauthorized(msg)) {
        // let your RTK refresh mechanism take over
        return;
      }

      toast.error(msg, { duration: 5000 });
    } else {
      if (isIframeLoadGame && platformType === "web-lobby") {
        router.push(
          "/iframe?url=" + opeGame?.message + "&&platformType=web-lobby",
        );
      }
    }
  };

  return (
    <main className="min-h-dvh text-white">
      <div className="relative">
        {/* ===== Carousel ===== */}
        <div className="relative z-0 h-[200px]">
          <HeroCarousel
            banners={[
              {
                background: IMAGES.home.banner.banner1,
                word: IMAGES.home.banner.word,
              },
              // {
              //   background: IMAGES.home.banner.banner2,
              //   word: IMAGES.home.banner.word,
              // },
            ]}
            logo911={logo911Src}
            search={IMAGES.iconSearch}
            ring={IMAGES.iconRing}
          />
        </div>

        {/* ===== Overlay Profile Panel ===== */}
        <div className="relative -mt-12 z-10">
          <HomeProfilePanel type="Home" ref={homeProfileMenuRef} />
        </div>
      </div>
      {/* ===== Marque ===== */}
      <div className="mt-1">
        <HomeMarquee />
      </div>

      {/* ===== Home Sidebar ===== */}
      <HomeSideBar
        activeId={activeId}
        onSelect={(it) => {
          setVisibleGames([]);
          setActiveId(it.id);
          setActivePid(null); // ✅ reset scroll/selection
        }}
      />

      <div className="mx-auto max-w-[480px]bg-[#01133C] flex overflow-hidden">
        {/* Right side */}
        <main className="flex-1 flex flex-col min-h-0 overflow-hidden text-white pl-2 pr-2">
          {/* Tabs */}
          {/* <div className="grid grid-cols-3 gap-6 shrink-0">
            {tabs.map((tab) => {
              const isActive = activeTab === tab.key;
              return (
                <button
                  key={tab.key}
                  onClick={() => setActiveTab(tab.key)}
                  className={`pb-2 text-center text-base font-semibold border-b-2 ${
                    isActive
                      ? "border-[#F8AF07] text-[#F8AF07]"
                      : "border-transparent text-[#F8AF07]/50"
                  }`}
                >
                  {tab.label}
                </button>
              );
            })}
          </div> */}

          {/* Panels (scroll region) */}
          <div className="flex-1 min-h-0 overflow-y-auto">
            <ProviderPage
              t={t}
              activePid={activePid}
              isProviderLoading={isProviderLoading}
              isProviderFetching={isProviderFetching}
              visibleGames={visibleGames}
              onSelectProvider={handleSelectProvider}
            />
          </div>
        </main>
      </div>
      <ChangePasswordSuccessDialog
        open={showChangePasswordSuccess}
        onConfirm={() => setShowChangePasswordSuccess(false)}
      />
    </main>
  );
}
