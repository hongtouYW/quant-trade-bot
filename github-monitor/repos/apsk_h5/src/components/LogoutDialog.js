"use client";

import React, { useContext } from "react";
import { UIContext } from "@/contexts/UIProvider";
import { useTranslations } from "next-intl";
import { clearAllCookie } from "@/utils/cookie";

export default function LogoutDialog() {
  const t = useTranslations();
  const { logoutConfig, setLogoutConfig } = useContext(UIContext);

  if (!logoutConfig) return null;

  const {
    titleKey = "common.sessionExpired",
    messageKey = "common.sessionExpiredMessage",
    confirmKey = "common.ok",
  } = logoutConfig;

  const close = () => setLogoutConfig(null);

  const handleConfirm = () => {
    try {
      clearAllCookie();
      localStorage.clear();
      window.location.href = "/login";
    } finally {
      close();
    }
  };

  return (
    <div className="fixed inset-0 z-[200] bg-black/60 backdrop-blur-sm flex justify-center items-center">
      <div className="w-full max-w-[420px] rounded-2xl bg-[#0B1D48] p-6 text-white shadow-2xl text-center">
        <h3 className="text-lg font-bold mb-3">{t(titleKey)}</h3>

        <p className="text-sm text-white/70 mb-8 leading-relaxed">
          {t(messageKey)}
        </p>

        <button
          onClick={handleConfirm}
          className="w-full h-12 rounded-full bg-gradient-to-b from-[#F8AF07] to-[#FFFC86]
          text-black font-semibold active:scale-95 transition"
        >
          {t(confirmKey)}
        </button>
      </div>
    </div>
  );
}
