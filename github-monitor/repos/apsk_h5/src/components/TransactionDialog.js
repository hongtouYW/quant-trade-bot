"use client";

import { useContext } from "react";
import Image from "next/image";
import { useTranslations } from "next-intl";
import { UIContext } from "@/contexts/UIProvider";
import SubmitButton from "./shared/SubmitButton";
import { useRouter } from "next/navigation";

export default function TransactionDialog() {
  const t = useTranslations();
  const { successSheet, setSuccessSheet } = useContext(UIContext);
  const router = useRouter();
  if (!successSheet.visible) return null;
  const { titleKey, descKey, amount, imageSrc, onClose, redirectTo } =
    successSheet;

  const handleClose = () => {
    // hide first for a smooth UX
    setSuccessSheet((s) => ({ ...s, visible: false }));

    setTimeout(() => {
      if (typeof onClose === "function") {
        onClose(); // 🔥 external logic (refetch, back, push, etc.)
      } else if (redirectTo) {
        router.push(redirectTo);
      } else {
        router.back();
      }
    }, 250);
  };

  return (
    <div className="fixed inset-0 z-50">
      {/* Backdrop */}
      <div
        className="absolute inset-0 bg-black/60 backdrop-blur-[1px]"
        onClick={handleClose}
        aria-hidden
      />

      {/* Centered Modal */}
      <div className="absolute left-1/2 top-1/2 w-full max-w-[400px] -translate-x-1/2 -translate-y-1/2 rounded-2xl bg-[#0B1D48] p-6 text-center shadow-lg">
        {/* Close Button */}
        <button
          onClick={handleClose}
          className="absolute right-4 top-4 text-white/60 hover:text-white"
          aria-label="Close"
        >
          ✕
        </button>

        {/* Title */}
        <div className="text-lg font-semibold">{t(titleKey)}</div>

        <div className="mt-4 flex flex-col items-center">
          {imageSrc && (
            <Image
              src={imageSrc}
              alt="success"
              width={140}
              height={140}
              className="object-contain"
            />
          )}

          {descKey && (
            <p className="mt-3 text-sm text-white/70">{t(descKey)}</p>
          )}

          {amount !== null && (
            <div className="mt-4 w-full rounded-xl border border-white/20 bg-[#06153C] px-5 py-4 text-center text-xl font-semibold text-[#FFC000]">
              + RM{Number(amount).toFixed(2)}
            </div>
          )}
        </div>

        <div className="mt-6">
          <SubmitButton onClick={handleClose}>{t("common.ok")}</SubmitButton>
        </div>
      </div>
    </div>
  );
}
