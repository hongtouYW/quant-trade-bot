"use client";

import { useRouter } from "next/navigation";
import { useState, useMemo, useEffect, useContext } from "react";
import Image from "next/image";
import { useTranslations } from "next-intl";
import { IMAGES } from "@/constants/images";
import { UIContext } from "@/contexts/UIProvider";

import HelpContent from "../help/components/HelpContent";
import NotificationList from "./components/notificationList";
import MsgCenterContent from "../feedback/components/MsgCenterContent";
import { useMsgCenterStore } from "@/store/zustand/msgCenterStore";
import MarqueList from "./components/marquelist";
import { useGetNotificationListQuery } from "@/services/notificationApi";
import { getMemberInfo } from "@/utils/utility";
import { useGetSliderListQuery } from "@/services/commonApi";

export default function Notification() {
  const t = useTranslations();
  const router = useRouter();
  const info = useMemo(() => getMemberInfo(), []);
  const { data: noticeData, isLoading: isNoticeLoading } =
    useGetNotificationListQuery(
      info?.member_id ? { member_id: info.member_id } : {},
      { skip: !info?.member_id },
    );

  const { data: marqueData, isLoading: isMarqueLoading } =
    useGetSliderListQuery(
      info?.member_id ? { member_id: info.member_id } : {},
      { skip: !info?.member_id },
    );

  const noticeUnread = useMemo(() => {
    if (!noticeData?.data) return 0;
    return noticeData.data.filter((n) => n.is_read === 0).length;
  }, [noticeData]);

  const marqueUnread = useMemo(() => {
    if (!marqueData?.data) return 0;
    return marqueData.data.filter((n) => n.is_read === 0).length;
  }, [marqueData]);

  // tabs (top segmented nav)
  const tabs = useMemo(
    () => [
      { key: "cs", label: t("msgCenter.tabs.cs"), count: 0 },
      { key: "notice", label: t("msgCenter.tabs.notice"), count: noticeUnread },
      {
        key: "marquee",
        label: t("msgCenter.tabs.marquee"),
        count: marqueUnread,
      },
      {
        key: "appFeedback",
        label: t("msgCenter.tabs.appFeedback"),
        count: 0,
      },
    ],
    [t, noticeUnread, marqueUnread],
  );

  const { activeTab, setActiveTab } = useMsgCenterStore();

  useEffect(() => {
    if (!activeTab) setActiveTab("cs"); // default for this page
  }, [activeTab, setActiveTab]);

  const renderContent = () => {
    switch (activeTab) {
      case "cs":
        return <HelpContent t={t} type="cs" />;

      case "notice":
        return <NotificationList t={t} />;

      case "marquee":
        return <MarqueList t={t} />;

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
              className={`relative pb-2 text-sm whitespace-nowrap flex items-center gap-1 ${
                isActive ? "text-[#F8AF07]" : "text-[#F8AF0780]"
              }`}
            >
              {tab.label}
              {/* badge */}
              {tab.count > 0 && (
                <span
                  className="
                    bg-red-600 text-white 
                    text-[10px] font-medium
                    flex items-center justify-center
                    rounded-full
                    h-[16px] min-w-[16px]
                  "
                >
                  {tab.count}
                </span>
              )}
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
