"use client";

import Image from "next/image";
import Link from "next/link";
import { useTranslations } from "next-intl";
import { IMAGES } from "@/constants/images";
import { useContext, useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import VipPage from "./components/page";
import { UIContext } from "@/contexts/UIProvider";

import {
  useClaimFirstBonusMutation,
  useClaimMonthlyBonusMutation,
  useClaimWeeklyBonusMutation,
} from "@/services/vipApi";

export default function VipPrivileges() {
  const t = useTranslations();
  const [activeTab, setActiveTab] = useState("normal");

  const currentVip = 1;
  const progress = { cur: 1327, target: 3000 };
  const router = useRouter();

  const { setConfirmConfig } = useContext(UIContext);

  // useEffect(() => {
  //   // 🔔 show notice on first render
  //   setConfirmConfig({
  //     titleKey: "common.underMaintenanceTitle",
  //     messageKey: "common.underMaintenanceMessage",
  //     confirmKey: "common.ok",
  //     displayMode: "center", // ✅ <— show in center
  //     showCancel: true, // ✅ one-button mode
  //   });
  // }, [setConfirmConfig]);

  return (
    <div className="mx-auto max-w-[480px] min-h-dvh bg-[#00143D] text-white py-6">
      {/* Header */}
      <div className="relative flex items-center px-4 py-4">
        <button onClick={() => router.back()} className="z-10 cursor-pointer">
          <Image src={IMAGES.arrowLeft} alt="back" width={20} height={20} />
        </button>
        <h1 className="absolute inset-x-0 text-lg font-semibold text-center">
          {t("vips.title")}
        </h1>
      </div>

      <VipPage></VipPage>
    </div>
  );
}
