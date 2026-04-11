"use client";

import { useEffect, useContext } from "react";
import { useRouter } from "next/navigation";
import { toast } from "react-hot-toast";
import { UIContext } from "@/contexts/UIProvider";
import { useTranslations } from "next-intl";

export default function PaymentRedirectPage() {
  const router = useRouter();
  const { setLoading } = useContext(UIContext);
  const t = useTranslations();

  useEffect(() => {
    const url = sessionStorage.getItem("paymentRedirectUrl");
    if (url) {
      sessionStorage.removeItem("paymentRedirectUrl");
      window.location.replace(url); // 👈 replaces history instead of adding new entry
    } else {
      router.replace("/topup");
    }
  }, [router]);

  return (
    <div className="flex flex-col items-center justify-center min-h-dvh bg-[#00143D] text-white">
      {/* 🌀 Loading spinner */}
      <div className="h-10 w-10 mb-6 animate-spin rounded-full border-4 border-white/20 border-t-[#FFC000]" />

      <p className="text-lg font-semibold">{t("topups.redirect.title")}</p>
      <p className="text-sm text-white/60 mt-2">{t("topups.redirect.desc")}</p>
    </div>
  );
}
