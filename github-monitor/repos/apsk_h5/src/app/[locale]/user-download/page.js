"use client";
import { useEffect, useState } from "react";
import Image from "next/image";
import { IMAGES } from "@/constants/images";
import { useTranslations } from "next-intl";
import { toast } from "react-hot-toast";
import {
  COOKIE_AGENT,
  COOKIE_REFERRAL,
  COOKIE_SHOP,
  setCookie,
} from "@/utils/cookie";
import { useRouter } from "next/navigation";
import { useGetVersionListQuery } from "@/services/commonApi";

export default function UserDownloadPage() {
  const t = useTranslations();
  const router = useRouter();
  const [agentCode, setAgentCode] = useState("");
  const [referralCode, setReferralCode] = useState("");
  const [shopCode, setShopCode] = useState("");

  // prevent auto-load
  const { data, refetch } = useGetVersionListQuery(
    {},
    {
      skip: false, // allow initial query
      refetchOnMountOrArgChange: false,
    },
  );
  const ios = data?.data?.find((v) => v.platform === "ios");
  const android = data?.data?.find((v) => v.platform === "android");

  useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    setAgentCode(params.get("agentCode") || "");
    setReferralCode(params.get("referralCode") || "");
    setShopCode(params.get("shopCode") || "");
  }, []);

  useEffect(() => {
    refetch(); // manual fetch once
  }, []);

  const handleCopy = async (platform) => {
    const hasReferral = Boolean(referralCode);
    const hasAgent = Boolean(agentCode);
    const hasShop = Boolean(shopCode);
    const text = `ag[${agentCode}][${referralCode}]`;
    if (hasAgent || hasReferral) {
      if (platform === "web") {
        if (hasReferral) setCookie(COOKIE_REFERRAL, referralCode);

        if (hasAgent) setCookie(COOKIE_AGENT, agentCode);
      } else {
        try {
          await navigator.clipboard.writeText(text);
        } catch {}
      }
    }

    if (platform === "web") {
      try {
        await router.push("/login");
        await new Promise((r) => setTimeout(r, 500));
        router.push("/register");
      } catch {}
      return;
    } else if (platform === "android") {
      window.open(android?.url ?? "#", "_blank");
    } else if (platform === "ios") {
      window.open(ios?.url ?? "#", "_blank");
    }
  };

  return (
    <div className="relative flex justify-center bg-black text-white overflow-hidden">
      {/* ✅ Fixed mobile viewport (420 × 1034 px) */}
      <div className="relative w-[480px] min-h-[120vh] md:min-h-[140vh] bg-[#000] text-white overflow-hidden flex flex-col items-center">
        {/* 🔹 Background Top */}
        <div className="absolute top-0 left-0 w-full z-0">
          <Image
            src={IMAGES.user.bgTop}
            alt="bg-top"
            width={800}
            height={400}
            className="w-full h-auto object-cover"
            priority
          />
        </div>

        <div className="absolute top-0 left-0 w-full z-0">
          <Image
            src={IMAGES.user.cash}
            alt="cash"
            width={800}
            height={800}
            className="absolute inset-0 w-full h-auto object-contain opacity-90"
            priority
          />
        </div>

        {/* 🔹 Conetnt */}
        <div className="relative z-10 pt-8 flex flex-col items-center justify-center">
          {/* 🔹 Logo foreground */}
          <div className="relative z-20">
            <Image
              src={IMAGES.user.logo}
              alt="logo"
              width={240}
              height={80}
              className="object-contain"
              priority
            />
          </div>

          {/* 👩‍🦰 Girl section */}
          <div className="relative z-10 -mt-[-75%] flex justify-center overflow-visible">
            <Image
              src={IMAGES.user.girl}
              alt="promo"
              width={1680}
              height={2372}
              className="w-screen h-auto object-contain -translate-y-[50%]"
              priority
            />

            <div
              className="absolute bottom-0 left-0 w-full h-[280px] bg-cover bg-center bg-no-repeat flex flex-col items-center justify-start px-4"
              style={{ backgroundImage: `url(${IMAGES.user.bgBottom})` }}
            >
              <div className="flex mt-10 flex-col items-center w-full space-y-4 -translate-y-[10%] sm:-translate-y-[6%]">
                {/* iOS */}
                {/* <button
                  onClick={() => handleCopy("ios")}
                  className="w-[82%] active:scale-95 transition-transform"
                >
                  <Image
                    src={IMAGES.user.apppleDownload}
                    alt="apple-download"
                    width={900}
                    height={180}
                    className="w-full h-auto object-contain"
                    priority
                  />
                </button> */}

                {/* Android */}
                <button
                  onClick={() => handleCopy("android")}
                  className="w-[82%] active:scale-95 transition-transform"
                >
                  <Image
                    src={IMAGES.user.androidDownload}
                    alt="android-download"
                    width={900}
                    height={180}
                    className="w-full h-auto object-contain"
                    priority
                  />
                </button>

                {/* Web */}
                <button
                  onClick={() => handleCopy("web")}
                  className="w-[82%] active:scale-95 transition-transform"
                >
                  <Image
                    src={IMAGES.user.buttonWeb}
                    alt="button-web"
                    width={1200}
                    height={240}
                    className="w-full h-auto object-contain"
                    priority
                  />
                </button>
              </div>
              {agentCode && (
                <div className="mt text-center text-xs sm:text-sm text-white/85 space-y-1 hidden">
                  {referralCode && (
                    <p>
                      {t("user.referralCode")}:{" "}
                      <span className="text-yellow-400">{referralCode}</span>
                    </p>
                  )}

                  {agentCode && (
                    <p>
                      {t("user.agentCode")}:{" "}
                      <span className="text-yellow-400">{agentCode}</span>
                    </p>
                  )}
                </div>
              )}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
