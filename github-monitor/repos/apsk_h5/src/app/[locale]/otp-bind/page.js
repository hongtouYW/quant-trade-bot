"use client";

import Image from "next/image";
import { useTranslations } from "next-intl";
import { useRouter } from "next/navigation";
import { useContext, useEffect, useState } from "react";

import { IMAGES } from "@/constants/images";
import { EyeYellowSlashIcon, EyeYellowIcon } from "@/components/shared/EyeIcon";
import SubmitButton from "@/components/shared/SubmitButton";
import { UIContext } from "@/contexts/UIProvider";

import {
  useBindRandomUserOtpMutation,
  useLoginMutation,
  useSendRandomUserOtpMutation,
} from "@/services/authApi";
import { useDispatch } from "react-redux";
import { setOtpPayload } from "@/store/slice/otpVerifySlice";
import { delayedRedirect, extractError } from "@/utils/utility";
import { toast } from "react-hot-toast";
import { useTransferOutMutation } from "@/services/transactionApi";
import { clearAllCookie, setCookie } from "@/utils/cookie";
import { useGameStore } from "@/store/zustand/gameStore";
import { useProviderStore } from "@/store/zustand/providerStore";
import { COOKIE_NAMES, LOCAL_STORAGE_NAMES } from "@/constants/cookies";

