"use client";

import Image from "next/image";
import { useTranslations } from "next-intl";
import { useRouter } from "next/navigation";
import { useContext, useEffect, useRef, useState } from "react";
import { setEmailPayload } from "@/store/slice/emailVerifySlice";
import { IMAGES } from "@/constants/images";
import SubmitButton from "@/components/shared/SubmitButton";
import { useDispatch, useSelector } from "react-redux";
import { delayedRedirect, extractError, maskPhone } from "@/utils/utility";
import {
  useEmailVerifyMutation,
  useOtpVerifyMutation,
} from "@/services/otpApi";
import { UIContext } from "@/contexts/UIProvider";
import { setCookie } from "@/utils/cookie";
import { COOKIE_NAMES } from "@/constants/cookies";
import { toast } from "react-hot-toast";
import { useBindEmailMutation } from "@/services/authApi";

export default function VerifyCodePage() {
  const dispatch = useDispatch();

  const { otpcode, memberId, email } = useSelector((s) => s.emailVerify);
  const [bindEmail, { isLoading: isSending }] = useBindEmailMutation();

  const t = useTranslations();
  const router = useRouter();
  const [errors, setErrors] = useState({});
  const [emailVerify, { isLoading }] = useEmailVerifyMutation();
  const length = 6;
  const { setLoading } = useContext(UIContext);
  // const [code, setCode] = useState(Array.from({ length }, () => ""));

  const [code, setCode] = useState(() => {
    if (otpcode && otpcode.length > 0) {
      return Array.from({ length }, (_, i) => otpcode[i] || "");
    }
    return Array.from({ length }, () => "");
  });

  const inputsRef = useRef([]);
  const [seconds, setSeconds] = useState(10);

  // useEffect(() => {
  //   if (otpcode && otpcode.length > 0) {
  //     setCode(Array.from({ length }, (_, i) => otpcode[i] || ""));
  //   }
  // }, [otpcode, length]);

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

  const resendReset = async (e) => {
    // 1) Guards: only allow when countdown finished + not already sending
    if (seconds > 0 || isSending) return;

    // 2) Basic payload validation
    if (!email || !memberId) {
      toast.error(t("verify.resendError")); // add a better msg if you want
      return;
    }

    e.preventDefault();
    setLoading(true); // always reset
    try {
      const payload = {
        email,
        member_id: memberId,
      };

      const result = await bindEmail(payload).unwrap();

      if (result?.status) {
        const nextOtp = String(result?.otpcode || "");
        // 4) Reset input boxes (use server otp if you want to show it)
        if (nextOtp) {
          setCode(Array.from({ length }, (_, i) => nextOtp[i] || ""));
          focusAt(Math.min(nextOtp.length - 1, length - 1));
        } else {
          setCode(Array.from({ length }, () => ""));
          focusAt(0);
        }

        dispatch(
          setEmailPayload({
            otpcode: nextOtp || "", // optional prefill
            memberId: memberId || "", // or whatever you have
            email: email,
          })
        );
        setSeconds(300);
        toast.success(t("verify.resendSuccess"));
      }
    } catch (err) {
      const result = extractError(err);

      if (result.type === "validation") {
        // Show under each field
        setErrors(result.fieldErrors);
      } else {
        toast.error(result.message);

        // Toast or global alert
      }
    } finally {
      setLoading(false); // always reset
    }
  };

  const handleChange = (val, idx) => {
    if (val.length > 1) {
      // handle paste full code
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

    if (Object.keys(newErrors).length > 0) {
      return; // don’t proceed if error
    }

    if (Object.keys(newErrors).length === 0) {
      setLoading(true); // always reset
      try {
        const payload = {
          code: otp,
          member_id: memberId,
        };

        const result = await emailVerify(payload).unwrap();

        if (result?.status) {
          toast.success(result.message);

          delayedRedirect(router, "/setting");
        }
      } catch (err) {
        const result = extractError(err);

        if (result.type === "validation") {
          // Show under each field
          setErrors(result.fieldErrors);
        } else {
          toast.error(result.message);
        }
      } finally {
        setLoading(false); // always reset
      }
    } else {
      // validation failed → stop loading too
      setLoading(false);
    }

    // const otp = code.join("");
    // router.push("/reset-password");
  };

  return (
    <div className="mx-auto max-w-[480px] min-h-dvh bg-[#00143D] text-white px-4 pb-8">
      {/* Background */}

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

        {/* Title + subtitle */}
        <div className="mt-6">
          <p className="text-lg font-semibold">{t("verify.title")}</p>
          <p className="mt-2 text-sm text-white/70">
            {t("verify.subtitle")}
            {email}
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

          {/* Resend */}
          <div className="mt-4 text-right text-sm">
            {seconds > 0 ? (
              <span className="text-white/60">
                <span>
                  {seconds}
                  {t("verify.resendInSuffix")}
                </span>
              </span>
            ) : (
              <button
                type="button"
                onClick={resendReset}
                className="font-medium text-[#FFC000] active:opacity-80 disabled:opacity-50"
                disabled={isSending}
              >
                {t("verify.resend")}
              </button>
            )}
          </div>

          {/* Submit */}
          <div className="mt-6">
            <SubmitButton type="submit" disabled={isLoading}>
              {t("verify.submit")}
            </SubmitButton>
          </div>
        </form>
      </div>
    </div>
  );
}
