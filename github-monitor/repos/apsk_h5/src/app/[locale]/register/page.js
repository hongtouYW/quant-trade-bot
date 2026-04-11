"use client";

import Image from "next/image";
import { useTranslations } from "next-intl";
import { useRouter } from "next/navigation";
import { useContext, useEffect, useState } from "react";

import { IMAGES } from "@/constants/images";
import { EyeYellowSlashIcon, EyeYellowIcon } from "@/components/shared/EyeIcon";
import SubmitButton from "@/components/shared/SubmitButton";
import { UIContext } from "@/contexts/UIProvider";
import { useDispatch } from "react-redux";
import { useRegisterMutation } from "@/services/authApi";
import { setOtpPayload } from "@/store/slice/otpVerifySlice";
import { extractError, passwordRegex } from "@/utils/utility";
import { toast } from "react-hot-toast";
import {
  clearCookie,
  COOKIE_AGENT,
  COOKIE_REFERRAL,
  COOKIE_SHOP,
  getCookie,
  setCookie,
} from "@/utils/cookie";

import UserAgreementModal from "@/components/UserAgreementModal";
import {
  useGetAgentIconQuery,
  useGetAgreementListQuery,
} from "@/services/commonApi";
export default function RegisterPage() {
  const t = useTranslations();
  const router = useRouter();

  const { showPhonePicker } = useContext(UIContext);
  const [dial, setDial] = useState("+60");
  const [showPwd, setShowPwd] = useState(false);
  const [checked, setChecked] = useState(false);
  const { setLoading } = useContext(UIContext);
  const dispatch = useDispatch();
  const [phone, setPhone] = useState("");
  const [password, setPassword] = useState("");
  const [confirmPassword, setConfirmPassword] = useState("");
  const [errors, setErrors] = useState({});
  const agentCodeFromCookie = getCookie(COOKIE_AGENT); // ✅ read cookie
  const [showComPwd, setShowComPwd] = useState(false);
  const [showAgreement, setShowAgreement] = useState(false);

  // ✅ Load agent code from cookie
  const [agentCode, setAgentCode] = useState("");
  const [referralCode, setReferralCode] = useState("");
  const [shopCode, setShopCode] = useState("");
  const { data: userAgreement } = useGetAgreementListQuery();
  const { data: agentIconData } = useGetAgentIconQuery({
    member_id: null,
  });
  const agreementUrl = userAgreement?.data?.url;
  const logoSrc =
    agentIconData?.status && agentIconData?.icon
      ? `${process.env.NEXT_PUBLIC_IMAGE_URL}/${agentIconData.icon}`
      : IMAGES.login.logo;

  useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    const queryAgent = params.get("agentCode");
    const queryReferral = params.get("referralCode");
    const queryShop = params.get("shopCode");

    if (queryAgent || queryReferral || queryShop) {
      // ✅ If querystring exists, set and save cookies
      if (queryAgent) {
        setAgentCode(queryAgent);
        setCookie(COOKIE_AGENT, queryAgent);
      }
      if (queryReferral) {
        setReferralCode(queryReferral);
        setCookie(COOKIE_REFERRAL, queryReferral);
      }

      if (queryShop) {
        setShopCode(queryShop);
        setCookie(COOKIE_SHOP, queryShop);
      }
    } else {
      // ✅ Else use cookies if not null
      const cookieAgent = getCookie(COOKIE_AGENT);
      const cookieReferral = getCookie(COOKIE_REFERRAL);
      const cookieShop = getCookie(COOKIE_SHOP);

      if (cookieAgent) setAgentCode(cookieAgent);
      if (cookieReferral) setReferralCode(cookieReferral);
      if (cookieShop) setShopCode(cookieShop);
    }
  }, []);

  const [register, { data, error, isLoading, isSuccess }] =
    useRegisterMutation();

  const handleDeleteAgentCode = () => {
    clearCookie(COOKIE_AGENT);
    setAgentCode("");
  };

  const handleSubmit = async (e) => {
    e.preventDefault();

    // ✅ normalize phone ONCE (safe)
    const cleanPhone = phone?.replace(/\s+/g, "").trim() || "";

    const newErrors = {};
    if (!cleanPhone) {
      newErrors.phone = t("register.errors.phoneRequired");
    }

    // if (!password.trim()) {
    //   newErrors.password = t("register.errors.passwordRequired");
    // } else if (!passwordRegex.test(password)) {
    //   newErrors.password = t("register.errors.passwordWeak");
    // }

    // if (!checked) {
    //   newErrors.agreement = t("register.errors.agreementRequired");
    // }

    // ✅ only trim for empty check, DO NOT mutate password
    if (!password?.trim()) {
      newErrors.password = t("register.errors.passwordRequired");
    }
    if (!confirmPassword?.trim()) {
      newErrors.confirmPassword = t("register.errors.confirmPasswordRequired");
    } else if (confirmPassword !== password) {
      newErrors.confirmPassword = t("register.errors.passwordMismatch");
    }

    setErrors(newErrors);

    if (Object.keys(newErrors).length === 0) {
      setLoading(true); // always reset
      try {
        const payload = {
          invitecode: referralCode || null,
          phone: `${dial.replace("+", "")}${cleanPhone}`,
          password, // ❌ untouched
          agent_code: agentCodeFromCookie || null, // ✅ fallback if cookie missing
          shop_code: shopCode || null, // ✅ fallback if cookie missing
        };

        const result = await register(payload).unwrap();

        if (result?.status) {
          const currentCookie = getCookie("referralCode");
          if (referralCode && referralCode !== currentCookie) {
            setCookie(COOKIE_REFERRAL, referralCode);
          }

          dispatch(
            setOtpPayload({
              phone: cleanPhone,
              dial: dial,
              otpcode: result?.otpcode,
              password: password, // ❌ untouched
              verifyby: "phone",
              module: "register",
            }),
          );

          router.push(`/otp-reset/verify`);

          // localStorage.setItem("token", result.token);
          // router.push("/home");
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
    } else {
      // validation failed → stop loading too
      setLoading(false);
    }
  };

  return (
    <div className="relative mx-auto max-w-[480px] min-h-dvh bg-[#00143D] text-white px-4 pb-8">
      {/* Background */}
      <Image
        src={
          IMAGES.login.bg /* reuse same bg, or IMAGES.register.bg if you have */
        }
        alt="bg"
        fill
        priority
        className="object-cover"
      />
      {/* Dark overlay */}
      <div className="absolute inset-0 bg-black/70" />

      <form onSubmit={handleSubmit}>
        <div className="relative z-10">
          {/* Header (project standard) */}
          <div className="relative flex items-center h-14">
            <button
              type="button"
              onClick={() => router.back()}
              className="z-10 cursor-pointer"
            >
              <Image
                src={IMAGES.arrowLeft}
                alt="back"
                width={22}
                height={22}
                className="object-contain"
              />
            </button>
            <h1 className="absolute inset-x-0 text-lg font-semibold text-center">
              {t("register.title")}
            </h1>
          </div>

          {/* Logo */}
          <div className="mt-3 flex justify-center">
            <div className="relative w-[500px] h-[100px]">
              <Image
                src={logoSrc}
                alt="logo"
                fill
                priority
                className="object-contain"
              />
            </div>
          </div>

          {/* Title + subtitle */}
          <div className="mt-6">
            <p className="text-lg font-semibold">{t("register.title")}</p>
            <p className="mt-2 text-sm text-white/70">
              {t("register.subtitle")}
            </p>
          </div>

          {/* Phone row (two framed boxes, circled) */}
          <div className="mt-5 grid grid-cols-[110px_1fr] gap-3">
            {/* Country code box */}
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
                    className="object-contain"
                  />
                </div>
              </div>
            </button>
            {/* Phone input box */}
            <div className="rounded-xl border border-white/30 bg-black/40 px-3 py-3">
              <input
                type="tel"
                value={phone}
                onChange={(e) => setPhone(e.target.value)}
                placeholder={t("login.phone")}
                className="w-full bg-transparent text-sm outline-none placeholder:text-[#F8AF07]/80"
              />
            </div>
          </div>

          <div className="grid grid-cols-[110px_1fr] gap-3">
            {/* Country code box */}
            <div className="px-3"></div>

            {/* Phone input box */}
            <div className="px-3">
              {errors.phone && (
                <p className="col-span-2 text-[13px] text-red-400">
                  {errors.phone}
                </p>
              )}
            </div>
          </div>

          {/* Password with eye toggle */}
          <div className="mt-3 rounded-xl border border-white/30 bg-black/40 px-3 py-3">
            <div className="flex items-center gap-2">
              <input
                type={showPwd ? "text" : "password"}
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                placeholder={t("register.password")}
                className="flex-1 bg-transparent text-sm outline-none placeholder:text-[#F8AF07]/80"
              />
              <button
                type="button"
                onClick={() => setShowPwd((v) => !v)}
                className="p-1"
              >
                {showPwd ? <EyeYellowIcon /> : <EyeYellowSlashIcon />}
              </button>
            </div>
          </div>
          {errors.password && (
            <p className="mt-1 text-[13px] text-red-400">{errors.password}</p>
          )}

          {/* Confirm password */}
          <div className="mt-3 rounded-xl border border-white/30 bg-black/40 px-3 py-3">
            <div className="flex items-center gap-2">
              <input
                type={showComPwd ? "text" : "password"}
                value={confirmPassword}
                onChange={(e) => setConfirmPassword(e.target.value)}
                placeholder={t("register.confirmPassword")}
                className="flex-1 bg-transparent text-sm outline-none placeholder:text-[#F8AF07]/80"
              />
              <button
                type="button"
                onClick={() => setShowComPwd((v) => !v)}
                className="p-1"
              >
                {showComPwd ? <EyeYellowIcon /> : <EyeYellowSlashIcon />}
              </button>
            </div>
          </div>

          {/* Rule line with yellow bullet (your standard) */}
          <div className="mt-3 flex items-center gap-2 text-sm text-white/80">
            <div
              className="grid h-5 w-5 place-items-center rounded-full transition-colors"
              style={{
                backgroundColor: passwordRegex.test(password)
                  ? "#16A34A"
                  : "transparent",
                border: passwordRegex.test(password)
                  ? "none"
                  : "1px solid rgba(255,255,255,0.4)",
              }}
            >
              {passwordRegex.test(password) && (
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="white"
                  strokeWidth="3"
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  className="h-3 w-3"
                >
                  <path d="M20 6L9 17l-5-5" />
                </svg>
              )}
            </div>
            <span>{t("register.passwordRule")}</span>
          </div>

          {/* <div className="mt-3 rounded-xl border border-white/30 bg-black/40 px-3 py-3">
            <input
              type="password"
              value={confirmPassword}
              onChange={(e) => setConfirmPassword(e.target.value)}
              placeholder={t("register.confirmPassword")}
              className="w-full bg-transparent text-sm outline-none placeholder:text-[#F8AF07]/80"
            />
          </div> */}
          {errors.confirmPassword && (
            <p className="mt-1 text-[13px] text-red-400">
              {errors.confirmPassword}
            </p>
          )}

          {
            <div className="mt-3">
              <label className="block text-sm text-white/80 mb-1">
                {t("register.referralCode")}
              </label>

              <div className="rounded-xl border border-white/30 bg-black/40 px-3 py-3 flex items-center">
                <input
                  type="text"
                  value={referralCode}
                  onChange={(e) => {
                    if (!getCookie("referralCode"))
                      setReferralCode(e.target.value);
                  }}
                  readOnly={!!getCookie("referralCode")}
                  className={`flex-1 bg-transparent text-sm outline-none ${
                    getCookie("referralCode")
                      ? "text-[#F8AF07]"
                      : "text-white placeholder-white/40"
                  }`}
                  placeholder={
                    !getCookie("referralCode")
                      ? t("register.enterReferral")
                      : ""
                  }
                />

                {/* Show X button only when cookie exists */}
                {/* {getCookie("referralCode") && (
                  <button
                    type="button"
                    onClick={() => {
                      setReferralCode("");
                      clearCookie(COOKIE_REFERRAL);
                    }}
                    className="relative w-5 h-5 flex items-center justify-center rounded-full border border-gray-400 text-gray-400 hover:bg-red-500 hover:text-white hover:border-red-500 transition"
                  >
                    <span className="absolute w-2.5 h-0.5 bg-current rotate-45"></span>
                    <span className="absolute w-2.5 h-0.5 bg-current -rotate-45"></span>
                  </button>
                )} */}
              </div>
            </div>
          }

          {agentCode && (
            <div className="mt-3 hidden">
              <label className="block text-sm text-white/80 mb-1">
                {t("register.agentCode")}
              </label>
              <div className="rounded-xl border border-white/30 bg-black/40 px-3 py-3 flex items-center">
                <input
                  type="text"
                  value={agentCode}
                  readOnly
                  className="flex-1 bg-transparent text-sm outline-none text-[#F8AF07]"
                />
                <button
                  type="button"
                  onClick={() => {
                    // ✅ Clear state
                    setAgentCode("");
                    clearCookie(COOKIE_AGENT);
                  }}
                  className="relative w-5 h-5 flex items-center justify-center rounded-full border border-gray-400 text-gray-400 hover:bg-red-500 hover:text-white hover:border-red-500 transition"
                >
                  <span className="absolute w-2.5 h-0.5 bg-current rotate-45"></span>
                  <span className="absolute w-2.5 h-0.5 bg-current -rotate-45"></span>
                </button>
              </div>
            </div>
          )}

          {/* Submit */}
          <div className="mt-6">
            <SubmitButton>{t("register.submit")}</SubmitButton>
          </div>

          {/* Agreement */}
          <div className="mt-6 text-center text-sm">
            <span className="text-white/80">{t("register.agreePrefix")}</span>{" "}
            <button
              onClick={() => setShowAgreement(true)}
              type="button"
              className="font-semibold text-[#FFC000] underline underline-offset-4"
            >
              {t("register.userAgreement")}
            </button>
          </div>
        </div>
      </form>

      {showAgreement && (
        <UserAgreementModal
          url={agreementUrl} // 🔥 pass the url
          loading={!userAgreement} // 🔥 pass loading
          onClose={() => setShowAgreement(false)}
        />
      )}
    </div>
  );
}
