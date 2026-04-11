"use client";

import { useRouter } from "next/navigation";
import { useState, useMemo, useEffect, useContext } from "react";
import Image from "next/image";
import { useTranslations } from "next-intl";
import { IMAGES } from "@/constants/images";
import { UIContext } from "@/contexts/UIProvider";

export default function Support() {
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
  const [activeTab, setActiveTab] = useState("appFeedback");

  // secondary buttons
  const [mode, setMode] = useState("create");

  // form state
  const [topic, setTopic] = useState("");
  const [content, setContent] = useState("");
  const maxLen = 1000;

  // uploads
  const [files, setFiles] = useState([]);
  const onPickFiles = (e) => {
    const picked = Array.from(e.target.files || []);
    // Basic client-side size checks (images ≤ 2MB, videos ≤ 20MB)
    const ok = picked.filter((f) => {
      const isVideo = f.type.startsWith("video/");
      const limit = isVideo ? 20 * 1024 * 1024 : 2 * 1024 * 1024;
      return f.size <= limit;
    });
    setFiles(ok);
  };

  const onSubmit = (e) => {
    e.preventDefault();
    // TODO: submit via API

    alert(t("msgCenter.toast.submitted"));
  };

  const { setConfirmConfig } = useContext(UIContext);

  useEffect(() => {
    // 🔔 show notice on first render
    setConfirmConfig({
      titleKey: "common.underMaintenanceTitle",
      messageKey: "common.underMaintenanceMessage",
      confirmKey: "common.ok",
      displayMode: "center", // ✅ <— show in center
      showCancel: true, // ✅ one-button mode
    });
  }, [setConfirmConfig]);

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
          {t("footer.cs")}
        </h1>
      </div>
    </div>
  );
}
