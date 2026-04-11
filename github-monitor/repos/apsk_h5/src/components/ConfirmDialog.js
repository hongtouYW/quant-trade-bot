"use client";

import React, { useContext } from "react";
import { UIContext } from "@/contexts/UIProvider";
import { useTranslations } from "next-intl";

export default function ConfirmDialog() {
  const t = useTranslations();
  const { confirmConfig, setConfirmConfig } = useContext(UIContext);
  if (!confirmConfig) return null;

  const {
    titleKey = "common.confirm",
    messageKey = "setting.logoutConfirmMessage",
    replaceKey,
    replaceValue,
    confirmKey = "setting.logoutConfirm",
    cancelKey = "common.cancel",
    onConfirm,
    onCancel,
  } = confirmConfig;

  const close = () => setConfirmConfig(null);

  const handleCancel = () => {
    try {
      onCancel?.();
    } finally {
      close();
    }
  };

  const handleConfirm = () => {
    try {
      onConfirm?.();
    } finally {
      close();
    }
  };

  return (
    <div
      className="fixed inset-0 z-[100] bg-black/60 backdrop-blur-[1px] flex items-center justify-center"
      onClick={handleCancel}
      aria-hidden
    >
      <div
        className="mx-2 w-full max-w-[480px] rounded-2xl bg-[#0B1D48] text-white p-6 shadow-2xl transition-all"
        onClick={(e) => e.stopPropagation()}
        role="dialog"
        aria-modal="true"
        aria-label={t(titleKey)}
      >
        {/* Title */}
        <h3 className="mb-3 text-lg font-semibold text-center">
          {t(titleKey)}
        </h3>

        {/* Message */}
        <div className="mb-8 text-center text-sm text-white/70 leading-relaxed">
          {(() => {
            const raw = t(messageKey);

            if (replaceKey && replaceValue !== undefined) {
              return raw.replace(replaceKey, replaceValue);
            }

            return raw;
          })()}
        </div>

        {/* Actions */}
        <div className="flex items-center gap-4 px-4">
          {/* Cancel */}
          <button
            type="button"
            onClick={handleCancel}
            className="w-1/2 rounded-full border border-[#FFC000] py-3 text-[#FFC000] font-medium active:scale-95 transition"
          >
            {t(cancelKey)}
          </button>

          {/* Confirm */}
          <button
            type="button"
            onClick={handleConfirm}
            className="w-1/2 h-12 rounded-full bg-gradient-to-b from-[#F8AF07] to-[#FFFC86]
             border border-transparent flex items-center justify-center
             text-black font-semibold disabled:opacity-50 active:scale-95 transition"
          >
            {t(confirmKey)}
          </button>
        </div>
      </div>
    </div>
  );
}
