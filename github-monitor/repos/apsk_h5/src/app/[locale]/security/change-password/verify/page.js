"use client";

import { useContext, useEffect, useRef, useState } from "react";
import Image from "next/image";
import { useRouter } from "next/navigation";
import { useTranslations } from "next-intl";
import { useSelector } from "react-redux";
import { IMAGES } from "@/constants/images";
import SubmitButton from "@/components/shared/SubmitButton";
import toast from "react-hot-toast";
import { extractError, maskPhone } from "@/utils/utility";
import { UIContext } from "@/contexts/UIProvider";
import {
  usePasswordOtpMutation,
  useSendPasswordOtpMutation,
} from "@/services/authApi";

function OtpGroup({ value, onChange, name = "otp" }) {
  const inputs = Array.from({ length: 6 });
  const refs = useRef([]);

  const handleInput = (i, e) => {
    const v = e.target.value.replace(/\D/g, "").slice(0, 1);

    const chars = value.split("");
    chars[i] = v;
    onChange(chars.join(""));

    if (v && i < 5) refs.current[i + 1]?.focus();
  };

  const handleKeyDown = (i, e) => {
    if (e.key === "Backspace") {
      const chars = value.split("");

      if (chars[i]) {
        chars[i] = "";
        onChange(chars.join(""));
      } else if (i > 0) {
        refs.current[i - 1]?.focus();
        chars[i - 1] = "";
        onChange(chars.join(""));
      }

      e.preventDefault();
    }
  };

  return (
    <div className="mt-6 flex justify-between gap-2">
      {inputs.map((_, i) => (
        <input
          key={`${name}-${i}`}
          ref={(el) => (refs.current[i] = el)}
          type="tel"
          inputMode="numeric"
          maxLength={1}
          value={value[i] ?? ""}
          onChange={(e) => handleInput(i, e)}
          onKeyDown={(e) => handleKeyDown(i, e)}
          className="w-12 h-12 text-center border border-white/30 text-lg font-semibold outline-none
            focus:rounded-xl
            focus:border-[0.59px] focus:border-transparent
            focus:[border-image:linear-gradient(180deg,#F8AF07_0%,#FFFC86_100%)_1]
            focus:[border-image-slice:1]
            focus:text-[#F8AF07]"
        />
      ))}
    </div>
  );
}

export default function SecurityVerify() {
  const t = useTranslations();
  const router = useRouter();

  const { bindphone, bindemail, bindgoogle, phone, newPw, memberId, email } =
    useSelector((s) => s.changePwd);
  const { setLoading } = useContext(UIContext);
  const [smsCode, setSmsCode] = useState("");
  const [emailCode, setEmailCode] = useState("");
  const [gaCode, setGaCode] = useState("");
  const [passwordOtp, { isLoading }] = usePasswordOtpMutation();
  const [sendPasswordOtp, { isLoading: isSendPasswordLoading }] =
    useSendPasswordOtpMutation();
  const [mounted, setMounted] = useState(false);
  const [seconds, setSeconds] = useState(0);
  useEffect(() => {
    setMounted(true);
  }, []);

  useEffect(() => {
    if (seconds <= 0) return;

    const timer = setTimeout(() => {
      setSeconds((s) => s - 1);
    }, 1000);

    return () => clearTimeout(timer);
  }, [seconds]);

  if (!mounted) return null;

  const handleSendCode = async () => {
    if (seconds > 0) return; // prevent click during countdown
    if (bindgoogle === 1) return; // google doesn't need resend

    try {
      setLoading(true);

      // decide verify method
      const verifyBy =
        bindphone === 1 ? "phone" : bindemail === 1 ? "email" : null;

      if (!verifyBy) return;

      const res = await sendPasswordOtp({
        member_id: Number(memberId),
        verifyby: verifyBy,
      }).unwrap();

      if (!res?.status) {
        toast.error(res.message);
        return;
      }

      // success message
      const message =
        verifyBy === "phone"
          ? t("verify.resendPhone")
          : t("verify.resendEmail");

      if (
        verifyBy === "phone" &&
        res?.otpcode &&
        res.otpcode.status &&
        res.otpcode.code
      ) {
        setSmsCode(String(res.otpcode.code));
      }

      toast.success(message);

      // start 300s countdown (follow reference)
      setSeconds(300);
    } catch (err) {
      const error = extractError(err);
      toast.error(error.message);
    } finally {
      setLoading(false);
    }
  };

  const handleSubmit = async () => {
    // Determine method based on binding + filled OTP
    let verifyBy = null;

    if (bindphone === 1 && smsCode.length === 6) {
      verifyBy = "phone";
    } else if (bindemail === 1 && emailCode.length === 6) {
      verifyBy = "email";
    } else if (bindgoogle === 1 && gaCode.length === 6) {
      verifyBy = "google";
    }

    if (!verifyBy) {
      toast.error(t("securityVerify.errors.invalidCode"));
      return;
    }

    // Select correct code
    const code =
      verifyBy === "phone"
        ? smsCode
        : verifyBy === "email"
          ? emailCode
          : gaCode;

    setLoading(true);

    try {
      const res = await passwordOtp({
        member_id: Number(memberId),
        newpassword: newPw,
        code,
        verifyby: verifyBy,
      }).unwrap();

      if (!res.status) {
        toast.error(res.message || t("securityVerify.errors.failed"));
        return;
      }

      sessionStorage.setItem("show_change_password_success", "1");
      router.replace("/");
    } catch (err) {
      const result = extractError(err);

      if (result.type === "validation") {
        toast.error(Object.values(result.fieldErrors).join(", "));
      } else {
        toast.error(result.message);
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="mx-auto max-w-[480px] min-h-dvh bg-[#00143D] text-white px-4 pb-8">
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
          {t("securityVerify.title")}
        </h1>
      </div>

      <div className="mt-2 space-y-10">
        {bindphone === 1 && (
          <section>
            <div className="text-xl font-bold">
              {t("securityVerify.phone.title")}
            </div>
            <p className="mt-2 text-sm text-white/70">
              {t("verify.subtitle2").replace("$phone", maskPhone(phone))}
            </p>
            <OtpGroup value={smsCode} onChange={setSmsCode} name="sms" />
          </section>
        )}

        {bindemail === 1 && (
          <section>
            <div className="text-xl font-bold">
              {t("securityVerify.email.title")}
            </div>
            <p className="mt-2 text-sm text-white/70">
              {t("securityVerify.email.tip")} {email}
            </p>
            <OtpGroup value={emailCode} onChange={setEmailCode} name="email" />
          </section>
        )}

        {bindgoogle === 1 && (
          <section>
            <div className="text-xl font-bold">
              {t("securityVerify.ga.title")}
            </div>
            <p className="mt-2 text-sm text-white/70">
              {t("securityVerify.ga.tip")}
            </p>
            <OtpGroup value={gaCode} onChange={setGaCode} name="ga" />
          </section>
        )}

        <div className="mt-4 text-right text-sm">
          {bindgoogle === 1 ? (
            <span className="text-white/60">{t("verify.googleTip")}</span>
          ) : seconds > 0 ? (
            <span className="text-white/60">
              {t("verify.resendIn")} {seconds}s
            </span>
          ) : (
            <button
              type="button"
              onClick={handleSendCode}
              className="font-medium text-[#FFC000] active:opacity-80"
            >
              {seconds === 0 ? t("verify.send") : t("verify.resend")}
            </button>
          )}
        </div>
      </div>

      <div className="pb-6 pt-6">
        <SubmitButton onClick={handleSubmit}>
          {t("securityVerify.actions.verify")}
        </SubmitButton>
      </div>
    </div>
  );
}
