"use client";

import Image from "next/image";
import { useTranslations } from "next-intl";
import { IMAGES } from "@/constants/images";

export default function EmptyRecord({
  titleKey = "common.noRecords",
  image = IMAGES.empty,
  padding = "py-30",
  className = "",
}) {
  const t = useTranslations();

  return (
    <div
      className={`flex flex-col items-center justify-center ${padding} text-white/70 ${className}`}
    >
      <Image
        src={image}
        alt="empty"
        width={120}
        height={120}
        className="mb-3 object-contain"
      />
      <p className="text-sm">{t(titleKey)}</p>
    </div>
  );
}
