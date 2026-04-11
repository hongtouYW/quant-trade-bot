"use client";

import { useTranslations } from "next-intl";

export default function UserAgreementModal({ url, loading, onClose }) {
  const t = useTranslations();

  return (
    <div
      className="fixed inset-0 z-[100] bg-black/60 backdrop-blur-[1px] 
             flex items-center justify-center"
      onClick={onClose}
      aria-hidden
    >
      <div
        className="mx-2 w-full max-w-[480px] rounded-2xl bg-[#0B1D48] text-white p-4 shadow-2xl
                transition-all flex flex-col h-[80dvh] relative"
        onClick={(e) => e.stopPropagation()}
        role="dialog"
        aria-modal="true"
        aria-label={t("register.userAgreement")}
      >
        {/* Close button (text “X”) */}
        <button
          onClick={onClose}
          className="absolute top-3 right-3 text-white/80 hover:text-white
                     text-xl leading-none p-1 active:scale-90"
        >
          ×
        </button>

        {/* Title */}
        <h3 className="text-lg font-semibold text-center mb-3 mt-1">
          {t("register.userAgreement")}
        </h3>

        {/* iframe content */}
        <div className="flex-1 mb-4 rounded-lg overflow-hidden border border-white/10">
          {loading ? (
            <div className="text-center text-white/70 py-10 text-sm">
              {t("common.loading")}
            </div>
          ) : (
            <iframe
              src={url}
              title="agreement"
              className="w-full h-full border-none"
            />
          )}
        </div>

        {/* OK button */}
        <button
          onClick={onClose}
          className="w-full h-12 rounded-full bg-gradient-to-b from-[#F8AF07] to-[#FFFC86]
                     text-black font-semibold active:scale-95 transition"
        >
          {t("common.ok")}
        </button>
      </div>
    </div>
  );
}
