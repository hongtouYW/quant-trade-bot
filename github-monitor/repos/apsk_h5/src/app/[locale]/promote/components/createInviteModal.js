"use client";

import { useState } from "react";
import { useTranslations } from "next-intl";

export default function CreateInviteModal({ open, onCancel, onConfirm }) {
  const t = useTranslations();
  const [isDefault, setIsDefault] = useState(false);

  if (!open) return null;

  const handleConfirm = () => onConfirm?.({ isDefault });

  return (
    <div
      className="fixed inset-0 z-[200] bg-black/60 backdrop-blur-sm flex justify-center items-center"
      onClick={onCancel}
      aria-hidden
    >
      {/* FULL WIDTH CENTERED CARD */}
      <div
        className="w-[92%] max-w-[480px] rounded-2xl bg-[#0B1D48] text-white px-6 py-6 shadow-2xl mx-auto"
        onClick={(e) => e.stopPropagation()}
        role="dialog"
        aria-modal="true"
        aria-label={t("invite.newCode.title")}
      >
        {/* Title */}
        <h3 className="mb-5 text-base font-semibold text-center">
          {t("invite.newCode.title")}
        </h3>

        {/* Description */}
        <p className="text-sm text-white/70 text-center leading-relaxed mb-6">
          {t("invite.newCode.desc")}
        </p>

        {/* Toggle default */}
        <button
          type="button"
          onClick={() => setIsDefault((s) => !s)}
          className="mb-8 flex items-center justify-center gap-2 text-sm"
        >
          <span
            className={`inline-flex h-5 w-5 items-center justify-center rounded-full border transition
              ${
                isDefault
                  ? "border-transparent bg-[#00C46C]"
                  : "border-white/40 bg-transparent"
              }`}
          >
            {isDefault && (
              <svg
                viewBox="0 0 24 24"
                className="h-3.5 w-3.5"
                fill="none"
                stroke="white"
                strokeWidth="3"
                strokeLinecap="round"
                strokeLinejoin="round"
              >
                <path d="M5 12l4 4 10-10" />
              </svg>
            )}
          </span>
          <span>{t("invite.newCode.setAsDefault")}</span>
        </button>

        {/* ACTIONS */}
        <div className="flex items-center gap-4">
          <button
            type="button"
            onClick={onCancel}
            className="flex-1 rounded-full border border-[#FFC000] py-3 text-sm font-medium text-[#FFC000] active:scale-95"
          >
            {t("common.cancel")}
          </button>

          <button
            type="button"
            onClick={handleConfirm}
            className="flex-1 rounded-full py-3 text-sm font-medium text-black active:scale-95"
            style={{
              background: "linear-gradient(180deg, #F8AF07 0%, #FFFC86 100%)",
            }}
          >
            {t("common.confirm")}
          </button>
        </div>
      </div>
    </div>
  );
}
