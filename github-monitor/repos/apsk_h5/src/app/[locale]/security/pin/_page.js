"use client";

import { useContext, useState } from "react";
import { useRouter } from "next/navigation";
import { useTranslations } from "next-intl";
import Image from "next/image";
import { IMAGES } from "@/constants/images";
import { UIContext } from "@/contexts/UIProvider";
import SubmitButton from "@/components/shared/SubmitButton";
import { toast } from "react-hot-toast";
export default function OtpVerification() {
  const t = useTranslations();
  const router = useRouter();

  const [otp, setOtp] = useState(Array(6).fill(""));

  const handleChange = (value, index) => {
    if (/^[0-9]?$/.test(value)) {
      const newOtp = [...otp];
      newOtp[index] = value;
      setOtp(newOtp);

      // auto focus next
      if (value && index < 5) {
        document.getElementById(`otp-${index + 1}`).focus();
      }
    }
  };

  return (
    <div className="min-h-dvh bg-[#01133C] text-white px-6">
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
          {t("otp.title")}
        </h1>
      </div>

      {/* Content */}
      <div className="mt-10 text-center">
        <h2 className="text-xl font-bold">{t("otp.enterCode")}</h2>
        <p className="mt-2 text-sm text-white/70">
          {t("otp.sentTo", { phone: "**** **** 8686" })}
        </p>

        {/* OTP Inputs */}
        <div className="mt-6 flex justify-center gap-2">
          {otp.map((digit, i) => (
            <input
              key={i}
              id={`otp-${i}`}
              type="text"
              inputMode="numeric"
              maxLength={1}
              value={digit}
              onChange={(e) => handleChange(e.target.value, i)}
              className="w-12 h-14 text-center text-lg font-bold rounded-md border border-white/30 bg-transparent focus:border-yellow-400 outline-none"
            />
          ))}
        </div>

        {/* Resend */}
        <button className="mt-4 text-xs text-yellow-400">
          {t("otp.resend", { seconds: 300 })}
        </button>

        {/* Submit */}
        <div className=" pb-6 pt-6">
          <SubmitButton
            onClick={() => {
              // TODO: send to backend
              toast.error(t("otp.bindSuccess"));

              // wait 1.5s then go back
              router.back();
              router.back();
            }}
          >
            {t("otp.verify")}
          </SubmitButton>
        </div>
      </div>
    </div>
  );
}
