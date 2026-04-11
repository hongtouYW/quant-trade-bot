"use client";
import { useEffect, useRef, useState } from "react";
import { useRouter } from "next/navigation";
import { toast } from "react-hot-toast";
import { useTranslations } from "next-intl";

export default function RedirectPage() {
  const router = useRouter();
  const t = useTranslations();
  const hasRun = useRef(false);
  const [failed, setFailed] = useState(false);

  useEffect(() => {
    // 🧩 Prevent re-entry after first use
    const used = sessionStorage.getItem("redirectUsed");
    if (used) {
      router.replace("/"); // redirect them to home or another safe page
      return;
    }
    sessionStorage.setItem("redirectUsed", "true");

    if (hasRun.current) return;
    hasRun.current = true;

    const gameUrl = sessionStorage.getItem("redirectUrl");

    if (!gameUrl) {
      //toast.error(t("wallets.cannotLoadGame"));
      router.back();
      return;
    }

    // remove it immediately
    sessionStorage.removeItem("redirectUrl");

    try {
      // 🪄 Trick: open a blank tab first, assign URL right away
      const newTab = window.open("", "_blank");
      if (newTab) {
        newTab.opener = null; // security
        newTab.location = gameUrl;
        router.back(); // ✅ go back to previous page instantly
      } else {
        // ❗ fallback if popup blocked
        setFailed(true);
        window.location.href = gameUrl;
      }
    } catch (err) {
      console.error("Popup failed", err);
      setFailed(true);
      window.location.href = gameUrl;
    }
  }, [router, t]);

  return (
    <div className="flex flex-col items-center justify-center min-h-dvh bg-[#0B1D48] text-white text-center px-4">
      <h2 className="text-lg font-semibold text-[#FFC000] mb-2">
        {t("wallets.loadingGameTitle")}
      </h2>
      <p className="text-sm mb-6">{t("wallets.pleaseWaitToContinue")}</p>

      {failed && (
        <p className="text-xs text-white/70">
          {t("wallets.popupBlockedTryAgain")}
        </p>
      )}
    </div>
  );
}
