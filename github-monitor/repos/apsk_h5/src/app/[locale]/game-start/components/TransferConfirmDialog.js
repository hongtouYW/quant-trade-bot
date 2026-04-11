"use client";

import { memo } from "react";
import Image from "next/image";
import { useTranslations } from "next-intl";

import { IMAGES } from "@/constants/images";
import SubmitButton from "@/components/shared/SubmitButton";

function TransferConfirmDialog({ open, onClose, onConfirm }) {
  const t = useTranslations();

  if (!open) return null;

  return (
    <div
      className="fixed inset-0 z-[200] bg-black/60 backdrop-blur-[1px] flex items-center justify-center"
      onClick={onClose}
      aria-hidden
    >
      <div
        className="mx-auto w-full max-w-[420px] rounded-2xl bg-[#0B1D48] text-white p-6 shadow-2xl"
        onClick={(e) => e.stopPropagation()}
        role="dialog"
        aria-modal="true"
        aria-label={t("common.confirm")}
      >
        {/* Header */}
        <div className="mb-2 space-y-2">
          {/* Row 1: Brand + left close + right link */}
          <div className="relative flex items-center justify-center">
            <button
              onClick={onClose}
              className="absolute left-0 grid h-8 w-8 place-items-center rounded-full text-white/80 active:scale-95"
              aria-label={t("common.close")}
            >
              <Image
                src={IMAGES.iconYellowClose}
                alt="close"
                width={22}
                height={22}
                className="object-contain"
              />
            </button>

            <h3 className="text-base font-semibold text-center">
              {t("common.confirm")}
            </h3>
          </div>

          {/* Row 2: Title */}
        </div>

        {/* Divider */}
        <div className="h-px w-full bg-white/20 mb-4" />

        {/* Message */}
        <p className="mb-6 text-center text-sm text-white/80">
          {t("wallets.confirmMessage")}
        </p>

        {/* Actions */}
        <div className="flex items-center gap-4">
          <button
            type="button"
            onClick={onClose}
            className="flex-1 rounded-full border border-[#FFC000] py-3 text-sm font-medium text-[#FFC000] active:scale-95"
          >
            {t("common.cancel")}
          </button>

          {/* use your SubmitButton for the yellow gradient */}
          <SubmitButton onClick={onConfirm} className="flex-1">
            {t("common.confirm")}
          </SubmitButton>
        </div>
      </div>
    </div>
  );
}

export default memo(TransferConfirmDialog);
