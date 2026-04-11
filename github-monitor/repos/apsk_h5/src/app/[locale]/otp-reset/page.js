"use client";

import Image from "next/image";
import { useTranslations } from "next-intl";
import { useRouter } from "next/navigation";
import { useContext, useState } from "react";

import { IMAGES } from "@/constants/images";
import SubmitButton from "@/components/shared/SubmitButton";
import { UIContext } from "@/contexts/UIProvider";

export default function ResetVerifyPage() {
  const t = useTranslations();
  const router = useRouter();
  const { showPhonePicker } = useContext(UIContext);

  const [dial, setDial] = useState("+60");

  return (
    <div className="relative mx-auto max-w-[480px] min-h-dvh bg-[#00143D] text-white px-4 pb-8">
      {/* Background */}
      <Image
        src={IMAGES.login.bg}
        alt="bg"
        fill
        priority
        className="object-cover"
      />
      <div className="absolute inset-0 bg-black/70" />

      <div className="relative z-10">
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
            {t("reset.title")}
          </h1>
        </div>

        {/* Logo */}
        <div className="mt-3 flex justify-center mb-30">
          <Image
            src={IMAGES.login.logo}
            alt="logo"
            width={300}
            height={300}
            className="object-contain"
          />
        </div>
        {/* Title + subtitle */}
        <div className="mt-6">
          <p className="text-lg font-semibold">{t("reset.title")}</p>
          <p className="mt-2 text-sm text-white/70">{t("reset.subtitle")}</p>
        </div>

        {/* Phone row */}
        <div className="mt-5 grid grid-cols-[110px_1fr] gap-3">
          {/* Country code */}
          <button
            onClick={() =>
              showPhonePicker({
                value: dial,
                onSelect: (item) => setDial(item.dial),
              })
            }
          >
            <div className="rounded-xl border border-white/30 bg-black/40 px-3 py-3">
              <div className="flex items-center justify-between">
                <span className="text-sm">{dial}</span>
                <Image
                  src={IMAGES.yellowDown}
                  alt="down"
                  width={10}
                  height={10}
                  className="object-contain"
                />
              </div>
            </div>
          </button>

          {/* Phone input */}
          <div className="rounded-xl border border-white/30 bg-black/40 px-3 py-3">
            <input
              type="tel"
              placeholder={t("reset.phone")}
              className="w-full bg-transparent text-sm outline-none placeholder:text-[#F8AF07]/80"
            />
          </div>
        </div>

        {/* Submit */}
        <div className="mt-6">
          <SubmitButton
            onClick={() => {
              router.push("/otp-reset/verify");
            }}
          >
            {t("reset.verify")}
          </SubmitButton>
        </div>
      </div>
    </div>
  );
}
