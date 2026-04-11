"use client";

import { useMemo, useRef, useState, useEffect } from "react";
import Image from "next/image";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useTranslations } from "next-intl";
import { toast } from "react-hot-toast";

import { IMAGES } from "@/constants/images";
import SubmitButton from "@/components/shared/SubmitButton";

// ✅ RTK Query hooks (from your userApi)
import {
  useGenerateGoogle2faQrQuery,
  useVerifyGoogle2faMutation,
} from "@/services/authApi";
import { getMemberInfo } from "@/utils/utility";

export default function BindGoogleAuth() {
  const t = useTranslations();
  const router = useRouter();

  // OTP state (6 boxes)
  const [otp, setOtp] = useState(Array(6).fill(""));
  const refs = useRef([]);
  const onChange = (idx, v) => {
    const val = v.replace(/\D/g, "").slice(0, 1);
    const next = [...otp];
    next[idx] = val;
    setOtp(next);
    if (val && idx < 5) refs.current[idx + 1]?.focus();
  };
  const onKeyDown = (idx, e) => {
    if (e.key === "Backspace" && !otp[idx] && idx > 0)
      refs.current[idx - 1]?.focus();
  };
  const onPaste = (e) => {
    e.preventDefault();
    const text = e.clipboardData.getData("text").replace(/\D/g, "").slice(0, 6);
    if (!text) return;
    const next = Array(6)
      .fill("")
      .map((_, i) => text[i] ?? "");
    setOtp(next);
    refs.current[Math.min(text.length, 5)]?.focus();
  };

  // member id from cookie
  const info = getMemberInfo();
  const memberId = info?.member_id ? String(info.member_id) : "";

  // Fetch { secret, qr } + member (bind status) in one call
  const {
    data: qrRes,
    isFetching: isQrFetching,
    isError: isQrError,
  } = useGenerateGoogle2faQrQuery({ member_id: memberId }, { skip: !memberId });

  const member = qrRes?.member;
  const gaBound = useMemo(() => {
    if (!member) return false;
    return !!member.bindgoogle || member.GAstatus === 1;
  }, [member]);

  const secret = !gaBound ? qrRes?.data?.secret : "";
  const otpauth = !gaBound ? qrRes?.data?.qr : "";
  const qrSrc = otpauth
    ? `https://api.qrserver.com/v1/create-qr-code/?size=144x144&data=${encodeURIComponent(
        otpauth
      )}`
    : "";

  const [verifyGoogle2fa, { isLoading: isVerifying }] =
    useVerifyGoogle2faMutation();

  const handleCopySecret = async () => {
    if (!secret) return;
    try {
      await navigator.clipboard.writeText(secret);
      toast.success(t("ga.copied"));
    } catch {
      /* ignore */
    }
  };

  const handleNext = async () => {
    if (!memberId) {
      toast.error(t("ga.errors.noMember"));
      return;
    }
    const code = otp.join("");
    if (!/^\d{6}$/.test(code)) {
      toast.error(t("ga.errors.sixDigits"));
      return;
    }
    try {
      // bind=1 to enable, bind=0 to disable
      const body = { bind: gaBound ? 0 : 1, code, member_id: memberId };
      const res = await verifyGoogle2fa(body).unwrap();
      if (res?.status) {
        toast.success(gaBound ? t("ga.disableSuccess") : t("ga.enableSuccess"));
        router.replace("/security");
      } else {
        toast.error(res?.message || t("ga.errors.failed"));
      }
    } catch (err) {
      const msg = err?.data?.message || t("ga.errors.failed");
      toast.error(msg);
    }
  };

  // Optional: surface initial load errors
  useEffect(() => {
    if (isQrError) toast.error(t("ga.errors.failed"));
  }, [isQrError, t]);

  return (
    <div className="mx-auto max-w-[480px] min-h-dvh bg-[#01133C] text-white">
      {/* Header (spec) */}
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
          {t("ga.title")}
        </h1>
      </div>

      {/* Big page title */}
      <div className="px-4">
        <h2 className="mt-1 text-[22px] font-bold">
          {!gaBound ? t("ga.h1Bind") : t("ga.h1Unbind")}
        </h2>
      </div>

      {/* Card */}
      <div className="mx-4 mt-3 rounded-2xl bg-[#0B204F] shadow-lg text-white">
        {/* Step 1: Download app */}
        <div className="px-4 py-3 border-b border-white/15">
          <div className="text-sm flex items-center gap-2">
            <span className="text-white/70">{t("ga.step1")}</span>
            <span className="text-white/70">{t("ga.download")}</span>
            <Image
              src={IMAGES.safety.googleStar}
              alt="Google Authenticator"
              width={20}
              height={20}
              className="object-contain"
            />
            <span className="font-semibold text-[#FFC83A] underline">
              {t("ga.googleAuthenticator")}
            </span>
            <span className="ml-1 text-xs text-white/50">
              {t("ga.openTip")}
            </span>
          </div>

          {/* Official store badges */}
          <div className="mt-3 flex justify-center items-center gap-3">
            <Link
              href="https://play.google.com/store/apps/details?id=com.google.android.apps.authenticator2"
              target="_blank"
              rel="noopener noreferrer"
              aria-label="Google Play"
            >
              <Image
                src={IMAGES.safety.google}
                alt="Get it on Google Play"
                width={160}
                height={48}
                className="object-contain"
                priority
              />
            </Link>
            <Link
              href="https://apps.apple.com/app/google-authenticator/id388497605"
              target="_blank"
              rel="noopener noreferrer"
              aria-label="App Store"
            >
              <Image
                src={IMAGES.safety.appstore}
                alt="Download on the App Store"
                width={160}
                height={48}
                className="object-contain"
                priority
              />
            </Link>
          </div>
        </div>

        {/* Step 2: QR / Secret (only when UNBOUND) */}
        {!gaBound && (
          <div className="px-4 py-4 border-b border-white/15">
            <div className="text-sm text-white/90">
              <span className="text-white/70 mr-2">{t("ga.step2")}</span>
              {t("ga.addQrTip")}
            </div>

            {/* QR box (dynamic) */}
            <div className="mt-4 mx-auto w-44 h-44 rounded-xl bg-[#10295F] grid place-items-center">
              <div className="relative w-36 h-36">
                {qrSrc && !isQrFetching ? (
                  <Image
                    fill
                    src={qrSrc}
                    alt="QR"
                    className="w-full h-full object-contain"
                  />
                ) : (
                  <div className="w-full h-full object-contain"></div>

                  // <Image
                  //   src={IMAGES.safety.qr}
                  //   alt="QR placeholder"
                  //   fill
                  //   className="object-contain opacity-60"
                  //   sizes="144px"
                  //   priority
                  // />
                )}
              </div>
            </div>

            {/* Secret row */}
            <div className="mt-3 flex items-center justify-center gap-2">
              <span className="text-[#FFC83A]">{t("ga.copyPrefix")}</span>
              <code className="px-2 py-1 rounded-md bg-black/30 text-sm">
                {secret || "••••••••••••"}
              </code>
              <button
                onClick={handleCopySecret}
                disabled={!secret}
                className="ml-1 inline-flex items-center justify-center rounded-md border border-white/20 px-2 py-1 text-xs active:scale-95 disabled:opacity-40"
                aria-label={t("ga.copy")}
                title={t("ga.copy")}
              >
                <span className="inline-block w-4 h-4 rounded-[3px] border border-white/50 relative">
                  <span className="absolute -right-1 -bottom-1 w-4 h-4 rounded-[3px] border border-white/50"></span>
                </span>
              </button>
            </div>
          </div>
        )}

        {/* Step 3: Enter code (bind or unbind) */}
        <div className="px-4 py-4">
          <div className="text-sm">
            <span className="text-white/70 mr-2">{t("ga.step3")}</span>
            {!gaBound ? t("ga.enterSixDigits") : t("ga.enterSixDigitsToUnbind")}
          </div>

          {/* OTP inputs */}
          <div className="mt-3 grid grid-cols-6 gap-3">
            {otp.map((v, i) => (
              <input
                key={i}
                ref={(el) => (refs.current[i] = el)}
                inputMode="numeric"
                pattern="[0-9]*"
                maxLength={1}
                value={v}
                onChange={(e) => onChange(i, e.target.value)}
                onKeyDown={(e) => onKeyDown(i, e)}
                onPaste={onPaste}
                className="h-16 rounded-xl bg-[#10295F] text-center text-2xl font-semibold outline-none ring-1 ring-white/15 focus:ring-2 focus:ring-[#FFC83A]"
              />
            ))}
          </div>
        </div>
      </div>

      {/* Bottom button */}
      <div className="px-4">
        <SubmitButton
          onClick={handleNext}
          className="mt-4"
          disabled={isVerifying}
        >
          {!gaBound ? t("ga.actions.bindNow") : t("ga.actions.unbindNow")}
        </SubmitButton>
      </div>
    </div>
  );
}
