"use client";

import Image from "next/image";
import { useTranslations } from "next-intl";
import { useRouter } from "next/navigation";
import { useContext, useEffect, useRef, useState } from "react";

import { IMAGES } from "@/constants/images";
import SubmitButton from "@/components/shared/SubmitButton";
import { useDispatch, useSelector } from "react-redux";
import { delayedRedirect, extractError, maskPhone } from "@/utils/utility";
import { useOtpVerifyMutation } from "@/services/otpApi";
import { UIContext } from "@/contexts/UIProvider";
import {
  clearAllCookie,
  clearCookie,
  COOKIE_AGENT,
  COOKIE_REFERRAL,
  COOKIE_SHOP,
  getCookie,
  setCookie,
} from "@/utils/cookie";
import { COOKIE_NAMES, LOCAL_STORAGE_NAMES } from "@/constants/cookies";
import { toast } from "react-hot-toast";
import { setOtpPayload } from "@/store/slice/otpVerifySlice";
import { useLoginMutation } from "@/services/authApi";
import { getLocationOrigin } from "next/dist/shared/lib/utils";
import { useTransferOutMutation } from "@/services/transactionApi";

export default function VerifyCodePage() {
  const dispatch = useDispatch();

  const { phone, dial, otpcode, password, verifyby, module } = useSelector(
    (s) => s.otpVerify,
  );

  const t = useTranslations();
  const router = useRouter();
  const [errors, setErrors] = useState({});
  const [verifyOtp, { isLoading }] = useOtpVerifyMutation();
  const length = 6;
  const { setLoading } = useContext(UIContext);
  const [errorMessage, setErrorMessage] = useState("");
  const [agentCode, setAgentCode] = useState("");
  const [referralCode, setReferralCode] = useState("");
  const [transferOut] = useTransferOutMutation();

  const [login, { data, error, isLoading: isLoginLoading, isSuccess }] =
    useLoginMutation();
  // const [code, setCode] = useState(Array.from({ length }, () => ""));

  const [code, setCode] = useState(() => {
    if (otpcode && otpcode.length > 0) {
      return Array.from({ length }, (_, i) => otpcode[i] || "");
    }
    return Array.from({ length }, () => "");
  });

  const inputsRef = useRef([]);
  const [seconds, setSeconds] = useState(300);

  // useEffect(() => {
  //   if (otpcode && otpcode.length > 0) {
  //     setCode(Array.from({ length }, (_, i) => otpcode[i] || ""));
  //   }
  // }, [otpcode, length]);

  // countdown

  useEffect(() => {
    const cookieReferral = getCookie(COOKIE_REFERRAL);

    // if cookie exists but state empty, set it
    if (cookieReferral && !referralCode) {
      setReferralCode(cookieReferral);
    }
  }, [referralCode]);

  useEffect(() => {
    if (seconds <= 0) return;
    const timer = setTimeout(() => setSeconds((s) => s - 1), 1000);
    return () => clearTimeout(timer);
  }, [seconds]);

  useEffect(() => {
    const code = getCookie(COOKIE_AGENT);
    if (code) setAgentCode(code);
  }, []);

  const focusAt = (idx) => {
    const el = inputsRef.current[idx];
    if (el) {
      el.focus();
      el.select();
    }
  };
  const handleResend = async () => {
    if (seconds > 0) return; // 倒计时未结束不能点

    try {
      setLoading(true);

      const payload = {
        phone: `${dial.replace("+", "")}${phone.replace(/\s+/g, "")}`,
        password,
      };

      const result = await login(payload).unwrap();

      if (result?.status) {
        toast.success(t("verify.resendSuccess"));

        // 重置倒计时
        setSeconds(300);

        // 清空输入框 or 预填 OTP（看你需求）
        const nextOtp = String(result?.otpcode || "");
        if (nextOtp) {
          setCode(Array.from({ length }, (_, i) => nextOtp[i] || ""));
          focusAt(Math.min(nextOtp.length - 1, length - 1));
        } else {
          setCode(Array.from({ length }, () => ""));
          focusAt(0);
        }

        // 更新 Redux
        dispatch(
          setOtpPayload({
            phone,
            dial,
            otpcode: nextOtp,
            password,
            verifyby: "phone",
            module: "login",
          }),
        );
      }
    } catch (err) {
      const error = extractError(err);
      toast.error(error.message);

      setErrorMessage(error.message); // ✅ capture message
    } finally {
      setLoading(false);
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
          login: `${dial.replace("+", "")}${phone.replace(/\s+/g, "")}`,
          password,
          code: otp,
          verifyby: verifyby,
          module: module,

          ...(module === "register" && referralCode
            ? { invitecode: referralCode || null } // ✅ include only if register AND has value
            : {}), // ✅ else omit completely

          ...(module === "register"
            ? { agent_code: getCookie(COOKIE_AGENT) || null } // ✅ only add when registering
            : {}), // ✅ otherwise omit completely

          ...(module === "register"
            ? { shop_code: getCookie(COOKIE_SHOP) || null } // ✅ only add when registering
            : {}), // ✅ otherwise omit completely
        };

        const result = await verifyOtp(payload).unwrap();

        if (result?.status) {
          const { member_id, member_login, member_name, phone, prefix } =
            result.data;
          const { access_token, refresh_token, token_type } = result.token;

          // // save to cookie
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
          // // Cookie (persist across tabs)
          setCookie(COOKIE_NAMES.BEARER_TOKEN, access_token);
          setCookie(COOKIE_NAMES.REFRESH_TOKEN, refresh_token);
          // LocalStorage (survive browser tab kills)
          localStorage.setItem(COOKIE_NAMES.BEARER_TOKEN, access_token);
          localStorage.setItem(COOKIE_NAMES.REFRESH_TOKEN, refresh_token);
          toast.success(result.message);
          if (module !== "register") {
            localStorage.setItem(LOCAL_STORAGE_NAMES.JUST_LOGIN, "1");
          }
          setCookie(COOKIE_NAMES.REFRESH_TOKEN, refresh_token);
          delayedRedirect(router, "/");
        }
      } catch (err) {
        const result = extractError(err);

        if (result.type === "validation") {
          // Show under each field
          setErrors(result.fieldErrors);
        } else {
          setErrorMessage(result.message); // ✅ capture message
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
            {t("verify.subtitle")}
            {maskPhone(dial + phone)}
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
          {(errorMessage?.includes("未找到用户") ||
            errorMessage?.includes("邀请码不存在")) && (
            <div className="mt-3 flex items-center gap-2">
              <span className="text-sm text-white/80">
                {t("register.enterReferral")}：
              </span>
              <span className="text-[#F8AF07] text-sm">{referralCode}</span>
              <button
                type="button"
                onClick={() => {
                  setErrorMessage("");
                  setReferralCode("");
                  clearCookie(COOKIE_REFERRAL);
                }}
                className="relative w-4 h-4 flex items-center justify-center rounded-full border border-gray-400 text-gray-400 hover:bg-red-500 hover:text-white hover:border-red-500 transition"
              >
                <span className="absolute w-2 h-[1.5px] bg-current rotate-45"></span>
                <span className="absolute w-2 h-[1.5px] bg-current -rotate-45"></span>
              </button>
            </div>
          )}

          {errorMessage?.includes("未找到代理商") && (
            <div className="mt-3 flex items-center gap-2">
              <span className="text-sm text-white/80">
                {t("register.agentCode")}：
              </span>
              <span className="text-[#F8AF07] text-sm">{agentCode}</span>
              <button
                type="button"
                onClick={() => {
                  setErrorMessage("");
                  setAgentCode("");
                  clearCookie(COOKIE_AGENT);
                }}
                className="relative w-4 h-4 flex items-center justify-center rounded-full border border-gray-400 text-gray-400 hover:bg-red-500 hover:text-white hover:border-red-500 transition"
              >
                <span className="absolute w-2 h-[1.5px] bg-current rotate-45"></span>
                <span className="absolute w-2 h-[1.5px] bg-current -rotate-45"></span>
              </button>
            </div>
          )}

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
