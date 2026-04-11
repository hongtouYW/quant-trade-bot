"use client";
import { useContext } from "react";
import { UIContext } from "@/contexts/UIProvider";
import { useTranslations } from "next-intl";
import Image from "next/image";
import { IMAGES } from "@/constants/images";

export default function LoadingDialog() {
  const { loading } = useContext(UIContext);
  const t = useTranslations("common");

  if (!loading) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm">
      <div className="px-6 py-5 rounded-2xl flex flex-col items-center">
        <Image
          src={IMAGES.common.loadingBar}
          alt="loading"
          width={150}
          height={150}
          className="object-contain"
          priority
        />
        <p className="text-sm text-white mt-3">{t("loading")}</p>
      </div>
    </div>
  );
}
