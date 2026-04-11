"use client";

import { useContext, useEffect, useRef, useState } from "react";
import Image from "next/image";
import { useTranslations } from "next-intl";
import { useRouter } from "next/navigation";
import { IMAGES } from "@/constants/images";
import HomeSideBar from "@/components/home/HomeSideBar";
import ActivityTab1 from "./components/tab1";
import VipPage from "../vip/components/page";
import DiscountsReturnScreen from "./components/tab3";
import DiscountRecordsPage from "./components/tab4";
import { UIContext } from "@/contexts/UIProvider";

/* -------- Tabs Content -------- */

function ActivityTab({ activeId, setActiveId }) {
  return <ActivityTab1 />;
}

function VipTab() {
  return (
    <div className="space-y-4 pb-20">
      <VipPage></VipPage>
    </div>
  );
}

function ReturnTab() {
  return <DiscountsReturnScreen />;
}

function RecordTab() {
  return (
    <div className="space-y-4 pb-20">
      <DiscountRecordsPage />
    </div>
  );
}

/* -------- Tabs Bar -------- */

function TabsBar({ activeTab, setActiveTab, t }) {
  const scrollRef = useRef(null);

  const onMouseDown = (e) => {
    const container = scrollRef.current;
    if (!container) return;
    let startX = e.pageX - container.offsetLeft;
    let scrollLeft = container.scrollLeft;

    const onMouseMove = (e) => {
      const x = e.pageX - container.offsetLeft;
      const walk = x - startX;
      container.scrollLeft = scrollLeft - walk;
    };
    const onMouseUp = () => {
      document.removeEventListener("mousemove", onMouseMove);
      document.removeEventListener("mouseup", onMouseUp);
    };
    document.addEventListener("mousemove", onMouseMove);
    document.addEventListener("mouseup", onMouseUp);
  };

  const tabs = [
    { key: 1, label: t("discounts.activity") },
    { key: 2, label: t("discounts.vip") },
    // { key: 3, label: t("discounts.return"), badge: 1 }, // 👈 badge for item 3
    { key: 4, label: t("discounts.record") },
  ];

  return (
    <div
      ref={scrollRef}
      onMouseDown={onMouseDown}
      className="ml-10 flex items-center gap-6 overflow-x-auto no-scrollbar flex-nowrap"
    >
      {tabs.map((tab) => {
        const isActive = activeTab === tab.key;
        return (
          <button
            key={tab.key}
            onClick={() => setActiveTab(tab.key)}
            className={`relative pb-2 text-sm whitespace-nowrap flex items-center ${
              isActive ? "text-[#F8AF07]" : "text-[#F8AF0780]"
            }`}
          >
            {tab.label}

            {/* badge for Tab3 */}
            {tab.badge && (
              <div className="relative ml-1 inline-block">
                <Image
                  src={IMAGES.discount.badge}
                  alt="badge"
                  width={18}
                  height={18}
                  className="w-5 h-5"
                />
                <span className="absolute inset-0 flex items-center justify-center text-[10px] font-semibold text-white">
                  {tab.badge}
                </span>
              </div>
            )}

            {isActive && (
              <span className="absolute left-0 -bottom-[2px] h-[3px] w-full rounded-sm bg-[#F8AF07]" />
            )}
          </button>
        );
      })}
    </div>
  );
}

/* -------- Main Page -------- */

export default function DiscountPage() {
  const t = useTranslations();
  const router = useRouter();
  const [activeTab, setActiveTab] = useState(1);

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
    <div className="mx-auto max-w-[480px] min-h-dvh bg-[#00143D] text-white">
      {/* Header with arrow + tabs */}
      <div className="relative flex items-center h-14 px-4">
        {/* <button onClick={() => router.back()} className="z-10 cursor-pointer">
          <Image
            src={IMAGES.arrowLeft}
            alt="back"
            width={22}
            height={22}
            className="object-contain"
          />
        </button> */}
        <TabsBar activeTab={activeTab} setActiveTab={setActiveTab} t={t} />
      </div>

      {/* Tab Content */}
      {activeTab === 1 && <ActivityTab />}
      {activeTab === 2 && <VipTab />}
      {activeTab === 3 && <ReturnTab />}
      {activeTab === 4 && <RecordTab />}
    </div>
  );
}
