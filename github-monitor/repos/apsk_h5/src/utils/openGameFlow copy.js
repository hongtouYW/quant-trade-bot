"use client";

import { toast } from "react-hot-toast";
import { extractError, getClientIp } from "@/utils/utility";
import { handleTransferAllPointsToCredit } from "@/utils/TransferUti";
import { useBalanceStore } from "@/store/zustand/balanceStore";
import { useProviderStore } from "@/store/zustand/providerStore";
import { useGameStore } from "@/store/zustand/gameStore";
import { isIframeLoadGame } from "@/constants/cookies";

export async function openGameFlow({
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
  t,
}) {
  const {
    credits,
    setCredits,
    setPoints,
    setIsTransferring,
    markTransferDone,
    isTransferring, // ⭐ get current flag
  } = useBalanceStore.getState();

  const {
    reset: resetProvider,
    setSelectedProvider,
    clearPrevGameMemberId,
    setPrevProviderId,
    prevProviderId,
    clearPrevProviderId,
    setPrevGameMemberId,
    prevGameMemberId, // ⭐ use from store, not param
  } = useProviderStore.getState();
  const { reset: resetGame } = useGameStore.getState();

  // ⭐ Guard: prevent double flow
  if (isTransferring) {
    // optional toast
    // toast.error(t("transfer.processing"));
    return { success: false, message: t?.("transfer.processing") };
  }
  setIsTransferring(true);
  let gameMemberId = null;
  let updateCredit = credits;

  //use for web lobby only, if other type wont do transfer here
  const isSameProvider =
    prevProviderId !== null && prevProviderId === providerId;

  try {
    setLoading(true);

    // 🧩 Step 0 — restore previous provider credit if any
    // if (
    //   prevProviderId && // must have previous provider
    //   prevProviderId !== providerId && // must be switching provider
    //   prevGameMemberId // ⭐ from store
    // ) {
    //   try {
    //     // setIsTransferring(true);
    //     const resp = await transferAllPointToCredit({
    //       gamemember_id: prevGameMemberId, // ⭐ correct source
    //       member_id: info.member_id,
    //       ip: await getClientIp(),
    //     }).unwrap();

    //     updateCredit = Number(resp.member.balance);
    //     await new Promise((r) => setTimeout(r, 0));
    //     setCredits(updateCredit);
    //     setPoints(Number(resp.player.balance));
    //     clearPrevGameMemberId();
    //     clearPrevProviderId();
    //   } catch (err) {
    //     if (platformType === "web-lobby") {
    //       const extracted = extractError(err);
    //       console.log(extracted?.message);
    //     }
    //   } finally {
    //     // setIsTransferring(false);
    //     // markTransferDone?.();
    //   }
    // }

    // const ip = await getClientIp();
    // const resp = await transferOut({
    //   member_id: info.member_id,
    //   ip,
    // }).unwrap();

    // if (resp?.status) {
    //   if (resp?.member?.balance != null) {
    //     setCredits(Number(resp.member.balance));
    //   }

    //   setPoints(0);
    // }

    clearPrevProviderId();
    clearPrevGameMemberId();

    // ⭐ Only after restore, we can mark this as new prevProvider
    setPrevProviderId(providerId);

    // // 🧩 Step 1 — fetch player list
    // const playerData = await triggerGetPlayerList({
    //   provider_id: providerId,
    //   member_id: info.member_id,
    // }).unwrap();

    // // ========================
    // // 🧩 Step 2A — existing player
    // // ========================
    // if (playerData?.data?.length > 0) {
    //   const gameMember = playerData.data[0];
    //   gameMemberId = gameMember.gamemember_id;

    //   if (platformType === "web-lobby" && !isSameProvider) {
    //     try {
    //       // setIsTransferring(true);

    //       const resp = await transferAllCreditToPoint({
    //         member_id: info.member_id,
    //         gamemember_id: gameMemberId,
    //         ip: await getClientIp(),
    //       }).unwrap();

    //       if (resp.status) {
    //         setPoints(Number(parseFloat(resp.player.balance)));
    //       }
    //     } catch (err) {
    //       const extracted = extractError(err);
    //       console.log(extracted?.message);
    //     } finally {
    //       // setIsTransferring(false);
    //       // markTransferDone?.();
    //     }
    //   }

    //   resetProvider();
    //   resetGame();
    //   setSelectedProvider({
    //     gamemember_id: gameMemberId,
    //     providerId,
    //     gamePid: gamePlatformId,
    //     providerName,
    //     providerCategory,
    //     platformType,
    //     providerIcon: providerIcon,
    //   });

    //   // ⭐ save the REAL one
    //   setPrevGameMemberId(gameMemberId);

    //   const openGameResult = await handleGameOpen({
    //     info,
    //     gameMemberId,
    //     platformType,
    //     openGameUrl,
    //     transferAllPointToCredit,
    //     setLoading,
    //     router,
    //     t,
    //     providerName,
    //     providerIcon,
    //   });

    //   if (!openGameResult?.success) {
    //     // setIsTransferring(false);
    //     // markTransferDone?.();

    //     return {
    //       success: false,
    //       message: openGameResult.message || "发生意外错误，请重试。",
    //     };
    //   }

    //   // setIsTransferring(false);
    //   // markTransferDone?.();
    //   return { success: true, message: openGameResult?.message };
    // }

    // // ========================
    // // 🧩 Step 2B — create player
    // // ========================
    // const result = await addPlayer({
    //   provider_id: providerId,
    //   member_id: info.member_id,
    // }).unwrap();

    // if (!result?.status) {
    //   toast.error(result?.message || t("transfer.createPlayerFailed"));
    //   return { success: false };
    // }

    // gameMemberId = result.data.gamemember_id;

    // // 🧩 Step 3 — transfer if credit > 0
    // if (!isSameProvider) {
    //   try {
    //     // setIsTransferring(true);
    //     const resp = await transferAllCreditToPoint({
    //       member_id: info.member_id,
    //       gamemember_id: gameMemberId,
    //       ip: await getClientIp(),
    //     }).unwrap();

    //     if (resp.status) {
    //       setPoints(Number(parseFloat(resp.player.balance)));
    //     }
    //   } catch (err) {
    //     const extracted = extractError(err);
    //     console.log(extracted);
    //   } finally {
    //     // setIsTransferring(false);
    //     // markTransferDone?.();
    //   }
    // }

    // // 🧩 Step 4 — Zustand provider setup
    // resetProvider();
    // resetGame();
    // setSelectedProvider({
    //   gamemember_id: gameMemberId,
    //   providerId,
    //   gamePid: gamePlatformId,
    //   providerName,
    //   loginId: result?.data?.loginId,
    //   providerCategory,
    //   platformType,
    //   providerIcon,
    // });

    // // ⭐ now safe to save
    // setPrevGameMemberId(gameMemberId);

    // // 🧩 Step 5 — open or redirect
    // const openGameResult = await handleGameOpen({
    //   info,
    //   gameMemberId,
    //   platformType,
    //   openGameUrl,
    //   transferAllPointToCredit,
    //   setLoading,
    //   router,
    //   t,
    //   providerName,
    //   providerIcon,
    // });

    // if (!openGameResult?.success) {
    //   return {
    //     success: false,
    //     message: openGameResult.message || "发生意外错误，请重试。",
    //   };
    // }

    // // setIsTransferring(false);
    // // markTransferDone?.();
    // return { success: true, message: openGameResult?.message };

    let gameMemberId = null;

    // ========================
    // 🧩 Step 1 — combined API (list/add/reload/login)
    // ========================
    let fastResult;
    try {
      fastResult = await fastOpenGame({
        member_id: info.member_id,
        provider_id: providerId,
      }).unwrap();
    } catch (err) {
      const extracted = extractError(err);
      return {
        success: false,
        message: extracted?.message || "发生意外错误，请重试。",
      };
    }

    if (!fastResult?.status) {
      return {
        success: false,
        message: fastResult?.message || "发生意外错误，请重试。",
      };
    }

    // keep your original structure
    const playerData = {
      data: fastResult.player ? [fastResult.player] : [],
    };

    // ========================
    // 🧩 Step 2 — player exist (combined API always returns player on success)
    // ========================

    if (playerData?.data?.length > 0) {
      const gameMember = playerData.data[0];
      gameMemberId = gameMember.gamemember_id;

      if (gameMember.has_balance) {
        setPoints(Number(parseFloat(gameMember.balance)));
      }

      if (platformType === "web-lobby" || platformType === "app") {
        setCredits(Number(parseFloat(fastResult.player_point)));
      } else {
        setCredits(Number(parseFloat(fastResult.member_credit)));
      }

      resetProvider();
      resetGame();

      setSelectedProvider({
        gamemember_id: gameMemberId,
        providerId,
        gamePid: gamePlatformId,
        providerName,
        providerCategory,
        platformType,
        providerIcon,
        providerBanner,
        loginId: gameMember.loginId,
      });

      setPrevGameMemberId(gameMemberId);

      // ========================
      // 🧩 Step 4 — open game (INLINE, no handleGameOpen, no second API)
      // ========================
      if (platformType === "web-lobby") {
        const gameUrl = fastResult?.url; // ✅ from combined API

        if (!gameUrl) {
          return {
            success: false,
            message: "发生意外错误，请重试",
          };
        }

        const finalUrl = `&&url=${encodeURIComponent(
          gameUrl,
        )}&gamemember_id=${gameMemberId}&&providerName=${providerName}&&platformType=${platformType}&&providerIcon=${providerIcon}`;

        if (!isIframeLoadGame) {
          window.location.replace(gameUrl);
        }
        // router.push(`/iframe?url=${encodeURIComponent(gameUrl)}`);

        return {
          success: true,
          message: finalUrl,
        };
      } else {
        router.push("/game-start");
        return {
          success: true,
          message: "",
        };
      }
    }
  } catch (err) {
    const extracted = extractError(err);

    return {
      success: false,
      message: extracted?.message || "发生意外错误，请重试。",
      data: err,
    };
  } finally {
    setLoading(false);
    setIsTransferring(false);
    markTransferDone?.();
  }
}

