"use client";

import { useContext, useEffect, useRef, useState } from "react";
import { useRouter } from "next/navigation";
import { toast } from "react-hot-toast";
import { useTranslations } from "next-intl";
import { extractError, getClientIp, getMemberInfo } from "@/utils/utility";

import { useOpenGameUrlMutation } from "@/services/gameApi";
import { useProviderStore } from "@/store/zustand/providerStore";
import { UIContext } from "@/contexts/UIProvider";
import { decryptObject } from "@/utils/encryption";
import { useBalanceStore } from "@/store/zustand/balanceStore";
import { useTransferAllCreditToPointMutation } from "@/services/transactionApi";

export default function GameRedirectPage() {
  const t = useTranslations();
  const router = useRouter();
  const hasRun = useRef(false);
  const [error, setError] = useState(null);
  const [transferAllCreditToPoint] = useTransferAllCreditToPointMutation();
  const query =
    typeof window !== "undefined"
      ? new URLSearchParams(window.location.search)
      : null;

  const encrypted = query?.get("payload");

  let decrypted = null;

  if (encrypted) {
    try {
      decrypted = decryptObject(encrypted);
    } catch (err) {
      console.error("Decrypt failed:", err);
    }
  }

  const memberId = decrypted?.member_id || null;
  const gameMemberId = decrypted?.gamemember_id || null;
  const gameId = decrypted?.game_id || null;
  const providerId = decrypted?.provider_id || null;
  const [openGameUrl] = useOpenGameUrlMutation();
  const { prevGameMemberId } = useProviderStore();
  const { setLoading } = useContext(UIContext);
  const info = getMemberInfo();

  const {
    reset: resetProvider,
    setSelectedProvider,
    clearPrevGameMemberId,
    setPrevGameMemberId,
  } = useProviderStore.getState();

  const {
    points,
    credits,
    setCredits,
    setPoints,
    isTransferring,
    setIsTransferring,
    markTransferDone,
  } = useBalanceStore.getState();

  useEffect(() => {
    if (hasRun.current) return;
    hasRun.current = true;

    if (!memberId || !gameId) {
      setError(t("wallets.cannotLoadGame"));
      return;
    }

    (async () => {
      try {
        setLoading(true);

        // ================================
        // 🟡 1. If previous transfer is still running → SKIP transfer, open game
        // ================================
        if (isTransferring) {
          const res = await openGameUrl({
            member_id: info.member_id || memberId,
            gamemember_id: gameMemberId,
            provider_id: providerId,
            game_id: gameId,
          }).unwrap();

          const gameUrl = res?.data ?? res?.Data ?? res?.url;

          if (!gameUrl) {
            setError(t("wallets.cannotLoadGame"));
            return;
          }
          const playerPoint = res?.player_point;
          const parsedPlayerPoint =
            playerPoint == null ? Number.NaN : Number.parseFloat(playerPoint);

          if (!Number.isNaN(parsedPlayerPoint)) {
            setCredits(parsedPlayerPoint);
          }

          //   router.push(`/iframe?url=${encodeURIComponent(gameUrl)}`);

          window.location.replace(gameUrl);
          return;
        }

        // ================================
        // 🟢 2. If points == 0 → try transfer first
        // ================================
        // if (points === 0) {
        //   try {
        //     setIsTransferring(true);

        //     const resp = await transferAllCreditToPoint({
        //       member_id: info.member_id,
        //       gamemember_id: gameMemberId,
        //       ip: await getClientIp(),
        //     }).unwrap();

        //     //  clearPrevGameMemberId();

        //     if (resp.status) {
        //       setPoints(Number(parseFloat(resp.player.balance)));
        //     }
        //   } catch (err) {
        //     // ❗ IMPORTANT:
        //     // Transfer failed but do NOT block user.
        //     // We continue to open game anyway.
        //     console.log(
        //       "Transfer failed, continue silently",
        //       extractError(err)
        //     );
        //   } finally {
        //     setIsTransferring(false);
        //     markTransferDone?.();
        //   }
        // }

        // ================================
        // 🟦 3. Open game ALWAYS (after transfer or skip)
        // ================================
        const res = await openGameUrl({
          member_id: info.member_id || memberId,
          gamemember_id: gameMemberId,
          provider_id: providerId,
          game_id: gameId,
        }).unwrap();

        const gameUrl = res?.data ?? res?.Data ?? res?.url;

        if (!gameUrl) {
          setError(t("wallets.cannotLoadGame"));
          return;
        }
        // alert(JSON.stringify(res));
        const playerPoint = res?.player_point;
        const parsedPlayerPoint =
          playerPoint == null ? Number.NaN : Number.parseFloat(playerPoint);

        if (!Number.isNaN(parsedPlayerPoint)) {
          // alert(parsedPlayerPoint);
          setCredits(parsedPlayerPoint);
        }

        window.location.replace(gameUrl);
      } catch (err) {
        const extracted = extractError(err);
        setError(extracted.message || t("common.somethingWentWrong"));
      } finally {
        setLoading(false);
      }
    })();
  }, []);

  if (error) {
    return (
      <main className="flex h-dvh items-center justify-center bg-[#00143D] text-white">
        <div className="text-center space-y-4">
          {/* REAL ERROR */}
          {/* REAL ERROR */}
          <p
            className="text-red-400 text-center block w-full"
            dangerouslySetInnerHTML={{
              __html: String(error || "").replace(/\n/g, "<br/>"),
            }}
          />

          {/* SUBTITLE */}
          <p className="text-sm text-white/70">{t("errorScreen.subtitle")}</p>
        </div>
      </main>
    );
  }
  return (
    <main className="flex h-dvh items-center justify-center bg-[#00143D] text-white">
      <div className="text-center space-y-2">
        <div className="h-8 w-8 mx-auto animate-spin rounded-full border-2 border-white/30 border-t-yellow-400" />
        <p className="text-sm">{t("wallets.loadingGame")}</p>
      </div>
    </main>
  );
}
