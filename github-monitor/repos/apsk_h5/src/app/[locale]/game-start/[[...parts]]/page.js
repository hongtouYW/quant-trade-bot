"use client";

import Image from "next/image";
import { useState, useEffect, useRef, useContext, useMemo } from "react";
import { useParams, useRouter } from "next/navigation";
import { useTranslations } from "next-intl";
import Link from "next/link";
import toast from "react-hot-toast";
import { IMAGES } from "@/constants/images";
import SubmitButton from "@/components/shared/SubmitButton";
import CreditBalanceBar from "@/components/shared/CreditBalanceBar";
import { UIContext } from "@/contexts/UIProvider";
import { useBalanceStore } from "@/store/zustand/balanceStore";
import {
  useDeletePlayerMutation,
  useGetPlayerPasswordQuery,
  useGetPlayerViewQuery,
} from "@/services/authApi";
import {
  useGetGameListQuery,
  useGetGameViewQuery,
  useGetProviderViewQuery,
  useOpenGameUrlMutation,
} from "@/services/gameApi";
import {
  useFromCreditToPointMutation,
  useFromPointToCreditMutation,
  useTransferAllCreditToPointMutation,
} from "@/services/transactionApi";
import { skipToken } from "@reduxjs/toolkit/query";
import QRCode from "react-qr-code";
import {
  calcCredit,
  calcPoints,
  extractError,
  getClientIp,
  getMemberInfo,
  handleATMInput,
  isFullHttpUrl,
} from "@/utils/utility";
import { useProviderStore } from "@/store/zustand/providerStore";

import { useGameStore } from "@/store/zustand/gameStore";
import PointBalanceBar from "@/components/shared/PointBalanceBar";
import DetectBackToHome from "@/components/DetectBackHome";
import FastToggle from "@/components/shared/toogle";
import { encryptObject } from "@/utils/encryption";
import GameTileDetail from "@/components/shared/GameTileDetail";

