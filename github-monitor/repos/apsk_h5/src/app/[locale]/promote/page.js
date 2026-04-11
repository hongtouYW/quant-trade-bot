"use client";

import Image from "next/image";
import { useRouter } from "next/navigation";
import { useTranslations } from "next-intl";
import { useContext, useEffect, useMemo, useRef, useState } from "react";
import { IMAGES } from "@/constants/images";
import SubmitButton from "@/components/shared/SubmitButton";
import EarnShareTab from "./components/tab1";
import MyDataPage from "./components/tab2";
import MyPerformanceTab from "./components/tab3";
import MyCommissionTab from "./components/tab4";
import SubordinatesTab from "./components/tab5";
import ReturnRatioTab from "./components/tab6";
import DirectOpenAccountTab from "./components/tab7";
import { UIContext } from "@/contexts/UIProvider";

export default function EarnPage() {
  const router = useRouter();
  const t = useTranslations(); // e.g. keys under "earn"

  const tabs = useMemo(
    () => [
      { key: "share", label: t("earn.tabs.share") },
      { key: "stats", label: t("earn.tabs.stats") },
      { key: "performance", label: t("earn.tabs.performance") },
      { key: "commission", label: t("earn.tabs.commission") },
      { key: "downline", label: t("earn.tabs.downline") },
      // { key: "backRatioTab", label: t("earn.tabs.backRatioTab") },
      { key: "directMember", label: t("earn.tabs.directMember") },
    ],
    [t]
  );

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

  const [activeTab, setActiveTab] = useState("share");

  const { setConfirmConfig } = useContext(UIContext);

  const handleTabClick = (key) => {
    if (key === "share") {
      setActiveTab(key);
    } else if (key === "downline") {
      setActiveTab(key);
    } else if (key === "performance") {
      setActiveTab(key);
    } else if (key === "commission") {
      setActiveTab(key);
    } else if (key === "stats") {
      setActiveTab(key);
    } else {
      setActiveTab(key);
      // setConfirmConfig({
      //   titleKey: "common.underMaintenanceTitle",
      //   messageKey: "common.underMaintenanceMessage",
      //   confirmKey: "common.ok",
      //   displayMode: "center",
      //   showCancel: false, // ✅ only OK button
      // });
    }
  };

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
    <div className="mx-auto max-w-[480px] min-h-dvh bg-[#00143D] text-white  pb-8">
      {/* Header */}
      <div className="relative flex items-center h-14 px-4">
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
          {t("earn.title")}
        </h1>
      </div>

      {/* Tabs */}
      <div
        ref={scrollRef}
        onMouseDown={onMouseDown}
        className="px-4 mt-2 mb-2 flex items-center gap-6 overflow-x-auto no-scrollbar flex-nowrap"
      >
        {tabs.map((tab) => {
          const isActive = activeTab === tab.key;
          return (
            <button
              key={tab.key}
              onClick={() => handleTabClick(tab.key)}
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

      {/* Content */}
      {activeTab === "share" && <ShareTab t={t} setActiveTab={setActiveTab} />}
      {activeTab === "stats" && <StatsTab t={t} />}
      {activeTab === "performance" && <PerformanceTab t={t} />}
      {activeTab === "commission" && <CommissionTab t={t} />}
      {activeTab === "backRatioTab" && <RatioTab t={t} />}
      {activeTab === "downline" && <DownlineTab t={t} />}
      {activeTab === "directMember" && <DirectOpenAccountTab t={t} />}
    </div>
  );
}

/* ------------------------- TAB 1: Share (主页面) ------------------------- */
function ShareTab({ t, setActiveTab }) {
  return <EarnShareTab setActiveTab={setActiveTab} />;
}

/* ------------------------- TAB 2: Stats ------------------------- */
function StatsTab({ t }) {
  return <MyDataPage></MyDataPage>;
}

/* ------------------------- TAB 3: Performance ------------------------- */
function PerformanceTab({ t }) {
  return <MyPerformanceTab></MyPerformanceTab>;
}

/* ------------------------- TAB 4: Commission ------------------------- */
function CommissionTab({ t }) {
  return <MyCommissionTab></MyCommissionTab>;
}

/* ------------------------- TAB 5: Downline ------------------------- */
function DownlineTab({ t }) {
  return <SubordinatesTab></SubordinatesTab>;
}

/* ------------------------- TAB 6: Return Ratio Tab ------------------------- */
function RatioTab({ t }) {
  return <ReturnRatioTab></ReturnRatioTab>;
}

/* ------------------------- TAB 7: Direct Open Account Tab ------------------------- */
function DirectAccountTab({ t }) {
  return <DirectOpenAccountTab></DirectOpenAccountTab>;
}

/* ------------------------- Small helpers ------------------------- */
function SectionTitle({ children }) {
  return <h3 className="text-[15px] font-semibold">{children}</h3>;
}

function PlaceholderCard({ children }) {
  return (
    <div className="rounded-2xl bg-[#071B4F] p-6 text-white/70 text-sm">
      {children}
    </div>
  );
}
