"use client";

import Image from "next/image";
import Link from "next/link";
import { IMAGES } from "@/constants/images";
import { useTranslations } from "next-intl";
import { useRouter } from "next/navigation";
import { useContext, useEffect, useRef, useState } from "react";
import { UIContext } from "@/contexts/UIProvider";
import html2canvas from "html2canvas";
import { useGetOfficialLinkQuery } from "@/services/commonApi";
import { getMemberInfo } from "@/utils/utility";
import { toast } from "react-hot-toast";

export default function FindUsPage() {
  const t = useTranslations("findus");
  const router = useRouter();
  const [info, setInfo] = useState(null);

  // read member from cookies
  useEffect(() => {
    const member = getMemberInfo();
    setInfo(member);
  }, []);

  const { data, error } = useGetOfficialLinkQuery(
    { member_id: info?.member_id },
    { skip: !info?.member_id },
  );

  const addresses = data?.data?.domains || [];

  const homeLinks =
    data?.data?.homes?.map((url, index) => ({
      label: t(`homeLink${index + 1}`),
      url,
    })) || [];

  const emailLinks = [{ label: t("officialLink"), url: data?.data?.email }];

  const { setConfirmConfig } = useContext(UIContext);
  const pageRef = useRef(null);
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

  const handleScreenshot = async () => {
    if (!pageRef.current) return;

    const canvas = await html2canvas(pageRef.current, {
      useCORS: true,
      ignoreElements: (el) => el.classList.contains("no-shot"),
      scale: 2, // HD quality
      backgroundColor: null,
    });

    const dataUrl = canvas.toDataURL("image/png");

    // auto download
    const link = document.createElement("a");
    link.href = dataUrl;
    link.download = "findus-page.webp";
    link.click();
  };

  return (
    <div
      ref={pageRef}
      className="mx-auto max-w-[480px] min-h-dvh bg-[#00143D] text-white px-4 py-6"
    >
      {/* Header */}
      <div className="relative flex items-center mb-6">
        <button
          onClick={() => router.back()}
          className="no-shot z-10 cursor-pointer"
        >
          <Image
            src={IMAGES.arrowLeft}
            alt="back"
            width={20}
            height={20}
            className="object-contain"
          />
        </button>
        <h1 className="absolute inset-x-0 text-lg font-semibold text-center">
          {t("title")}
        </h1>
      </div>

      {/* Save Website Section */}
      <h2 className="text-2xl font-bold mb-2">{t("saveWebsite")}</h2>
      <p className="text-xs mb-4" style={{ color: "rgba(255,255,255,0.7)" }}>
        {t("websiteNote")}
      </p>
      <div className="bg-[#13255B] rounded-lg">
        <div className="p-3 mb-4">
          {addresses.map((addr, idx) => (
            <p
              key={idx}
              className="underline text-sm mb-2 last:mb-0 text-[#F8AF07]"
            >
              <Link
                className="text-[#F8AF07] underline"
                href={addr}
                target="_blank"
                rel="noopener noreferrer"
              >
                {addr}
              </Link>
            </p>
          ))}
        </div>

        <div className="bottom-0 left-3 right-3 h-px bg-[#354B9C] mb-4" />

        {homeLinks.map((link, idx) => (
          <div key={idx} className="flex items-center gap-2  px-3 py-2 mb-2">
            <span className="text-sm">
              {link.label}{" "}
              <Link
                className="text-[#F8AF07] underline"
                href={link.url}
                target="_blank"
                rel="noopener noreferrer"
              >
                {link.url}
              </Link>
            </span>
            <Image
              src={IMAGES.iconCopy}
              alt="copy"
              width={16}
              height={16}
              className="object-contain cursor-pointer"
              onClick={async () => {
                try {
                  await navigator.clipboard.writeText(link.url);
                  toast.success(t("common.copied"));
                } catch (err) {
                  toast.error(t("common.copyFailed"));
                }
              }}
            />
          </div>
        ))}

        {/* Save Image Button */}
        <button
          onClick={handleScreenshot}
          className="w-full py-3 rounded-[50px] font-medium text-black mt-4 mb-4"
          style={{
            background: "linear-gradient(180deg, #F8AF07 0%, #FFFC86 100%)",
          }}
        >
          {t("saveImage")}
        </button>
      </div>

      {/* Save Email Section */}
      <h2 className="text-2xl  font-bold mt-8 mb-2 ">{t("saveEmail")}</h2>
      <p className="text-xs mb-4" style={{ color: "rgba(255,255,255,0.7)" }}>
        {t("emailNote")}
      </p>

      <div className="bg-[#13255B] rounded-lg">
        {emailLinks.map((link, idx) => (
          <div key={idx} className="flex items-center gap-2 px-3 py-2 mb-2">
            <span className="text-sm">
              {link.label}{" "}
              <Link
                className="text-[#F8AF07] underline"
                href={`mailto:${link.url}`}
                target="_blank"
                rel="noopener noreferrer"
              >
                {link.url}
              </Link>
            </span>
            <Image
              onClick={async () => {
                try {
                  await navigator.clipboard.writeText(link.url);
                  toast.success(t("common.copied"));
                } catch (err) {
                  toast.error(t("common.copyFailed"));
                }
              }}
              src={IMAGES.iconCopy}
              alt="copy"
              width={16}
              height={16}
              className="object-contain cursor-pointer"
            />
          </div>
        ))}

        <button
          className="w-full py-3 rounded-[50px] font-medium text-black mt-4 mb-4"
          style={{
            background: "linear-gradient(180deg, #F8AF07 0%, #FFFC86 100%)",
          }}
        >
          {t("saveImage")}
        </button>
      </div>
    </div>
  );
}