export default function GameStartPage() {
  const t = useTranslations();
  const router = useRouter();
  const { parts } = useParams();
  const [deletePlayer] = useDeletePlayerMutation();
  const [openGameUrl] = useOpenGameUrlMutation();
  const { selectedProvider, clearPrevGameMemberId } = useProviderStore();
  const { selectedGameId, setSelectedGameId } = useGameStore();

  const gmemberId = selectedProvider?.gamemember_id; //player id
  const providerId = selectedProvider?.providerId;
  const gamePlatformId = selectedProvider?.gamePid;
  const providerName = selectedProvider?.providerName
    ? selectedProvider.providerName.toUpperCase()
    : "";

  const providerIcon = selectedProvider?.providerIcon
    ? selectedProvider.providerIcon
    : "";

  const providerBanner = selectedProvider?.providerBanner
    ? selectedProvider.providerBanner
    : "";

  const providerCategory = selectedProvider?.providerCategory;
  const platformType = selectedProvider?.platformType
    ? selectedProvider.platformType.toLowerCase()
    : "";
  const [transferAllCreditToPoint, { isLoading, isSuccess, error }] =
    useTransferAllCreditToPointMutation();

  // ---------- STATES ----------
  const [mounted, setMounted] = useState(false);
  useEffect(() => setMounted(true), []);
  // const [playerId, setPlayerId] = useState(gmemberId);
  const [fromAmount, setFromAmount] = useState("0.00");
  const [toAmount, setToAmount] = useState("0.00");
  const [delta] = useState("+ 0.00");
  const { setLoading, showConfirm, showTransfer } = useContext(UIContext);
  const balanceRef = useRef(null);

  const {
    credits,
    points,
    setCredits,
    setPoints,
    isTransferring,
    setIsTransferring,
    markTransferDone,
  } = useBalanceStore();

  const [isCreditToPoints, setIsCreditToPoints] = useState(true);
  const [transferOpen, setTransferOpen] = useState(false);
  const [info, setInfo] = useState(null);
  const [username, setUsername] = useState("");
  const [password, setPassword] = useState("");
  const [gameUrl, setGameUrl] = useState(null);
  const [visibleGames, setVisibleGames] = useState([]);
  const setSelectedProvider = useProviderStore((s) => s.setSelectedProvider);
  const scrollRef = useRef(null);
  const [deepLink, setDeepLink] = useState("");
  const [downloadUrl, setDownloadUrl] = useState("");
  const [selectedGame, setSelectedGame] = useState(selectedGameId || null);
  const { isGlobalLoading, setIsGlobalLoading } = useContext(UIContext);
  // useEffect(() => {
  //   const handlePopState = (event) => {
  //     alert("⬅️ User clicked browser back or forward", event);
  //     // do your logic here (e.g. refetch balance, cleanup, etc.)
  //   };

  //   window.addEventListener("popstate", handlePopState);
  //   return () => window.removeEventListener("popstate", handlePopState);
  // }, []);

  useEffect(() => {
    if (!scrollRef.current || !selectedGame) return;

    const container = scrollRef.current;
    const item = container.querySelector(`[data-id='${selectedGame}']`);

    if (item) {
      item.scrollIntoView({
        behavior: "auto", // ✅ instant scroll (no animation)
        block: "center",
        inline: "center",
      });
    }
  }, [selectedGame, visibleGames]);

  // auto-restore on mount
  // useEffect(() => {
  //   if (selectedGameId) {
  //     setSelectedGame(selectedGameId);
  //   } else if (!selectedGame && visibleGames?.length > 0) {
  //     const firstId = visibleGames[0].game_id;
  //     setSelectedGame(firstId);
  //     setSelectedGameId(firstId);
  //   }
  // }, [selectedGameId, selectedGame, visibleGames, setSelectedGameId]);

  useEffect(() => {
    if (selectedGameId) {
      setSelectedGame(selectedGameId);
    }
    // else if (!selectedGame && visibleGames?.length > 0) {
    //   const firstId = visibleGames[0].game_id;
    //   setSelectedGame(firstId);
    //   setSelectedGameId(firstId);
    // }
  }, [selectedGameId, selectedGame, visibleGames, setSelectedGameId]);

  // ---------- MEMBER ----------
  useEffect(() => {
    if (!mounted) return;
    const member = getMemberInfo();
    setInfo(member || null);
  }, [mounted]);

  // ---------- QUERIES ----------
  const canQuery = mounted && gmemberId && info?.member_id;

  const {
    data: playerData,
    isLoading: playerLoading,
    refetch: refetchPlayer,
  } = useGetPlayerViewQuery(
    canQuery
      ? { gamemember_id: gmemberId, member_id: info.member_id }
      : skipToken,
    {
      skip: !canQuery || platformType !== "app", // ✅ skip when not ready OR not app
      refetchOnMountOrArgChange: false, // don't refetch every time component remounts
      refetchOnFocus: false, // don't auto refresh when tab becomes active
      refetchOnReconnect: false, // don't auto refresh when network reconnects
      keepUnusedDataFor: 3600, // ⬅️ cache for 1 hour
    },
  );

  const passwordQueryArg = useMemo(() => {
    if (!canQuery) return skipToken;
    return { gamemember_id: gmemberId, user_id: info?.member_id };
  }, [canQuery, gmemberId, info?.member_id]);

  const {
    data: passwordData,
    isLoading: isPasswordLoading,
    isFetching: isPasswordFetching,
    refetch: refetchPwd,
  } = useGetPlayerPasswordQuery(passwordQueryArg, {
    skip: !canQuery || platformType !== "app", // ✅ skip when not ready OR not app
    refetchOnMountOrArgChange: false,
    refetchOnFocus: false,
    refetchOnReconnect: false,
    keepUnusedDataFor: 3600, // ✅ cache for 1 hour
  });

  const {
    data: gameList,
    isLoading: isGameLoading,
    isFetching: isGameFetching,
    isError,
    refetch: isGameRefetch,
  } = useGetGameListQuery(
    {
      type: null,
      isBookmark: null,
      member_id: info?.member_id,
      gameplatform_id: gamePlatformId,
      provider_id: providerId,
    },
    {
      skip: !info?.member_id || platformType === "app",
      refetchOnMountOrArgChange: false,
      skip: !info?.member_id,
      refetchOnMountOrArgChange: false, // don't refetch every time component remounts
      refetchOnFocus: false, // don't auto refresh when tab becomes active
      refetchOnReconnect: false, // don't auto refresh when network reconnects
      keepUnusedDataFor: 3600, // ⬅️ cache for 1 hour
    },
  );

  // ✅ Only trigger query when category = "app"
  const {
    data: providerInfo,
    isLoading: isProviderLoading,
    isFetching: isProviderFetching,
    refetch: refetchProvider,
  } = useGetProviderViewQuery(
    platformType === "app" && info?.member_id && providerId
      ? { member_id: info.member_id, provider_id: providerId }
      : skipToken,
  );

  useEffect(() => {
    if (passwordData?.password) setPassword(passwordData.password);
  }, [passwordData]);
  useEffect(() => {
    if (playerData?.data?.loginId) {
      setUsername(playerData.data.loginId);
    }

    if (playerData?.deeplink) {
      const isIOS = /iPhone|iPad|iPod/i.test(navigator.userAgent);
      const link = isIOS
        ? playerData.deeplink.ios
        : playerData.deeplink.android; // default to android on web or other

      setDeepLink(link);
    }
  }, [playerData]);
  useEffect(() => {
    if (providerInfo?.data?.download) {
      setDownloadUrl(providerInfo.data.download);
    }
  }, [providerInfo?.data?.download]);

  useEffect(() => {
    const hasCache = !!sessionStorage.getItem("visibleGamesCache");

    setLoading(
      hasCache
        ? false // ✅ don’t show loading when we already have cache
        : isGameLoading || isGameFetching || isPasswordLoading,
    );
  }, [isGameLoading, isGameFetching, isPasswordLoading, setLoading]);

  useEffect(() => {
    // 🔹 Load cached data immediately on mount
    const cached = sessionStorage.getItem("visibleGamesCache");
    if (cached) {
      try {
        const parsed = JSON.parse(cached);
        setVisibleGames(parsed);
      } catch {
        console.warn("Invalid cache data, clearing...");
        sessionStorage.removeItem("visibleGamesCache");
      }
    }
  }, []);

  useEffect(() => {
    if (gameList?.data) {
      setVisibleGames(gameList.data); // update state
      sessionStorage.setItem(
        "visibleGamesCache",
        JSON.stringify(gameList.data),
      ); // cache it
    }
  }, [gameList]);

  const hasRun = useRef(false); // 👈 remember if already executed

  // useEffect(() => {
  //   if (hasRun.current) return; // prevent running twice
  //   if (!info?.member_id || !gmemberId) return; // wait until ready
  //   // setPoints(0);

  //   const doTransfer = async () => {
  //     try {
  //       setIsTransferring(true);
  //       setPoints(0);

  //       const resp = await transferAllCreditToPoint({
  //         member_id: info.member_id,
  //         gamemember_id: gmemberId,
  //         ip: await getClientIp(),
  //       }).unwrap();

  //       if (resp.status) {
  //         setCredits(Number(parseFloat(resp.member.balance)));
  //         setPoints(Number(parseFloat(resp.player.balance)));
  //       }
  //     } catch (err) {
  //       // console.error("transfer error:", err);
  //       // toast.error(t("transfer.failed"));
  //     } finally {
  //       setIsTransferring(false);
  //       markTransferDone?.();
  //     }
  //   };

  //   doTransfer();
  //   hasRun.current = true; // ✅ mark done so it won’t rerun
  // }, [info?.member_id, gmemberId]);

  // ---------- GAME URL ----------
  // useEffect(() => {
  //   if (!info?.member_id || !gmemberId || !selectedGame) return;

  //   const fetchUrl = async () => {
  //     try {
  //       const res = await openGameUrl({
  //         member_id: info.member_id,
  //         gamemember_id: gmemberId,
  //         game_id: selectedGame,
  //       }).unwrap();
  //       if (res?.data) {
  //         setGameUrl(res.data);
  //       }
  //     } catch (err) {
  //       console.error("Failed to get game URL:", err);
  //     }
  //   };
  //   fetchUrl();
  // }, [info, gmemberId, selectedGame, openGameUrl]);

  // ---------- MUTATIONS ----------

  // ---------- HANDLERS ----------
  const handleOpen = () => {
    if (isTransferring) {
      toast.error(t("transfer.processing"));
      return;
    }

    // ❗ No deep link → show error
    if (!deepLink) {
      toast.error(t("transfer.noDeepLink"));
      return;
    }

    try {
      // Try open the app
      window.location.href = deepLink;
    } catch (err) {
      // ❗ Browser won't throw, but just in case
      toast.error(t("transfer.noDeepLink"));
    }
  };
  const handleClick = () => {
    showConfirm({
      titleKey: "players.deleteConfirmTitle",
      messageKey: "players.deleteConfirmDesc",
      replaceKey: "PLAYER_ID_PLACEHOLDER", // user-defined placeholder
      replaceValue: username, // dynamic value
      confirmKey: "common.confirm",
      cancelKey: "common.cancel",
      onConfirm: async () => {
        try {
          setLoading(true);
          const ip = await getClientIp();
          await deletePlayer({
            gamemember_id: gmemberId,
            ip,
            member_id: info?.member_id,
          }).unwrap();
          toast.success(t("players.deleteSuccess"));
          balanceRef?.current?.handleRefresh?.();
          router.push("/");
        } catch (err) {
          const result = extractError(err);
          toast.error(result.message || t("common.error"));
        } finally {
          setLoading(false);
        }
      },
    });
  };

  const handleBackClick = async () => {
    // if (isTransferring) {
    //   toast.error(t("transfer.processing"));
    //   return;
    // }

    // balanceRef.current?.handleTransferAllPoints();
    router.back();
  };

  const handleOneClickTransfer = async () => {
    if (isTransferring) {
      toast.error(t("transfer.processing"));
      return;
    }

    balanceRef.current?.handleTransferAllPoints();
  };

  // ✅ Define the helper function before the return
  const showDownload = () => {
    // Only show if it's "app" and has download URL
    if (platformType !== "app" || !providerInfo?.data?.download) return null;

    return (
      <div className="px-4">
        {/* Logo */}
        {/* {mounted && providerInfo?.data?.icon && (
          <div className="flex justify-center">
            <Image
              src={`${process.env.NEXT_PUBLIC_IMAGE_URL}/${providerInfo?.data?.icon}`}
              width={100}
              height={100}
              className="h-20 w-20 object-contain p-2"
              alt="Game Icon"
            />
          </div>
        )} */}
        {/* Banner */}
        <div className="overflow-hidden rounded-xl">
          {mounted && providerInfo?.data?.icon_sm && (
            <Image
              src={`${process.env.NEXT_PUBLIC_IMAGE_URL}/${providerInfo?.data?.icon_sm}`}
              width={800}
              height={200}
              className="h-15 w-full object-contain"
              alt="Game Banner"
            />
          )}
        </div>

        {/* Title */}
        <h3 className="mt-4 text-sm font-semibold">
          {t("mega.subtitle")} {providerName}
        </h3>

        {/* 3 Steps */}
        <ol className="mt-3 space-y-3">
          <li className="flex items-start gap-3">
            <span
              className="grid h-6 w-6 place-items-center rounded-full text-sm font-semibold text-black"
              style={{
                background: "linear-gradient(180deg,#F8AF07 0%,#FFFC86 100%)",
              }}
            >
              1
            </span>
            <div>
              <p className="text-sm font-semibold">{t("mega.steps.1.title")}</p>
              <p className="text-[13px] text-white/70">
                {t("mega.steps.1.desc")}
              </p>
              <p className="text-[13px] text-[#FFC000]">
                <a
                  href={downloadUrl}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="text-sm font-semibold text-yellow-400 underline"
                >
                  {t("common.downloadUrl")}
                </a>
              </p>
            </div>
          </li>

          <li className="flex items-start gap-3">
            <span
              className="grid h-6 w-6 place-items-center rounded-full text-sm font-semibold text-black"
              style={{
                background: "linear-gradient(180deg,#F8AF07 0%,#FFFC86 100%)",
              }}
            >
              2
            </span>
            <div>
              <p className="text-sm font-semibold">{t("mega.steps.2.title")}</p>
              <p className="text-[13px] text-white/70">
                {t("mega.steps.2.desc")}
              </p>
            </div>
          </li>

          <li className="flex items-start gap-3">
            <span
              className="grid h-6 w-6 place-items-center rounded-full text-sm font-semibold text-black"
              style={{
                background: "linear-gradient(180deg,#F8AF07 0%,#FFFC86 100%)",
              }}
            >
              3
            </span>
            <div>
              <p className="text-sm font-semibold">{t("mega.steps.3.title")}</p>
              <p className="text-[13px] text-white/70">
                {t("mega.steps.3.desc")}
              </p>
            </div>
          </li>
        </ol>
      </div>
    );
  };

  // const gamelist = () => {
  //   if (
  //     !isGameLoading &&
  //     !isGameFetching &&
  //     (!visibleGames || visibleGames.length === 0)
  //   ) {
  //     return (
  //       <div className="flex flex-col items-center justify-center py-12 text-white/70">
  //         <Image
  //           src={IMAGES.empty}
  //           alt="empty"
  //           width={120}
  //           height={120}
  //           className="mb-3 object-contain"
  //         />
  //         <p className="text-sm">{t("common.noRecords")}</p>
  //       </div>
  //     );
  //   }

  //   // 🔁 Create alternating rows: 4 items, then 3, then 4, etc.
  //   const rows = [];
  //   let start = 0;
  //   let toggle = true; // true = 4 items, false = 3 items

  //   while (start < visibleGames.length) {
  //     const count = toggle ? 3 : 3;
  //     rows.push(visibleGames.slice(start, start + count));
  //     start += count;
  //     toggle = !toggle;
  //   }

  //   return (
  //     <div
  //       ref={scrollRef}
  //       className="flex-1 min-h-0 overflow-y-auto mb-30 px-4"
  //       onWheel={(e) => {
  //         const target = e.currentTarget;
  //         const isAtTop = target.scrollTop === 0;
  //         const isAtBottom =
  //           Math.ceil(target.scrollTop + target.clientHeight) >=
  //           target.scrollHeight;

  //         if ((isAtTop && e.deltaY < 0) || (isAtBottom && e.deltaY > 0)) {
  //           e.stopPropagation();
  //         }
  //       }}
  //     >
  //       <h2 className="text-lg font-semibold text-white py-2">
  //         {providerName} {t("mega.gameSeriesList")}
  //       </h2>

  //       <div className="space-y-4">
  //         {rows.map((row, i) => (
  //           <div
  //             key={i}
  //             className={`grid ${
  //               i % 2 === 0 ? "grid-cols-4" : "grid-cols-3"
  //             } gap-4`}
  //           >
  //             {row.map((g) => (
  //               <div key={g.game_id} data-id={g.game_id}>
  //                 <GameTile
  //                   isBookMark={false}
  //                   src={
  //                     g.icon
  //                       ? g.icon === "assets/img/game/WL.webp"
  //                         ? IMAGES.home.rightbar.game2
  //                         : isFullHttpUrl(g.icon)
  //                         ? g.icon
  //                         : `${process.env.NEXT_PUBLIC_IMAGE_URL}/${g.icon}`
  //                       : IMAGES.home.rightbar.game2
  //                   }
  //                   title=""
  //                   selected={selectedGame === g.game_id}
  //                   onClick={() => {
  //                     setSelectedGame(g.game_id);
  //                     setSelectedGameId(g.game_id);
  //                   }}
  //                 />
  //               </div>
  //             ))}
  //           </div>
  //         ))}
  //       </div>
  //     </div>
  //   );
  // };

  const gamelist = () => {
    // 2 rows x 4 columns

    if (
      !isGameLoading &&
      !isGameFetching &&
      (!visibleGames || visibleGames.length === 0)
    ) {
      return (
        <div className="flex flex-col items-center justify-center py-12 text-white/70">
          <Image
            src={IMAGES.empty}
            alt="empty"
            width={120}
            height={120}
            className="mb-3 object-contain"
          />
          <p className="text-sm">{t("common.noRecords")}</p>
        </div>
      );
    }

    return (
      <div
        ref={scrollRef}
        className="flex-1 min-h-0 overflow-y-auto mb-30 px-2"
        onWheel={(e) => {
          const target = e.currentTarget;
          const isAtTop = target.scrollTop === 0;
          const isAtBottom =
            Math.ceil(target.scrollTop + target.clientHeight) >=
            target.scrollHeight;

          if ((isAtTop && e.deltaY < 0) || (isAtBottom && e.deltaY > 0)) {
            e.stopPropagation();
          }
        }}
      >
        <div className="py-2 px-2 ml-2">
          {providerInfo?.data?.icon_sm ? (
            <>
              <Image
                src={`${process.env.NEXT_PUBLIC_IMAGE_URL}/${providerInfo.data.icon_sm}`}
                width={800}
                height={200}
                className="h-15 w-full object-contain"
                alt="Game Banner"
              />
              <div className="text-lg font-semibold text-white">
                {t("mega.gameSeriesList")}
              </div>
            </>
          ) : providerIcon ? (
            <div className="flex items-center gap-3">
              <img
                src={`${process.env.NEXT_PUBLIC_IMAGE_URL}/${providerIcon}`}
                alt={providerName}
                className="h-8 w-auto"
              />
              <span className="text-lg font-semibold text-white">
                {t("mega.gameSeriesList")}
              </span>
            </div>
          ) : (
            <h2 className="text-lg font-semibold text-white">
              {providerName} {t("mega.gameSeriesList")}
            </h2>
          )}
        </div>

        <div className="grid grid-cols-3 gap-2">
          {visibleGames.map((g) => (
            <div key={g.game_id} data-id={g.game_id}>
              <GameTileDetail
                type="G"
                isBookMark={false}
                src={
                  g.icon
                    ? g.icon === "assets/img/game/WL.webp"
                      ? IMAGES.home.rightbar.game
                      : isFullHttpUrl(g.icon)
                        ? g.icon
                        : `${process.env.NEXT_PUBLIC_IMAGE_URL}/${g.icon}`
                    : IMAGES.home.rightbar.game
                }
                alt={g.game_name}
                // title={g.game_name}
                title={g.game_name}
                selected={selectedGame === g.game_id}
                onClick={() => {
                  if (isTransferring) {
                    toast.error(t("transfer.processing"));
                    return;
                  }

                  setSelectedGame(g.game_id);
                  setSelectedGameId(g.game_id);
                  const payload = encryptObject({
                    gamemember_id: gmemberId,
                    provider_id: providerId,
                    member_id: info?.member_id,
                    game_id: g.game_id, // still encoded safely in object
                  });

                  const redirectUrl = `/game-detail-redirect?payload=${encodeURIComponent(
                    payload,
                  )}`;

                  // popup-friendly
                  window.open(redirectUrl, "_blank");
                }}
              />
            </div>
          ))}
        </div>
      </div>
    );
  };

  const openGame = async (gameId) => {
    setLoading(true);

    try {
      let gameUrl = "";

      if (platformType !== "app") {
        // ✅ Step 1 — build internal redirect URL
        const redirectUrl =
          `/game-detail-redirect?` +
          `gamemember_id=${gmemberId}` +
          `&member_id=${info?.member_id}` +
          `&game_id=${encodeURIComponent(gameId)}`;

        // ✅ Step 2 — open immediately (popup-friendly)
        const newTab = window.open(redirectUrl, "_blank");
      }
    } catch (err) {
      console.error("openGame error:", err);
      toast.error(t("common.somethingWentWrong"));
    } finally {
      setLoading(false);
    }
  };

  // const openGame = async (gameId) => {
  //   let gameUrl = "";
  //   setLoading(true);

  //   try {
  //     if (platformType !== "app") {
  //       const res = await openGameUrl({
  //         member_id: info.member_id,
  //         gamemember_id: gmemberId,
  //         game_id: gameId,
  //       }).unwrap();

  //       gameUrl = res?.data ?? res?.Data;

  //       if (!gameUrl) {
  //         toast.error(t("wallets.cannotLoadGame"));

  //         // 🔄 return all points back to credit
  //         await handleTransferAllPointsToCredit({
  //           info,
  //           gameMemberId,
  //           transferAllPointToCredit,
  //           setIsGlobalLoading: setLoading,
  //         });

  //         return; // stop here
  //       }
  //     } else {
  //       gameUrl = url;
  //     }

  //     sessionStorage.setItem("redirectUrl", gameUrl);
  //     router.push("/redirect");
  //   } catch (err) {
  //     console.error("openGame error:", err);
  //     toast.error(t("common.somethingWentWrong") || "Something went wrong");
  //   } finally {
  //     setLoading(false); // ✅ always stop loader
  //   }
  // };

  // ---------- RENDER ----------
  return (
    <>
      <div className="relative mx-auto max-w-[480px] min-h-dvh bg-[#00143D] text-white overflow-hidden">
        {/* ===== Scrollable content (give space for fixed footer) ===== */}
        <div className="h-dvh overflow-y-auto pb-28">
          {/* ===== Banner ===== */}
          <section
            className={`relative w-full overflow-hidden ${
              platformType === "app" ? "h-[160px]" : "h-[110px]"
            }`}
          >
            <Image
              src={
                providerBanner
                  ? `${process.env.NEXT_PUBLIC_IMAGE_URL}/${providerBanner}`
                  : IMAGES.games.banner2
              }
              alt="banner"
              fill
              className="object-cover object-center"
              priority
              sizes="(min-width: 480px) 480px, 100vw"
            />
            <div className="absolute inset-0" />

            <div className="absolute top-1 left-2 right-2 z-10">
              <div className="relative overflow-hidden rounded-[18px]  px-4 py-3 ">
                <div className="relative flex items-center gap-2">
                  <button
                    onClick={handleBackClick}
                    className="cursor-pointer active:scale-95"
                  >
                    <Image
                      src={IMAGES.arrowLeft}
                      alt="back"
                      width={22}
                      height={22}
                      className="object-contain"
                    />
                  </button>
                  <h1 className="text-lg font-semibold tracking-wide">
                    {providerName}
                  </h1>
                </div>

                <div className="relative mt-3 flex items-center justify-between gap-3">
                  <div className="min-w-0 flex items-baseline gap-2 leading-none">
                    <div className="text-[11px] whitespace-nowrap text-white/80">
                      {t("wallets.balance")}
                    </div>
                    <span className="text-xs font-semibold bg-[linear-gradient(180deg,#F8AF07_0%,#FFFC86_100%)] bg-clip-text text-transparent">
                      RM
                    </span>
                    <span className="text-[25px] font-extrabold tabular-nums bg-[linear-gradient(180deg,#F8AF07_0%,#FFFC86_100%)] bg-clip-text text-transparent sm:text-[25px]">
                      {credits.toFixed(2)}
                    </span>
                  </div>

                  <div className="shrink-0 rounded-full bg-[linear-gradient(180deg,#F8AF07_0%,#FFFC86_100%)] p-[1px]">
                    <button
                      onClick={handleOneClickTransfer}
                      disabled={isGlobalLoading || isTransferring}
                      className="flex h-8 items-center gap-1.5 rounded-full bg-[#101563] px-3"
                    >
                      <img
                        src={IMAGES.home.profile.currency}
                        alt="restore wallet"
                        className={`h-4 w-4 object-contain shrink-0 ${
                          isGlobalLoading || isTransferring
                            ? "animate-[spin_0.6s_linear_infinite]"
                            : ""
                        }`}
                      />
                      <span className="text-xs font-semibold whitespace-nowrap bg-[linear-gradient(180deg,#F8AF07_0%,#FFFC86_100%)] bg-clip-text text-transparent">
                        {t("transfer.oneClick")}
                      </span>
                    </button>
                  </div>
                </div>
              </div>

              <div className="hidden">
                <PointBalanceBar
                  autoTransfer={false}
                  isPreload={false}
                  ref={balanceRef}
                  gameMemberId={gmemberId}
                />
              </div>
            </div>
          </section>

          {/* ===== Floating credentials card ===== */}
          {platformType === "app" && (
            <div className="relative top-[-40px] z-10 w-full px-4">
              <div className="rounded-2xl border border-[#F8AF07] bg-[#0B245C]/95 backdrop-blur-sm p-4 shadow-lg">
                <div className="mb-4 flex items-center justify-between">
                  <h3 className="text-sm font-semibold">
                    {t("players.credentials")}
                  </h3>
                  <button
                    onClick={handleClick}
                    className="flex items-center gap-1 text-sm text-[#FF7A7A] active:scale-95"
                  >
                    <Image
                      src={IMAGES.redtrash}
                      alt="delete"
                      width={16}
                      height={16}
                    />
                    {t("players.delete")}
                  </button>
                </div>

                {/* Username */}
                <div className="mb-3 flex items-center gap-2 rounded-full border border-white/40 px-3 py-2">
                  <span className="w-16 text-sm">
                    {t("mega.credentials.username")}
                  </span>
                  <input
                    readOnly
                    value={username}
                    className="flex-1 bg-transparent text-sm outline-none"
                  />
                  <button
                    type="button"
                    onClick={() => {
                      navigator.clipboard.writeText(username);
                      toast.success(t("common.copySuccess"));
                    }}
                    className="grid h-8 w-8 place-items-center rounded-full bg-white/10"
                  >
                    <Image
                      src={IMAGES.iconCopy}
                      alt="copy"
                      width={16}
                      height={16}
                    />
                  </button>
                </div>

                {/* Password */}
                <div className="flex items-center gap-2 rounded-full border border-white/40 px-3 py-2">
                  <span className="w-16 text-sm">
                    {t("mega.credentials.password")}
                  </span>
                  <input
                    readOnly
                    value={password}
                    className="flex-1 bg-transparent text-sm outline-none"
                  />
                  <button
                    type="button"
                    onClick={() => {
                      navigator.clipboard.writeText(password);
                      toast.success(t("common.copySuccess"));
                    }}
                    className="grid h-8 w-8 place-items-center rounded-full bg-white/10"
                  >
                    <Image
                      src={IMAGES.iconCopy}
                      alt="copy"
                      width={16}
                      height={16}
                    />
                  </button>
                </div>
              </div>
            </div>
          )}

          {platformType === "app" ? showDownload() : gamelist()}
          {/* ===== Content below banner ===== */}
          {/* {gameViewData?.downloadurl && (
          <div className="px-4">
            {mounted && gameViewData?.game?.icon && (
              <Image
                src={`${process.env.NEXT_PUBLIC_IMAGE_URL}/${gameViewData?.game?.icon}`}
                width={100}
                height={100}
                className="h-20 w-20 object-cover p-2"
                alt="Game Icon"
              />
            )}

            <div className="overflow-hidden rounded-xl">
              {mounted && gameViewData?.game?.banner && (
                <Image
                  src={`${process.env.NEXT_PUBLIC_IMAGE_URL}/${gameViewData?.game?.banner}`}
                  width={800}
                  height={200}
                  className="h-28 w-full object-cover"
                  alt="Game Banner"
                />
              )}
            </div>

            <h3 className="mt-4 text-sm font-semibold">
              {t("mega.subtitle")}{" "}
              {mounted && playerData?.data?.game?.game_name
                ? playerData.data.game.game_name
                : ""}
            </h3>

            <ol className="mt-3 space-y-3">
              {[1, 2, 3].map((num) => (
                <li key={num} className="flex items-start gap-3">
                  <span
                    className="grid h-6 w-6 place-items-center rounded-full text-sm font-semibold text-black"
                    style={{
                      background:
                        "linear-gradient(180deg,#F8AF07 0%,#FFFC86 100%)",
                    }}
                  >
                    {num}
                  </span>
                  <div>
                    <p className="text-sm font-semibold">
                      {t(`mega.steps.${num}.title`)}
                    </p>
                    <p className="text-[13px] text-white/70">
                      {t(`mega.steps.${num}.desc`)}
                    </p>
                    {num === 1 && (
                      <p className="text-[13px] text-[#FFC000]">
                        <a
                          href={gameViewData.downloadurl}
                          target="_blank"
                          rel="noopener noreferrer"
                          className="text-sm font-semibold text-yellow-400 underline"
                        >
                          {t("Common.downloadUrl")}
                        </a>
                      </p>
                    )}
                  </div>
                </li>
              ))}
            </ol>
          </div>
        )} */}
        </div>

        {/* ===== Fixed bottom bar (INSIDE wrapper; always sticks) ===== */}
        {platformType === "app" && (
          <div className="fixed bottom-0 left-1/2 z-50 w-full max-w-[480px] -translate-x-1/2 border-t border-white/10 bg-[#00143D]/95 px-4 pb-6 pt-3 backdrop-blur-md">
            <div className="flex items-center gap-3">
              <button
                type="button"
                onClick={() => handleOpen()}
                // onClick={() =>
                //   providerInfo?.data?.download
                //     ? handleOpen(providerInfo.data.download)
                //     : openGame(selectedGame)
                // }
                className="flex-1 rounded-full py-3 text-sm font-semibold text-[#00143D] active:scale-95"
                style={{
                  background: "linear-gradient(90deg,#27E16D 0%,#16C3A1 100%)",
                }}
              >
                {t("wallets.openGame")}
              </button>

              <button
                type="button"
                onClick={() => router.push("/topup")}
                className="flex-1 rounded-full border border-[#FFC000] py-3 text-sm font-semibold text-[#FFC000]"
              >
                {t("wallets.topup")}
              </button>

              <button
                onClick={() => router.push(`/transfer/${gmemberId}`)}
                type="button"
                className="flex-1 rounded-full border border-[#FFC000] py-3 text-sm font-semibold text-[#FFC000]"
              >
                {t("wallets.transfer")}
              </button>
            </div>
          </div>
        )}
      </div>
    </>
  );
}
