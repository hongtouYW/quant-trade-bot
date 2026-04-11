"use client";

import Image from "next/image";
import { useTranslations } from "next-intl";
import { useParams, useRouter } from "next/navigation";
import { useContext, useEffect, useState } from "react";

import { IMAGES } from "@/constants/images";
import { EyeYellowSlashIcon, EyeYellowIcon } from "@/components/shared/EyeIcon";
import SubmitButton from "@/components/shared/SubmitButton";
import { extractError, getMemberInfo } from "@/utils/utility";
import { useChangeGamePasswordMutation } from "@/services/authApi";
import toast from "react-hot-toast";
import { UIContext } from "@/contexts/UIProvider";

export default function ResetPasswordPage() {
  const t = useTranslations();
  const router = useRouter();

  const [showPwd1, setShowPwd1] = useState(false);
  const [showPwd2, setShowPwd2] = useState(false);
  const [password1, setPassword1] = useState("");
  const [password2, setPassword2] = useState("");
  const [errors, setErrors] = useState({});
  const params = useParams();
  const { setLoading } = useContext(UIContext);

  const [mounted, setMounted] = useState(false);
  useEffect(() => setMounted(true), []);
  const [changeGamePassword, { isLoading }] = useChangeGamePasswordMutation();

  const [info, setInfo] = useState(null);
  // Load member info only on client
  useEffect(() => {
    if (!mounted) return;
    const member = getMemberInfo();
    setInfo(member || null);
  }, [mounted]);

  const [playerId, setPlayerId] = useState(null);
  useEffect(() => {
    // params.id is only reliable after mount for hydration
    if (mounted && params?.gmemberId) setPlayerId(params.gmemberId);
  }, [mounted, params]);

  const handleSubmit = async (e) => {
    e.preventDefault();
    const newErrors = {};

    if (!password1.trim())
      newErrors.password1 = t("resetPwd.errors.passwordRequired");
    if (!password2.trim())
      newErrors.password2 = t("resetPwd.errors.confirmRequired");
    if (password1 && password2 && password1 !== password2)
      newErrors.password2 = t("resetPwd.errors.notMatch");

    setErrors(newErrors);
    if (Object.keys(newErrors).length > 0) return;

    setLoading(true);
    try {
      const result = await changeGamePassword({
        member_id: info?.member_id,
        gamemember_id: playerId,
        password: password1,
      }).unwrap();

      if (result?.status) {
        toast.success(result.message);

        setTimeout(() => {
          router.back();
        }, 1200);
      } else {
        toast.error(result?.message || t("resetPwd.errors.failed"));
      }
    } catch (err) {
      const result = extractError(err);

      if (result.type === "validation") {
        // Show under each field
        setErrors(result.fieldErrors);
      } else {
        toast.error(t("login.invalidCredentials"));

        // Toast or global alert
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="mx-auto max-w-[480px] min-h-dvh bg-[#00143D] text-white px-4 pb-8">
      {/* Background */}
      {/* <Image
        src={IMAGES.login.bg}
        alt="bg"
        fill
        priority
        className="object-cover"
      /> */}
      {/* <div className="absolute inset-0 bg-black/70" /> */}

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
            {t("resetPwd.title")}
          </h1>
        </div>

        {/* Logo */}
        {/* <div className="mt-3 flex justify-center">
          <Image
            src={IMAGES.login.logo}
            alt="logo"
            width={200}
            height={100}
            className="object-contain"
          />
        </div> */}

        {/* Title + subtitle */}
        <div className="mt-6">
          <p className="text-lg font-semibold">{t("resetPwd.title")}</p>
          <p className="mt-2 text-sm text-white/70">{t("resetPwd.subtitle")}</p>
        </div>

        {/* New password */}
        <div className="mt-5 rounded-xl border border-white/30 bg-black/40 px-3 py-3">
          <div className="flex items-center gap-2">
            <input
              type={showPwd1 ? "text" : "password"}
              value={password1}
              onChange={(e) => setPassword1(e.target.value)}
              placeholder={t("resetPwd.newPassword")}
              className="flex-1 bg-transparent text-sm outline-none placeholder:text-[#F8AF07]/80"
            />
            <button onClick={() => setShowPwd1((v) => !v)} className="p-1">
              {showPwd1 ? <EyeYellowIcon /> : <EyeYellowSlashIcon />}
            </button>
          </div>
          {errors.password1 && (
            <p className="mt-1 text-xs text-red-500">{errors.password1}</p>
          )}
        </div>

        {/* Confirm password */}
        <div className="mt-3 rounded-xl border border-white/30 bg-black/40 px-3 py-3">
          <div className="flex items-center gap-2">
            <input
              type={showPwd2 ? "text" : "password"}
              value={password2}
              onChange={(e) => setPassword2(e.target.value)}
              placeholder={t("resetPwd.confirmPassword")}
              className="flex-1 bg-transparent text-sm outline-none placeholder:text-[#F8AF07]/80"
            />
            <button onClick={() => setShowPwd2((v) => !v)} className="p-1">
              {showPwd2 ? <EyeYellowIcon /> : <EyeYellowSlashIcon />}
            </button>
          </div>
          {errors.password2 && (
            <p className="mt-1 text-xs text-red-500">{errors.password2}</p>
          )}
        </div>

        {/* Submit */}
        <div className="mt-6">
          <SubmitButton onClick={handleSubmit}>
            {t("resetPwd.submit")}
          </SubmitButton>
        </div>
      </div>
    </div>
  );
}
