"use client";

import Image from "next/image";
import { useTranslations } from "next-intl";
import { useRouter } from "next/navigation";
import { useContext, useEffect, useState } from "react";

import { IMAGES } from "@/constants/images";
import { EyeYellowSlashIcon, EyeYellowIcon } from "@/components/shared/EyeIcon";
import SubmitButton from "@/components/shared/SubmitButton";
import { UIContext } from "@/contexts/UIProvider";

import { useLoginMutation } from "@/services/authApi";
import { useDispatch } from "react-redux";
import { setOtpPayload } from "@/store/slice/otpVerifySlice";
import { delayedRedirect, extractError } from "@/utils/utility";
import { toast } from "react-hot-toast";
import { useTransferOutMutation } from "@/services/transactionApi";
import { clearAllCookie, setCookie } from "@/utils/cookie";
import { useGameStore } from "@/store/zustand/gameStore";
import { useProviderStore } from "@/store/zustand/providerStore";
import { COOKIE_NAMES, LOCAL_STORAGE_NAMES } from "@/constants/cookies";
import { useGetAgentIconQuery } from "@/services/commonApi";

export default function LoginPage() {
  const t = useTranslations();
  const router = useRouter();
  const [showPwd, setShowPwd] = useState(false);
  const { showPhonePicker } = useContext(UIContext);
  const [dial, setDial] = useState("+60");
  const [phone, setPhone] = useState("");
  const [password, setPassword] = useState("");
  const [login, { data, error, isLoading, isSuccess }] = useLoginMutation();
  const [errors, setErrors] = useState({});
  const { setLoading } = useContext(UIContext);
  const [rememberMe, setRememberMe] = useState(false);
  const dispatch = useDispatch();
  const [transferOut] = useTransferOutMutation();
  const { prevGameMemberId } = useProviderStore();
  const { clearPrevGameMemberId, clearPrevProviderId } =
    useProviderStore.getState();
  const { reset: resetGame } = useGameStore.getState();
  const { data: agentIconData } = useGetAgentIconQuery({
    member_id: null,
  });

  const logoSrc =
    agentIconData?.status && agentIconData?.icon
      ? `${process.env.NEXT_PUBLIC_IMAGE_URL}/${agentIconData.icon}`
      : IMAGES.login.logo;

  const handleLogin = async (e) => {
    e.preventDefault();

    const cleanPhone = phone?.replace(/\s+/g, "").trim() || "";

    const newErrors = {};
    if (!cleanPhone) {
      newErrors.phone = t("login.errors.phoneRequired");
    }
    if (!password.trim()) {
      newErrors.password = t("login.errors.passwordRequired");
    }
    setErrors(newErrors);

    if (Object.keys(newErrors).length === 0) {
      setLoading(true);
      try {
        const payload = {
          phone: `${dial.replace("+", "")}${cleanPhone}`,
          password,
        };

        const result = await login(payload).unwrap();

        if (result?.status) {
          if (!result?.needbinding) {
            clearAllCookie();
            clearPrevGameMemberId();
            clearPrevProviderId();
            sessionStorage.removeItem("wallet_member_fetched");

            if (result?.data == null) {
              dispatch(
                setOtpPayload({
                  phone: cleanPhone,
                  dial,
                  otpcode: result?.otpcode,
                  password,
                  verifyby: "phone",
                  module: "login",
                }),
              );

              router.push(`/otp-reset/verify`);
            } else {
              const { member_id, member_login, member_name, phone, prefix } =
                result.data;
              const { access_token, refresh_token, token_type } = result.token;
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
              localStorage.setItem(LOCAL_STORAGE_NAMES.JUST_LOGIN, "1");
              setCookie(COOKIE_NAMES.REFRESH_TOKEN, refresh_token);
              delayedRedirect(router, "/");
            }
          } else {
            var memberId = result?.member_id;
            sessionStorage.setItem("otp_bind_member_id", memberId);

            router.push("/otp-bind");
            //router.push to otp-bind and pass it use session storage
          }
        }
      } catch (err) {
        const result = extractError(err);

        if (result.type === "validation") {
          setErrors(result.fieldErrors);
        } else {
          toast.error(t("login.invalidCredentials"));
        }
      } finally {
        setLoading(false);
      }
    }
  };

  // Load from localStorage on mount
  useEffect(() => {
    const rememberedPhone = localStorage.getItem("rememberPhone");
    const rememberedDial = localStorage.getItem("rememberDial");

    if (rememberedPhone) {
      setPhone(rememberedPhone);
    }
    if (rememberedDial) {
      setDial(rememberedDial);
    }

    if (rememberedPhone || rememberedDial) {
      setRememberMe(true);
    }
  }, []);

  // Whenever rememberMe changes, save or clear storage
  useEffect(() => {
    if (rememberMe) {
      localStorage.setItem("rememberPhone", phone);
      localStorage.setItem("rememberDial", dial);
    } else {
      localStorage.removeItem("rememberPhone");
      localStorage.removeItem("rememberDial");
    }
  }, [rememberMe, phone, dial]);

  return (
    <div className="relative mx-auto max-w-[480px] min-h-dvh text-white px-4 pb-8">
      {/* Background (now visible) */}
      <Image
        src={IMAGES.login.bg} // make sure this path exists in /public or allowed domain
        alt="bg"
        fill
        priority
        className="object-cover"
      />
      {/* Dark overlay like screenshot */}
      <div className="absolute inset-0 bg-black/70" />
      <form onSubmit={handleLogin}>
        <div className="relative z-10">
          {/* Header (project-standard) */}
          <div className="relative flex items-center h-14">
            {/* <button
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
            </button> */}
            {/* <h1 className="absolute inset-x-0 text-lg font-semibold text-center">
            {t("login.title")}
          </h1> */}
          </div>

          {/* Logo */}
          <div className="mt-3 mb-30 flex justify-center">
            <div className="h-[120px] w-[300px] relative">
              <Image
                src={logoSrc}
                alt="logo"
                fill
                priority
                className="object-contain"
              />
            </div>
          </div>

          {/* Left title + subtitle (circled) */}
          <div className="mt-6">
            <p className="text-lg font-semibold">{t("login.title")}</p>
            <p className="mt-2 text-sm text-white/70">{t("login.subtitle")}</p>
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

          {/* Password (framed, with eye icon, circled) */}
          <div className="mt-3 rounded-xl border border-white/30 bg-black/40 px-3 py-3">
            <div className="flex items-center gap-2">
              <input
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                type={showPwd ? "text" : "password"}
                placeholder={t("login.password")}
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
          <div className="px-3">
            {errors.password && (
              <p className="col-span-2 text-[13px] text-red-400">
                {errors.password}
              </p>
            )}
          </div>

          {/* Forgot */}
          {/* Remember + Forgot row */}
          <div className="mt-2 flex items-center justify-between text-sm">
            {/* Remember me */}
            {/* <label className="flex items-center gap-2">
              <input
                type="checkbox"
                checked={rememberMe}
                onChange={(e) => setRememberMe(e.target.checked)}
                className="h-4 w-4 rounded border-gray-300 text-[#FFC000] focus:ring-[#FFC000]"
              />
              <span>{t("login.remember")}</span>
            </label> */}

            {/* Forgot */}
            <button
              type="button"
              className="text-[#FFC000]"
              onClick={() => router.push("/otp-password")}
            >
              {t("login.forgot")}
            </button>
          </div>

          {/* Submit (project-standard button) */}
          <div className="mt-5">
            <SubmitButton
              type="submit"
              disabled={isLoading}

              // onClick={() => {
              //   router.push("/home");
              // }}
            >
              {t("login.submit")}
            </SubmitButton>
          </div>

          {/* Divider with centered label (circled) */}
          {/* <div className="mt-7 flex items-center gap-3">
            <div className="h-px flex-1 bg-white/20" />
            <span className="text-sm text-white/70">
              {t("login.socialTitle")}
            </span>
            <div className="h-px flex-1 bg-white/20" />
          </div> */}

          {/* Social block (use exactly your mapping) */}
          {/* <div className="mt-4">
            <div className="flex items-center gap-3">
              {[
                { src: IMAGES.earn.iconWechat, alt: "wechat" },
                { src: IMAGES.earn.iconWatapps, alt: "whatsapp" },
                { src: IMAGES.earn.iconFb, alt: "facebook" },
                { src: IMAGES.earn.iconTelegram, alt: "telegram" },
              ].map((it, idx) => (
                <button type="button" key={idx} className="rounded-full py-1.5">
                  <Image
                    src={it.src}
                    alt={it.alt}
                    width={150}
                    height={40}
                    className="object-contain"
                  />
                </button>
              ))}
            </div>
          </div> */}

          {/* Register */}
          <div className="mt-8 text-center text-sm">
            <span className="text-white/70">{t("login.noAccount")}</span>{" "}
            <button
              type="button"
              className="font-semibold text-[#FFC000]"
              onClick={() => {
                router.push("/register");
              }}
            >
              {t("login.register")}
            </button>
            {/* <a href="lobbyclubsuncity://lobbyclubsuncity?account=netpy_ehi4548&password=Cc9999">
              Open Suncity App
            </a>
            <a href="evo888android://lobbyevoandroid?account=01924336139&password=Pp3333">
              Open EVO
            </a> */}
          </div>
        </div>
      </form>
    </div>
  );
}
