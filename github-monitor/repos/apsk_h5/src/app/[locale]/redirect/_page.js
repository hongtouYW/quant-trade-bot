"use client";
import { useEffect, useRef } from "react";
import { useRouter } from "next/navigation";
import { toast } from "react-hot-toast";
import { useTranslations } from "next-intl";

export default function RedirectPage() {
  const router = useRouter();
  const t = useTranslations();
  const hasRun = useRef(false);
  const gameUrlRef = useRef(null);

  const handleManualOpen = () => {
    const gameUrl = gameUrlRef.current;
    if (!gameUrl) {
      // toast.error(t("wallets.cannotLoadGame") || "Something went wrong.");
      return;
    }

    const newTab = window.open(gameUrl, "_blank");
    if (newTab) {
      newTab.opener = null;
      newTab.focus();
    } else {
      // fallback: same tab so user can go back
      window.location.href = gameUrl;
    }
  };

  useEffect(() => {
    if (hasRun.current) return;
    hasRun.current = true;

    const gameUrl = sessionStorage.getItem("redirectUrl");
    gameUrlRef.current = gameUrl;
    sessionStorage.removeItem("redirectUrl");

    if (!gameUrl) {
      // toast.error(t("wallets.cannotLoadGame") || "Something went wrong.");
      router.push("/");
      return;
    }

    try {
      const ua = navigator.userAgent || "";
      const isIOS = /iPhone|iPad|iPod/i.test(ua);
      const isInApp = /FBAN|FBAV|Instagram|Line|WeChat|MicroMessenger/i.test(
        ua
      );

      if (isIOS || isInApp) {
        // ✅ iOS/in-app → same tab so Back works
        window.location.href = gameUrl;
        return;
      }

      // ✅ Desktop/Android → try new tab
      const newTab = window.open(gameUrl, "_blank");

      if (newTab) {
        newTab.opener = null;
        newTab.focus();
        // don’t replace — let back work naturally
      } else {
        // ❗ popup blocked → open same tab (Back works)
        window.location.href = gameUrl;
      }
    } catch (err) {
      console.error("Redirect failed:", err);
      window.location.href = gameUrl;
    }
  }, [router, t]);

  return (
    <div
      style={{
        textAlign: "center",
        paddingTop: "60px",
        background: "#0B1D48",
        color: "white",
        minHeight: "100vh",
        fontFamily: "sans-serif",
      }}
    >
      <h2 style={{ color: "#FFC000" }}>{t("wallets.loadingGameTitle")}</h2>
      <p>{t("wallets.pleaseWaitToContinue")}</p>
      <p style={{ fontSize: 12, color: "#aaa", marginTop: 10 }}>
        {t("wallets.popupBlockedNotice")}
      </p>

      {/* 🔹 Buttons side by side (left/right) */}
      <div
        style={{
          display: "flex",
          justifyContent: "center",
          gap: 16,
          marginTop: 24,
          width: "100%",
          maxWidth: 320,
          marginLeft: "auto",
          marginRight: "auto",
        }}
      >
        {/* 🎮 Open game */}
        <button
          onClick={handleManualOpen}
          style={{
            flex: 1,
            background: "linear-gradient(90deg, #F8AF07, #FFFC86)",
            color: "#000",
            border: "none",
            borderRadius: "999px",
            padding: "10px 0",
            fontWeight: 600,
            fontSize: 14,
            cursor: "pointer",
            boxShadow: "0 0 10px rgba(255,192,0,0.3)",
          }}
        >
          🎮 {t("wallets.openGameManually")}
        </button>

        {/* ⬅️ Back home */}
        <button
          onClick={() => {
            if (window.history.length > 1) router.back();
            else router.push("/");
          }}
          style={{
            flex: 1,
            background: "transparent",
            border: "1px solid #FFC000",
            borderRadius: "999px",
            color: "#FFC000",
            padding: "10px 0",
            fontWeight: 500,
            fontSize: 14,
            cursor: "pointer",
          }}
        >
          ⬅️ {t("wallets.backToHome")}
        </button>
      </div>
    </div>
  );
}