/**
 * Helper — Step 6: Open Game / Redirect
 */
async function handleGameOpen({
  info, // 👈 expects info
  gameMemberId,
  platformType,
  openGameUrl,
  transferAllPointToCredit,
  setLoading,
  router,
  t,
  providerName,
  providerIcon,
}) {
  if (platformType === "web-lobby") {
    try {
      const res = await openGameUrl({
        member_id: info.member_id,
        gamemember_id: gameMemberId,
      }).unwrap();

      const gameUrl = res?.data ?? res?.Data;
      if (!gameUrl) {
        return {
          success: false,
          message: "发生意外错误，请重试",
        };
      }
      const finalUrl = `&&url=${encodeURIComponent(
        gameUrl,
      )}&gamemember_id=${gameMemberId}&&providerName=${providerName}&&platformType=${platformType}&&providerIcon=${providerIcon}`;

      if (!isIframeLoadGame) {
        window.location.replace(gameUrl);
      }
      //  router.push(`/iframe?url=${encodeURIComponent(gameUrl)}`);

      return {
        success: true,
        message: finalUrl,
      };
      //   sessionStorage.setItem("redirectUrl", gameUrl);
      //   router.push("/redirect");
    } catch (err) {
      const extracted = extractError(err); // 👈 use your extract function

      return {
        success: false,
        message: extracted?.message || "发生意外错误，请重试。",
      };
    }
  } else {
    router.push("/game-start");

    return {
      success: true,
      message: "",
    };
  }
}
