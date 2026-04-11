"use client";

import { useContext, useEffect, useRef, useState } from "react";
import { useRouter } from "next/navigation";
import { toast } from "react-hot-toast";
import { useTranslations } from "next-intl";
import { openGameFlow } from "@/utils/openGameFlow";
import { getMemberInfo } from "@/utils/utility";

import {
  useAddPlayerMutation,
  useFastOpenGameMutation,
  useLazyGetPlayerListQuery,
} from "@/services/authApi";
import {
  useTransferAllCreditToPointMutation,
  useTransferAllPointToCreditMutation,
  useTransferOutMutation,
} from "@/services/transactionApi";
import { useOpenGameUrlMutation } from "@/services/gameApi";
import { useProviderStore } from "@/store/zustand/providerStore";
import { UIContext } from "@/contexts/UIProvider";
import { decryptObject } from "@/utils/encryption";

export default function GameRedirectPage() {
  const t = useTranslations();
  const router = useRouter();
  const hasRun = useRef(false);
  const [error, setError] = useState(null);

  // GET ENCRYPTED PAYLOAD
  const query =
    typeof window !== "undefined"
      ? new URLSearchParams(window.location.search)
      : null;

  let providerId = null;
  let memberId = null;
  let providerName = null;
  let providerIcon = null;
  let providerBanner = null;
  let providerCategory = null;
  let gamePlatformId = null;
  let platformType = null;

  // READ PAYLOAD
  const cipher = query?.get("payload");

  if (cipher) {
    try {
      const data = decryptObject(cipher);

      providerId = data.provider_id;
      memberId = data.member_id;
      providerName = data.provider_name;
      providerIcon = data.providerIcon;
      providerBanner = data.providerBanner;
      providerCategory = data.provider_category;
      gamePlatformId = data.gameplatform_id;
      platformType = data.platform_type;
    } catch (err) {
      console.error("❌ Failed to decrypt payload:", err);
    }
  }

  const [addPlayer] = useAddPlayerMutation();
  const [triggerGetPlayerList] = useLazyGetPlayerListQuery();
  const [openGameUrl] = useOpenGameUrlMutation();
  const [transferAllCreditToPoint] = useTransferAllCreditToPointMutation();
  const [transferAllPointToCredit] = useTransferAllPointToCreditMutation();
  const [fastOpenGame] = useFastOpenGameMutation();
  const { prevGameMemberId } = useProviderStore();

  const { setLoading } = useContext(UIContext);
  const info = getMemberInfo();
  const [transferOut] = useTransferOutMutation();

  useEffect(() => {
    if (hasRun.current) return;
    hasRun.current = true;

    if (!providerId || !memberId) {
      setError(t("wallets.cannotLoadGame"));
      return;
    }

    (async () => {
      try {
        const res = await openGameFlow({
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
        });

        if (!res.success) {
          setError(res.message);
        }
      } catch (err) {
        console.error("Game redirect failed:", err);
        setError(t("common.somethingWentWrong"));
      }
    })();
  }, []);

  if (error) {
    return (
      <main className="flex h-dvh items-center justify-center bg-[#00143D] text-white">
        <div className="text-center space-y-4">
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
