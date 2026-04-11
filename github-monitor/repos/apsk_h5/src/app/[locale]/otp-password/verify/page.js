"use client";

import Image from "next/image";
import { useTranslations } from "next-intl";
import { useRouter } from "next/navigation";
import { useContext, useEffect, useRef, useState } from "react";
import { useDispatch, useSelector } from "react-redux";
import toast from "react-hot-toast";

import { IMAGES } from "@/constants/images";
import SubmitButton from "@/components/shared/SubmitButton";
import { delayedRedirect, extractError, maskPhone } from "@/utils/utility";
import { UIContext } from "@/contexts/UIProvider";
import { setResetPwdPayload } from "@/store/slice/resetPwdSlice";
import { useVerifyPhoneForResetMutation } from "@/services/authApi";
import { useResetPasswordOtpMutation } from "@/services/otpApi";

export default function VerifyCodePage() {
  const dispatch = useDispatch();
  const t = useTranslations();
  const router = useRouter();

  const { phone, dial, otpCode, memberId } = useSelector((s) => s.resetPwd);

  const { setLoading } = useContext(UIContext);

  const [verifyResetOtp] = useResetPasswordOtpMutation();
  const [resendPhone] = useVerifyPhoneForResetMutation();

  const length = 6;
  const [code, setCode] = useState(() => {
    if (otpCode && otpCode.length > 0) {
      return Array.from({ length }, (_, i) => otpCode[i] || "");
    }
    return Array.from({ length }, () => "");
  });
  const inputsRef = useRef([]);
  const [errors, setErrors] = useState({});
  const [seconds, setSeconds] = useState(300);

  // countdown
  useEffect(() => {
    if (seconds <= 0) return;
    const timer = setTimeout(() => setSeconds((s) => s - 1), 1000);
    return () => clearTimeout(timer);
  }, [seconds]);

  const focusAt = (idx) => {
    const el = inputsRef.current[idx];
    if (el) {
      el.focus();
      el.select();
    }
  };

  const handleResend = async () => {
    if (seconds > 0) return; // wait countdown

    try {
      setLoading(true);

      const payload = {
        phone: `${dial.replace("+", "")}${phone.replace(/\s+/g, "")}`,
      };

      const result = await resendPhone(payload).unwrap();

      if (result?.status) {
        toast.success(t("verify.resendSuccess"));
        setSeconds(300);

        const nextOtp = String(result?.otpcode || "");
        if (nextOtp) {
          setCode(Array.from({ length }, (_, i) => nextOtp[i] || ""));
          focusAt(Math.min(nextOtp.length - 1, length - 1));
        } else {
          setCode(Array.from({ length }, () => ""));
          focusAt(0);
        }

        // update slice again
        dispatch(
          setResetPwdPayload({
            memberId: result.member_id,
            otpCode: nextOtp,
            phone,
            dial,
          }),
        );
      }
    } catch (err) {
      const error = extractError(err);
      toast.error(error.message);
    } finally {
      setLoading(false);
    }
  };

  const handleChange = (val, idx) => {
    if (val.length > 1) {
      // handle paste
      const digits = val.replace(/\D/g, "").slice(0, length).split("");
      if (digits.length === 0) return;
      const next = Array.from({ length }, (_, i) => digits[i] ?? "");
      setCode(next);
      focusAt(Math.min(digits.length - 1, length - 1));
      return;
    }
    if (!/^\d?$/.test(val)) return;
    const next = [...code];
    next[idx] = val;
    setCode(next);
    if (val && idx < length - 1) focusAt(idx + 1);
  };

  const handleKeyDown = (e, idx) => {
    if (e.key === "Backspace") {
      if (code[idx]) {
        const next = [...code];
        next[idx] = "";
        setCode(next);
      } else if (idx > 0) {
        focusAt(idx - 1);
        const next = [...code];
        next[idx - 1] = "";
        setCode(next);
      }
      e.preventDefault();
    }
    if (e.key === "ArrowLeft" && idx > 0) {
      focusAt(idx - 1);
      e.preventDefault();
    }
    if (e.key === "ArrowRight" && idx < length - 1) {
      focusAt(idx + 1);
      e.preventDefault();
    }
  };

  const handlePaste = (e) => {
    const text =
      (e.clipboardData || window.clipboardData).getData("text") || "";
    const digits = text.replace(/\D/g, "").slice(0, length).split("");
    if (digits.length === 0) return;
    e.preventDefault();
    const next = Array.from({ length }, (_, i) => digits[i] ?? "");
    setCode(next);
    focusAt(Math.min(digits.length - 1, length - 1));
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    const otp = code.join("");
    const newErrors = {};

    if (!otp) {
      newErrors.otp = t("verify.errors.requiredAll");
    } else if (otp.length !== 6) {
      newErrors.otp = t("verify.errors.sixDigits");
    } else if (!/^\d{6}$/.test(otp)) {
      newErrors.otp = t("verify.errors.invalidCode");
    }

    setErrors(newErrors);
    if (Object.keys(newErrors).length > 0) return;

    setLoading(true);
    try {
      const payload = {
        code: otp,
        member_id: memberId,
        verifyby: "phone",
      };

      const result = await verifyResetOtp(payload).unwrap();

      if (result?.status) {
        // toast.success(result.message);
        delayedRedirect(router, "/reset-password");
      } else {
        toast.error(result?.message || t("verify.errors.failed"));
      }
    } catch (err) {
      const error = extractError(err);
      toast.error(error.message);
    } finally {
      setLoading(false);
    }
  };

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
            {t("verify.title")}
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
          <p className="text-lg font-semibold">{t("verify.title")}</p>
          <p className="mt-2 text-sm text-white/70">
            {t("verify.subtitle")} {maskPhone(dial + phone)}
          </p>
        </div>

        {/* OTP inputs */}
        <form onSubmit={handleSubmit}>
          <div className="mt-6 flex justify-between gap-2">
            {code.map((digit, idx) => (
              <input
                key={idx}
                ref={(el) => (inputsRef.current[idx] = el)}
                type="tel"
                inputMode="numeric"
                maxLength={1}
                value={digit}
                onChange={(e) => handleChange(e.target.value, idx)}
                onKeyDown={(e) => handleKeyDown(e, idx)}
                onPaste={handlePaste}
                className="w-12 h-12 text-center border border-white/30 text-lg font-semibold outline-none
             focus:rounded-xl
             focus:border-[0.59px] focus:border-transparent
             focus:[border-image:linear-gradient(180deg,#F8AF07_0%,#FFFC86_100%)_1]
             focus:[border-image-slice:1]
             focus:text-[#F8AF07]"
              />
            ))}
          </div>

          {/* Error */}
          {errors.otp && (
            <p className="mt-2 text-xs text-red-500">{errors.otp}</p>
          )}

          {/* Resend */}
          <div className="mt-4 text-right text-sm">
            {seconds > 0 ? (
              <span className="text-white/60">
                {t("verify.resendIn")} {seconds}s
              </span>
            ) : (
              <button
                type="button"
                onClick={handleResend}
                className="font-medium text-[#FFC000] active:opacity-80"
              >
                {t("verify.resend")}
              </button>
            )}
          </div>

          {/* Submit */}
          <div className="mt-6">
            <SubmitButton type="submit">{t("verify.submit")}</SubmitButton>
          </div>
        </form>
      </div>
    </div>
  );
}
