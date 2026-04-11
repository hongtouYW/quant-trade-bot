"use client";
import { useEffect, useState } from "react";
import Image from "next/image";
import { IMAGES } from "@/constants/images";
import { useTranslations } from "next-intl";
import { toast } from "react-hot-toast";
import {
  clearCookie,
  COOKIE_AGENT,
  COOKIE_SHOP,
  setCookie,
} from "@/utils/cookie";
import { useRouter } from "next/navigation";
import { useGetVersionListQuery } from "@/services/commonApi";

export default function ShopDownloadPage() {
  const t = useTranslations();
  const [shopCode, setshopCode] = useState("");

  const router = useRouter();

  // prevent auto-load
  const { data, refetch } = useGetVersionListQuery(
    {},
    {
      skip: false, // allow initial query
      refetchOnMountOrArgChange: false,
    },
  );

  useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    setshopCode(params.get("shopCode") || "");
  }, []);

  useEffect(() => {
    refetch(); // manual fetch once
  }, []);

  const ios = data?.data?.find((v) => v.platform === "ios");
  const android = data?.data?.find((v) => v.platform === "android");

  const handleCopy = async (platform) => {
    const hasShop = Boolean(shopCode);
    const text = `shop[${shopCode}]`;

    // only copy/store if hasShop
    if (hasShop) {
      if (platform === "web") {
        clearCookie(COOKIE_AGENT);
        setCookie(COOKIE_SHOP, shopCode);
      } else {
        try {
          await navigator.clipboard.writeText(text);
        } catch {}
      }
    }

    // navigation / download
    if (platform === "web") {
      try {
        await router.push("/login");
        await new Promise((r) => setTimeout(r, 500));
        await router.push("/register");
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
      <div
        className="mx-auto max-w-[480px] min-h-[120vh] md:min-h-[140vh] text-white bg-[#00143D] bg-cover bg-center bg-no-repeat flex flex-col items-center"
        style={{
          backgroundImage: `url(${IMAGES.agent.bg})`,
        }}
      >
        {/* 🔹 Background Top */}
        <div
          className="absolute top-0 left-0 w-full z-0 bg-cover bg-center bg-no-repeat"
          style={{
            backgroundImage: `url(${IMAGES.agent.bgTop})`,
          }}
        >
          <Image
            src={IMAGES.agent.card}
            alt="bg-top"
            width={800}
            height={400}
            className="w-full h-auto object-cover opacity-90"
            priority
          />
        </div>

        {/* 🔹 Conetnt */}
        <div className="relative z-10 pt-8 flex flex-col items-center justify-center">
          {/* 🔹 Logo foreground */}
          <div className="relative z-20">
            <Image
              src={IMAGES.agent.logo}
              alt="logo"
              width={240}
              height={80}
              className="object-contain"
              priority
            />
          </div>

          {/* 👩‍🦰 Card + Girl + Title overlay */}
          <div className="relative w-full mb-2 mt-2">
            {/* 🃏 Card background behind girl */}

            {/* 👩 Girl (above card) */}
            <div className="relative z-10">
              <Image
                src={IMAGES.agent.girl}
                alt="girl"
                width={300}
                height={400}
                className="w-[100%] mx-auto h-auto object-contain"
                priority
              />
            </div>

            {/* 🏷️ Title (overlaps bottom part of girl) */}
            <div className="absolute bottom-[-10%] left-1/2 -translate-x-1/2 w-[100%] z-20">
              <Image
                src={IMAGES.agent.title}
                alt="title"
                width={600}
                height={160}
                className="w-full h-auto object-contain"
                priority
              />
            </div>
          </div>

          {/* 💰 Discount below */}
          <div
            className="relative w-full mb-2 mt-6 bg-cover bg-center bg-no-repeat py-6 flex justify-center"
            style={{
              backgroundImage: `url(${IMAGES.agent.greenCard})`,
            }}
          >
            <Image
              src={IMAGES.agent.discount}
              alt="discount"
              width={700}
              height={180}
              className="w-[100%] h-auto object-contain"
              priority
            />
          </div>
        </div>

        <div className="flex flex-col w-full items-center space-y-3 mt-3">
          {/* iOS */}
          {/* <button
            onClick={() => handleCopy("ios")}
            className="flex w-full justify-center rounded-full active:scale-95"
          >
            <Image
              src={IMAGES.agent.apppleDownload}
              alt="apple-download"
              width={300}
              height={60}
              className="object-contain"
            />
          </button> */}

          {/* Android */}
          <button
            onClick={() => handleCopy("android")}
            className="flex w-full justify-center rounded-full active:scale-95"
          >
            <Image
              src={IMAGES.agent.androidDownload}
              alt="android-download"
              width={300}
              height={60}
              className="object-contain"
            />
          </button>

          {/* Web */}
          <button
            onClick={() => handleCopy("web")}
            className="flex w-full justify-center rounded-full active:scale-95"
          >
            <Image
              src={IMAGES.agent.buttonWeb}
              alt="button-web"
              width={300}
              height={60}
              className="object-contain"
            />
          </button>
        </div>

        {shopCode && (
          <div className="mt text-center text-xs sm:text-sm text-white/85 space-y-1 hidden">
            {shopCode && (
              <p>
                {t("agent.yourCode")}:{" "}
                <span className="text-yellow-400">{shopCode}</span>
              </p>
            )}
          </div>
        )}
      </div>
    </div>
  );
}
