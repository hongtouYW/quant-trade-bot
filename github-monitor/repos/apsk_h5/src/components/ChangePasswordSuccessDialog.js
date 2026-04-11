"use client";

import { useTranslations } from "next-intl";

export default function ChangePasswordSuccessDialog({ open, onConfirm }) {
  const t = useTranslations();

  if (!open) return null;

  return (
    <div className="fixed inset-0 z-[200]">
      <div
        className="absolute inset-0 bg-black/60 backdrop-blur-[1px]"
        onClick={onConfirm}
        aria-hidden
      />
      <div
        className="absolute left-1/2 top-1/2 mx-2 w-[calc(100%-16px)] max-w-[480px] -translate-x-1/2 -translate-y-1/2 rounded-2xl bg-[#0B1D48] p-6 text-white shadow-2xl transition-all"
        role="dialog"
        aria-modal="true"
        aria-label={t("securityVerify.successModal.title")}
        onClick={(e) => e.stopPropagation()}
      >
        <h3 className="mb-3 text-center text-lg font-semibold">
          {t("securityVerify.successModal.title")}
        </h3>

        <div className="mb-8 text-center text-sm leading-relaxed text-white/70">
          {t("securityVerify.successModal.message")}
        </div>

        <div className="px-4">
          <button
            type="button"
            onClick={onConfirm}
            className="flex h-12 w-full items-center justify-center rounded-full border border-transparent bg-gradient-to-b from-[#F8AF07] to-[#FFFC86] font-semibold text-black transition active:scale-95"
          >
            {t("securityVerify.successModal.confirm")}
          </button>
        </div>
      </div>
    </div>
  );
}
