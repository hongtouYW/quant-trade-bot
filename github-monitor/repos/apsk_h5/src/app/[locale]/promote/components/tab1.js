"use client";

import Image from "next/image";
import { useTranslations } from "next-intl";

import SubmitButton from "@/components/shared/SubmitButton";
import { IMAGES } from "@/constants/images";
import { useGetReferralQrQuery } from "@/services/authApi";
import { getMemberInfo, getUserLevel } from "@/utils/utility";
import { useContext, useEffect, useState } from "react";
import { toast } from "react-hot-toast";
import { QRCodeCanvas } from "qrcode.react"; // or react-qrcode-logo if you prefer
import MarqueeTitle from "@/components/shared/MarqueeTitle";
import {
  useGetReferralDirectUplineQuery,
  useGetReferralTutorialQuery,
} from "@/services/referralApi";
import { UIContext } from "@/contexts/UIProvider";
import { skipToken } from "@reduxjs/toolkit/query";
import { useRouter } from "next/navigation";

export default function EarnShareTab({ setActiveTab }) {
  const t = useTranslations();

  const [info, setInfo] = useState(null);
  const [referralCode, setReferralCode] = useState("");
  const [agentCode, setAgentCode] = useState("");
  const [invitationUrl, setInvitationUrl] = useState("");
  const [showWeChatQR, setShowWeChatQR] = useState(false);
  const { setLoading } = useContext(UIContext);
  const router = useRouter();

  // read cookies on client
  useEffect(() => {
    const member = getMemberInfo();
    setInfo(member);
  }, []);

  const { data, isLoading, isFetching } = useGetReferralQrQuery(
    info?.member_id ? { member_id: info.member_id } : skipToken,
    {
      // ⭐ Cache for 60 seconds
      keepUnusedDataFor: 300,
      refetchOnMountOrArgChange: false,

      refetchOnWindowFocus: false,
      refetchOnReconnect: false,
    },
  );

  const { data: dataDirectUpline } = useGetReferralDirectUplineQuery(
    info?.member_id ? { member_id: info.member_id } : skipToken,
    {
      // ⭐ Cache for 60 seconds
      keepUnusedDataFor: 3000,
      refetchOnMountOrArgChange: false,
      refetchOnWindowFocus: true,
      refetchOnReconnect: true,
    },
  );

  console.log(dataDirectUpline);

  // ⭐ Global loading bar logic
  useEffect(() => {
    setLoading(isLoading); // ❗ only first load shows loading
  }, [isLoading, setLoading]);

  const {
    data: tutorialData,
    error,
    isLoading: isTutorialLoading,
  } = useGetReferralTutorialQuery(
    { member_id: info?.member_id },
    { skip: !info?.member_id },
  );
  const tutorial = tutorialData?.data || {}; // safe fallback
  useEffect(() => {
    if (data?.status && data?.qr) {
      try {
        // ✅ Extract query params from backend QR URL
        const url = new URL(data.qr);
        const agent = url.searchParams.get("agentCode") || "";
        const referral = url.searchParams.get("referralCode") || "";

        setAgentCode(agent);
        setReferralCode(referral);

        // ✅ Use current host dynamically (no need for env)
        if (typeof window !== "undefined") {
          const baseUrl = window.location.origin;
          setInvitationUrl(
            `${baseUrl}/user-download?agentCode=${agent}&referralCode=${referral}`,
          );
        }
      } catch (err) {
        console.error("Invalid QR URL:", err);
      }
    }
  }, [data]);

  const handleShare = (platform) => {
    if (!invitationUrl) return;

    const text = encodeURIComponent(t("earn.share.shareTitle"));
    const url = encodeURIComponent(invitationUrl);
    let shareUrl = "";

    switch (platform) {
      case "facebook":
        shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${url}`;
        break;
      case "telegram":
        shareUrl = `https://t.me/share/url?url=${url}&text=${text}`;
        break;
      case "whatsapp":
        shareUrl = `https://api.whatsapp.com/send?text=${text}%20${url}`;
        break;
      case "wechat":
        setShowWeChatQR(true); // ✅ open modal
        return;
      default:
        return;
    }
    window.open(shareUrl, "_blank");
  };

  return (
    <div className="mt-3 space-y-5 px-4">
      {/* Badge + actions */}
      <div className="mt-2 flex flex-col items-start">
        {/* Badge on the left */}
        <div className="relative w-[100px] h-[100px]">
          {/* 1. The Image component */}
          <Image
            src={IMAGES.earn.level}
            alt="level badge"
            width={100}
            height={100}
            className="rounded-lg" // The base image should fill the container
          />

          {/* 2. The Text Overlay */}
          <div
            className="absolute top-[40%] left-1/2 transform -translate-x-1/2 -translate-y-1/2 
               text-white text-xl font-bold z-10"
          >
            {getUserLevel()}
          </div>
        </div>
        {/* Under badge: labels (left) + buttons (right) */}
        <div className="mt-2 flex w-full items-center justify-between">
          {/* left text */}
          <div>
            {/* <p className="flex items-center text-[13px] text-white/85 leading-6">
              {t("earn.share.toClaim")} <span className="ml-1">-</span>
            </p> */}
            <p className="flex items-center text-[12px] text-white/70 leading-6">
              {t("earn.share.myUpline")}{" "}
              <span className="ml-1">{dataDirectUpline?.data ?? "-"}</span>
            </p>
          </div>

          {/* right buttons */}
          <div className="flex items-center gap-2">
            {/* <SubmitButton className="!w-auto !px-4 !py-1.5 !text-xs">
              {t("earn.share.oneClickReceive")}
            </SubmitButton> */}
            <SubmitButton
              onClick={() => setActiveTab("commission")}
              className="!w-auto !px-4 !py-1.5 !text-xs"
            >
              {t("earn.share.receiveHistory")}
            </SubmitButton>
          </div>
        </div>

        {/* bottom note */}
        <p className="mt-2 w-full text-right text-[12px] text-white/60">
          {t("earn.share.agentMode")}
        </p>
      </div>

      {/* QR + link */}
      <div className="h-px w-full bg-[#354B9C] " />

      <div className="p-1">
        {/* FIRST ROW */}
        <div className="flex gap-4 w-full">
          {/* LEFT: QR + Save */}
          <div className="rounded-xl p-2">
            {/* LEFT: QR + Save inside one white box */}
            <div className="flex h-[168px] w-[120px] flex-col overflow-hidden rounded-xl bg-white">
              {/* QR image */}
              <div className="flex flex-1 items-center justify-center">
                {/* Replace with real QR if needed */}
                {invitationUrl && (
                  <QRCodeCanvas
                    value={invitationUrl}
                    size={130} // ✅ slightly smaller improves scan
                    bgColor="#FFFFFF"
                    fgColor="#000000"
                    level="M"
                    marginSize={4} // ✅ border padding
                  />
                )}
              </div>

              {/* Save button (gradient) at bottom */}
              <button
                onClick={() => {
                  const qrCanvas = document.querySelector("canvas");
                  const link = document.createElement("a");
                  link.download = "invitation_qr.webp";
                  link.href = qrCanvas.toDataURL("image/png");
                  link.click();
                }}
                className="w-full py-1.5 text-sm font-semibold text-black"
                style={{
                  background:
                    "linear-gradient(180deg, #F8AF07 0%, #FFFC86 100%)",
                }}
              >
                {t("earn.share.save")}
              </button>
            </div>
          </div>

          {/* RIGHT: Link section vertically centered */}
          <div
            className="
            flex items-center w-full
            max-w-[58%]         /* default → iPhone SE & small phones */
            sm:max-w-[62%]      /* ≥640px → most modern phones (390–430 px) */
            md:max-w-[68%]      /* ≥768px → tablets or landscape */
          "
          >
            <div className="flex flex-col w-full">
              {/* row: 我的链接 + 更换 */}
              <div className="flex items-center justify-between">
                <span className="text-sm font-semibold">
                  {t("earn.share.myLink")}
                </span>
                <button
                  className="text-xs text-[#F8AF07]"
                  onClick={() => {
                    router.push("/promote/member");
                  }}
                >
                  {t("earn.share.switch")}
                </button>
              </div>

              {/* bordered link + copy */}
              <div className="mt-2 flex items-center gap-2 rounded-lg border border-white/30 px-3 py-4 w-full">
                <MarqueeTitle
                  speedSec={40}
                  title={invitationUrl}
                  maxWidth={300}
                />

                <button
                  type="button"
                  onClick={() => {
                    navigator.clipboard.writeText(invitationUrl);
                    toast.success(t("common.copied"));
                  }}
                  className="shrink-0 active:scale-95 transition"
                >
                  <Image
                    src={IMAGES.iconCopy}
                    alt="copy"
                    width={18}
                    height={18}
                    className="object-contain"
                  />
                </button>
              </div>

              <div className="flex items-center justify-between mt-5">
                <span className="text-sm font-semibold">
                  {t("earn.share.invitaionCode")}
                </span>
                {/* <button className="text-xs text-[#F8AF07]">
                  {t("earn.share.switch")}
                </button> */}
              </div>

              <div className="mt-2 flex items-center gap-2 rounded-lg border border-white/30 px-3 py-4">
                <span
                  className="flex-1 truncate text-[13px] text-white/85 min-w-0"
                  title={referralCode}
                >
                  {referralCode}
                </span>

                <button
                  type="button"
                  onClick={() => {
                    navigator.clipboard.writeText(referralCode);
                    toast.success(t("common.copied"));
                  }}
                  className="shrink-0 active:scale-95 transition"
                >
                  <Image
                    src={IMAGES.iconCopy}
                    alt="copy"
                    width={18}
                    height={18}
                    className="object-contain"
                  />
                </button>
              </div>
            </div>
          </div>
        </div>

        {/* SECOND ROW: 分享 + socials */}
        <div className="mt-4">
          <p className="mb-2 text-sm">{t("earn.share.shareTitle")}</p>
          <div className="flex items-center gap-3">
            {[
              { src: IMAGES.earn.iconFb, alt: "facebook" },
              { src: IMAGES.earn.iconTelegram, alt: "telegram" },
              { src: IMAGES.earn.iconWatapps, alt: "whatsapp" },
              { src: IMAGES.earn.iconWechat, alt: "wechat" },
            ].map((it) => (
              <button
                key={it.alt}
                onClick={() => handleShare(it.alt)}
                className="rounded-full py-1.5 active:scale-95 transition"
              >
                <Image
                  src={it.src}
                  alt={it.alt}
                  width={150}
                  height={40}
                  className="object-contain"
                />
              </button>
            ))}
          </div>
        </div>
      </div>

      {/* Tutorial card */}
      <div className="rounded-2xl p-1">
        <h3 className="mb-3 text-[15px] font-semibold">
          {t("earn.tutorial.title")}
        </h3>

        {/* summary row */}
        <div className="rounded-xl bg-[#0C255F] p-3 flex items-start gap-3">
          <div className="relative inline-flex items-center justify-center p-[2px]">
            {/* inner circle (avatar holder) */}
            <Image
              src={IMAGES.earn.avatarA}
              alt="avatar"
              width={80}
              height={80}
              className="rounded-full"
            />

            <Image
              src={IMAGES.earn.iconA}
              alt="iconN"
              width={26}
              height={26}
              className="absolute -bottom-1 -right-1"
            />
          </div>

          <div
            className="flex-1 text-[13px] leading-6 ml-4"
            dangerouslySetInnerHTML={{
              __html: isTutorialLoading ? "" : tutorialData?.data?.title || "",
            }}
          />
        </div>

        {/* diagram */}
        <div className="mt-3 overflow-hidden rounded-xl bg-[#0C255F]">
          {tutorialData?.data?.picture && (
            <Image
              src={`${process.env.NEXT_PUBLIC_IMAGE_URL}/${tutorialData.data.picture}`}
              alt="diagram"
              width={600}
              height={900}
              className="w-full h-auto object-cover"
              priority
            />
          )}
        </div>

        {/* note */}
        <div className="relative mt-1 flex justify-center">
          {/* dotted arrow pointing up */}
          <div className="items-center">
            <Image
              src={IMAGES.earn.arrowup}
              alt="arrow up"
              width={25}
              height={20}
              className="object-contain"
              priority
            />
          </div>

          {/* Card */}
        </div>

        <div className="relative w-full max-w-[480px] rounded-2xl bg-[#0C255F] px-4 pb-5 pt-14 text-center">
          {/* Avatar with gradient border */}
          <div className="absolute -top-10 left-1/2 -translate-x-1/2">
            <div className="relative inline-flex rounded-full p-[2px] bg-gradient-to-b from-[#F8AF07] to-[#FFFC86]">
              <Image
                src={IMAGES.earn.avatar}
                alt="avatar"
                width={70}
                height={70}
                className="rounded-full"
              />
              {/* N badge */}
              <Image
                src={IMAGES.earn.iconN}
                alt="N"
                width={26}
                height={26}
                className="absolute -bottom-1 -right-1"
              />
            </div>
          </div>

          {/* Text */}
          <div
            className="flex-1 text-[13px] leading-6 ml-4"
            dangerouslySetInnerHTML={{
              __html: isTutorialLoading
                ? ""
                : (tutorialData?.data?.slogan || "").replace(/\r?\n/g, "<br/>"),
            }}
          />
        </div>
      </div>

      {/* Rules (collapsible if you like later) */}
      <div className="rounded-2xl p-1">
        {/* <h3 className="text-[15px] font-semibold">{t("earn.rules.title")}</h3> */}
        <div
          className="flex-1 text-[13px] leading-6 ml-4"
          dangerouslySetInnerHTML={{
            __html: isTutorialLoading ? "" : tutorialData?.data?.desc || "",
          }}
        />
      </div>

      {showWeChatQR && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm">
          <div className="bg-[#0B1D48] rounded-2xl px-6 py-6 text-center max-w-[85%] w-[320px]">
            <p className="mb-4 text-sm text-white/85">
              {t("earn.share.wechatTip")}
            </p>

            <QRCodeCanvas
              value={invitationUrl}
              size={220} // ✅ bigger for easier scan
              bgColor="#FFFFFF"
              fgColor="#000000"
              level="H" // ✅ higher error correction for better recognition
              className="mx-auto rounded-md"
            />

            <button
              onClick={() => setShowWeChatQR(false)}
              className="mt-6 w-full rounded-full border border-[#FFC000] py-2.5 text-sm font-semibold text-[#FFC000] active:scale-95"
            >
              {t("common.close")}
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
