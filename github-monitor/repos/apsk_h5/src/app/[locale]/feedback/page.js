"use client";

import { useRouter } from "next/navigation";
import { useState, useMemo, useEffect, useContext } from "react";
import Image from "next/image";
import { useTranslations } from "next-intl";
import { IMAGES } from "@/constants/images";
import { UIContext } from "@/contexts/UIProvider";
import MsgCenterContent from "./components/MsgCenterContent";
import HelpContent from "../help/components/HelpContent";
import NotificationList from "../notification/components/notificationList";
import { useMsgCenterStore } from "@/store/zustand/msgCenterStore";

export default function Feedback() {
  const t = useTranslations();
  const router = useRouter();

  // tabs (top segmented nav)
  const tabs = useMemo(
    () => [
      { key: "cs", label: t("msgCenter.tabs.cs") },
      { key: "notice", label: t("msgCenter.tabs.notice") },
      { key: "marquee", label: t("msgCenter.tabs.marquee") },
      { key: "appFeedback", label: t("msgCenter.tabs.appFeedback") },
    ],
    [t]
  );

  const { activeTab, setActiveTab } = useMsgCenterStore();

  useEffect(() => {
    if (!activeTab) setActiveTab("appFeedback"); // default for this page
  }, [activeTab, setActiveTab]);

  const renderContent = () => {
    switch (activeTab) {
      case "cs":
        return <HelpContent t={t} />;

      case "notice":
        return <NotificationList t={t} />;

      case "marquee":
        return <NotificationList t={t} />;

      case "appFeedback":
        return <MsgCenterContent t={t} />;

      default:
        return null;
    }
  };
  return (
    <div className="mx-auto max-w-[480px] min-h-dvh bg-[#00143D] text-white px-4 pb-8">
      {/* Header */}
      <div className="relative flex items-center h-14">
        <button onClick={() => router.back()} className="z-10 cursor-pointer">
          <Image
            src={IMAGES.arrowLeft}
            alt="back"
            width={22}
            height={22}
            className="object-contain"
          />
        </button>
        <h1 className="absolute inset-x-0 text-lg font-semibold text-center">
          {t("msgCenter.title")}
        </h1>
      </div>

      {/* Tabs */}
      <div className="mt-2 flex items-center gap-6 overflow-x-auto no-scrollbar">
        {tabs.map((tab) => {
          const isActive = activeTab === tab.key;
          return (
            <button
              key={tab.key}
              onClick={() => setActiveTab(tab.key)}
              className={`relative pb-2 text-sm whitespace-nowrap ${
                isActive ? "text-[#F8AF07]" : "text-[#F8AF0780]"
              }`}
            >
              {tab.label}
              {isActive && (
                <span className="absolute left-0 -bottom-[2px] h-[3px] w-full rounded-sm bg-[#F8AF07]" />
              )}
            </button>
          );
        })}
      </div>

      {/* Secondary actions */}
      {renderContent()}
    </div>
  );
}
