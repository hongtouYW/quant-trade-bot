"use client";

import { useTranslations } from "next-intl";
import { useContext, useEffect } from "react";
import { useRouter } from "next/navigation";
import Image from "next/image";
import { UIContext } from "@/contexts/UIProvider";
import { IMAGES } from "@/constants/images";
import HelpContent from "./components/HelpContent";

export default function HelpPage() {
  const t = useTranslations();
  const router = useRouter();
  const { setConfirmConfig } = useContext(UIContext);

  // useEffect(() => {
  //   setConfirmConfig({
  //     titleKey: "common.underMaintenanceTitle",
  //     messageKey: "common.underMaintenanceMessage",
  //     confirmKey: "common.ok",
  //     displayMode: "center",
  //     showCancel: true,
  //   });
  // }, [setConfirmConfig]);

  return (
    <div className="mx-auto max-w-[480px] min-h-dvh bg-[#00143D] text-white px-4 pb-10">
      {/* Header */}
      <div className="relative flex items-center h-14">
        {/* <button onClick={() => router.back()} className="z-10 cursor-pointer">
          <Image
            src={IMAGES.arrowLeft}
            alt="back"
            width={22}
            height={22}
            className="object-contain"
          />
        </button> */}
        <h1 className="absolute inset-x-0 text-lg font-semibold text-center">
          {t("help.title")}
        </h1>
      </div>

      {/* Content */}
      <HelpContent t={t} type="all" />
    </div>
  );
}
