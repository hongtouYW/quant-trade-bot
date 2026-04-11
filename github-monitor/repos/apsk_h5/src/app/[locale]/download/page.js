"use client";

import Image from "next/image";
import Link from "next/link";
import { IMAGES } from "@/constants/images";
import { useTranslations } from "next-intl";
import { useRouter } from "next/navigation";
import { useContext, useEffect } from "react";
import { UIContext } from "@/contexts/UIProvider";
import { useGetVersionListQuery } from "@/services/commonApi";
import QRCode from "react-qr-code";
export default function AppDownloadPage() {
  const t = useTranslations();
  const router = useRouter();
  const { setConfirmConfig } = useContext(UIContext);

  // prevent auto-load
  const { data, refetch } = useGetVersionListQuery(
    {},
    {
      skip: false, // allow initial query
      refetchOnMountOrArgChange: false,
    },
  );
  useEffect(() => {
    refetch(); // manual fetch once
  }, []);

  const ios = data?.data?.find((v) => v.platform === "ios");
  const android = data?.data?.find((v) => v.platform === "android");

  return (
    <div className="mx-auto max-w-[480px] min-h-dvh bg-[#00143D] text-white px-4 py-6">
      {/* Header */}
      <div className="relative flex items-center mb-6">
        <button onClick={() => router.back()} className="z-10 cursor-pointer">
          <Image
            src={IMAGES.arrowLeft}
            alt="back"
            width={20}
            height={20}
            className="object-contain"
          />
        </button>
        <h1 className="absolute inset-x-0 text-lg font-semibold text-center">
          {t("appdownload.title")}
        </h1>
      </div>

      {/* QR + Versions */}
      <div className="bg-[#162344] rounded-lg p-6 flex flex-col items-center text-center">
        <p className="text-sm text-white/80 mb-4">
          {t("appdownload.instruction")}
        </p>

        <div className="grid grid-cols-1 gap-6 w-full">
          {/* iOS */}
          {ios?.url && (
            <div className="flex flex-col items-center">
              <div className="bg-white p-2 rounded mb-2">
                <QRCode
                  value={ios.url}
                  size={120}
                  bgColor="#FFFFFF"
                  fgColor="#000000"
                />
              </div>

              <p className="text-xs text-white/70">
                {t("appdownload.iosVersion", {
                  version: ios?.latest_version ?? "1.0",
                })}
              </p>

              <Link href={ios?.url ?? "#"} target="_blank">
                <Image
                  src={IMAGES.appdownload.apple}
                  alt="App Store"
                  width={140}
                  height={42}
                  className="object-contain mt-2"
                />
              </Link>
            </div>
          )}

          {/* Android */}
          <div className="flex flex-col items-center">
            {android?.url && (
              <div className="bg-white p-2 rounded mb-2">
                <QRCode
                  value={android.url}
                  size={120}
                  bgColor="#FFFFFF"
                  fgColor="#000000"
                />
              </div>
            )}

            <p className="text-xs text-white/70">
              {t("appdownload.androidVersion", {
                version: android?.latest_version ?? "1.0",
              })}
            </p>

            <Link href={android?.url ?? "#"} target="_blank">
              <Image
                src={IMAGES.appdownload.android}
                alt="Google Play"
                width={140}
                height={42}
                className="object-contain mt-2"
              />
            </Link>
          </div>
        </div>
      </div>

      <button
        onClick={() => {
          router.push("/feedback");
        }}
        className={`w-[250px] mt-10 mx-auto flex h-10 p-5 rounded-full text-sm font-medium text-[#F8AF07] border border-[#F8AF07] justify-center items-center`}
      >
        {t(`setting.feedback`)}
      </button>
    </div>
  );
}