export default function OtpBindPage() {
  const t = useTranslations();
  const router = useRouter();
  const { showPhonePicker, setLoading } = useContext(UIContext);

  const [dial, setDial] = useState("+60");
  const [phone, setPhone] = useState("");
  const [otp, setOtp] = useState("");

  const [errors, setErrors] = useState({});
  const dispatch = useDispatch();

  const [sendRandomUserOtp] = useSendRandomUserOtpMutation();
  const [bindRandomUserOtp, { isLoading }] = useBindRandomUserOtpMutation();
  const { prevGameMemberId } = useProviderStore();
  const { clearPrevGameMemberId, clearPrevProviderId } =
    useProviderStore.getState();
  const { reset: resetGame } = useGameStore.getState();
  const [seconds, setSeconds] = useState(0);
  const [hasSent, setHasSent] = useState(false);
  const [memberId, setMemberId] = useState(null);

  useEffect(() => {
    const id = sessionStorage.getItem("otp_bind_member_id");
    if (id) {
      setMemberId(id);
    }
  }, []);

  useEffect(() => {
    return () => {
      sessionStorage.removeItem("otp_bind_member_id");
    };
  }, []);

  useEffect(() => {
    if (seconds <= 0) return;

    const timer = setInterval(() => {
      setSeconds((s) => s - 1);
    }, 1000);

    return () => clearInterval(timer);
  }, [seconds]);

  const handleBind = async (e) => {
    e.preventDefault();

    const cleanPhone = phone?.replace(/\s+/g, "").trim() || "";

    const newErrors = {};

    if (!cleanPhone) {
      newErrors.phone = t("bind.errors.phoneRequired");
    }

    if (!otp || otp.length !== 6) {
      newErrors.otp = t("bind.errors.otpRequired");
    }

    setErrors(newErrors);
    if (Object.keys(newErrors).length !== 0) return;

    if (!memberId) {
      toast.error("Invalid session");
      return;
    }

    setLoading(true);

    try {
      const payload = {
        member_id: memberId,
        phone: `${dial.replace("+", "")}${cleanPhone}`,
        code: otp,
      };

      const result = await bindRandomUserOtp(payload).unwrap();

      if (result?.status) {
        const { member_id, member_login, member_name, phone, prefix } =
          result.data;

        const { access_token, refresh_token } = result.token;

        clearAllCookie();

        setCookie(
          COOKIE_NAMES.MEMBER_INFO,
          JSON.stringify({
            member_id,
            member_login,
            member_name,
            phone,
            prefix,
          }),
        );

        localStorage.setItem("selected_phone_country", dial);

        // cookies
        setCookie(COOKIE_NAMES.BEARER_TOKEN, access_token);
        setCookie(COOKIE_NAMES.REFRESH_TOKEN, refresh_token);

        // localStorage backup
        localStorage.setItem(COOKIE_NAMES.BEARER_TOKEN, access_token);
        localStorage.setItem(COOKIE_NAMES.REFRESH_TOKEN, refresh_token);

        toast.success(result.message);

        localStorage.setItem(LOCAL_STORAGE_NAMES.JUST_LOGIN, "1");

        // clear temp session
        sessionStorage.removeItem("otp_bind_member_id");

        delayedRedirect(router, "/");
      }
    } catch (err) {
      const result = extractError(err);

      if (result.type === "validation") {
        setErrors(result.fieldErrors);
      } else {
        toast.error(t("bind.invalidCode"));
      }
    } finally {
      setLoading(false);
    }
  };

  const sendCode = async (e) => {
    e.preventDefault();

    const cleanPhone = phone?.replace(/\s+/g, "").trim() || "";

    const newErrors = {};
    if (!cleanPhone) {
      newErrors.phone = t("bind.errors.phoneRequired");
    }

    setErrors(newErrors);
    if (Object.keys(newErrors).length !== 0) return;

    if (!memberId) {
      toast.error("Invalid session");
      return;
    }

    setLoading(true);

    try {
      const payload = {
        member_id: memberId,
        phone: `${dial.replace("+", "")}${cleanPhone}`,
      };

      const result = await sendRandomUserOtp(payload).unwrap();

      if (result?.status) {
        toast.success(result.message);
        setHasSent(true);
        setSeconds(300);
      }
    } catch (err) {
      const result = extractError(err);

      if (result.type === "validation") {
        setErrors(result.fieldErrors);
      } else {
        toast.error(t("bind.sendCodeFailed"));
      }
    } finally {
      setLoading(false);
    }
  };
  return (
    <div className="relative mx-auto max-w-[480px] min-h-dvh text-white px-4 pb-8">
      <Image
        src={IMAGES.login.bg}
        alt="bg"
        fill
        priority
        className="object-cover"
      />
      <div className="absolute inset-0 bg-black/70" />

      <form onSubmit={handleBind}>
        <div className="relative z-10">
          {/* Header */}
          <div className="relative flex items-center h-14">
            <button
              onClick={() => router.back()}
              className="z-10 cursor-pointer"
              type="button"
            >
              <Image
                src={IMAGES.arrowLeft}
                alt="back"
                width={22}
                height={22}
                className="object-contain"
              />
            </button>
          </div>

          {/* Logo */}
          <div className="mt-6 mb-24 flex justify-center">
            <div className="h-[120px] w-[300px] relative">
              <Image
                src={IMAGES.login.logo}
                alt="logo"
                fill
                priority
                className="object-contain"
              />
            </div>
          </div>

          {/* Title */}
          <div>
            <p className="text-lg font-semibold">{t("bind.title")}</p>
            <p className="mt-2 text-sm text-white/70">{t("bind.subtitle")}</p>
          </div>

          {/* Phone */}
          <div className="mt-6 grid grid-cols-[110px_1fr] gap-3">
            <button
              type="button"
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
                  />
                </div>
              </div>
            </button>

            <div className="rounded-xl border border-white/30 bg-black/40 px-3 py-3">
              <input
                type="tel"
                value={phone}
                onChange={(e) => setPhone(e.target.value)}
                placeholder={t("bind.phone")}
                className="w-full bg-transparent text-sm outline-none placeholder:text-[#F8AF07]/80"
              />
            </div>
          </div>

          {errors.phone && (
            <p className="mt-1 text-[13px] text-red-400">{errors.phone}</p>
          )}

          {/* OTP */}
          <div className="mt-4 rounded-xl border border-white/30 bg-black/40 px-3 py-3">
            <div className="flex items-center justify-between gap-3">
              <input
                type="text"
                inputMode="numeric"
                autoComplete="one-time-code"
                maxLength={6}
                value={otp}
                onChange={(e) => {
                  const value = e.target.value.replace(/\D/g, "").slice(0, 6);
                  setOtp(value);
                }}
                placeholder={t("bind.otp")}
                className="flex-1 bg-transparent text-sm outline-none placeholder:text-[#F8AF07]/80"
              />

              {seconds > 0 ? (
                <span className="text-white/60 text-sm whitespace-nowrap">
                  {seconds}s
                </span>
              ) : (
                <button
                  type="button"
                  onClick={sendCode}
                  className="text-[#FFC000] text-sm whitespace-nowrap active:opacity-80"
                >
                  {hasSent ? t("bind.resendCode") : t("bind.sendCode")}
                </button>
              )}
            </div>
          </div>

          {errors.otp && (
            <p className="mt-1 text-[13px] text-red-400">{errors.otp}</p>
          )}

          {/* Submit */}
          <div className="mt-6">
            <SubmitButton type="submit" disabled={isLoading}>
              {t("bind.confirm")}
            </SubmitButton>
          </div>
        </div>
      </form>
    </div>
  );
}
