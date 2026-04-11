"use client";

import Image from "next/image";
import { useTranslations } from "next-intl";
import { useRouter } from "next/navigation";
import { useContext, useState } from "react";
import { useSelector } from "react-redux";
import toast from "react-hot-toast";

import { IMAGES } from "@/constants/images";
import { EyeYellowSlashIcon, EyeYellowIcon } from "@/components/shared/EyeIcon";
import SubmitButton from "@/components/shared/SubmitButton";
import { useResetNewPasswordMutation } from "@/services/authApi";
import { UIContext } from "@/contexts/UIProvider";
import { extractError } from "@/utils/utility";

export default function ResetPasswordPage() {
  const t = useTranslations();
  const router = useRouter();
  const { setLoading } = useContext(UIContext);

  const { memberId } = useSelector((s) => s.resetPwd);

  const [showPwd1, setShowPwd1] = useState(false);
  const [showPwd2, setShowPwd2] = useState(false);
  const [password1, setPassword1] = useState("");
  const [password2, setPassword2] = useState("");
  const [errors, setErrors] = useState({});

  const [resetNewPassword] = useResetNewPasswordMutation();

  const handleSubmit = async (e) => {
    e.preventDefault();

    const newErrors = {};
    if (!password1.trim()) {
      newErrors.password1 = t("resetPwd.errors.passwordRequired");
    }
    if (!password2.trim()) {
      newErrors.password2 = t("resetPwd.errors.confirmRequired");
    }
    if (password1 && password2 && password1 !== password2) {
      newErrors.password2 = t("resetPwd.errors.notMatch");
    }

    setErrors(newErrors);
    if (Object.keys(newErrors).length > 0) return;

    setLoading(true);
    try {
      const payload = {
        member_id: memberId,
        newpassword: password1,
      };

      const result = await resetNewPassword(payload).unwrap();

      if (result?.status) {
        toast.success(result.message);
        router.push("/login"); // ✅ go to login
      } else {
        toast.error(result?.message || t("resetPwd.errors.failed"));
      }
    } catch (err) {
      const result = extractError(err);

      if (result.type === "validation") {
        setErrors(result.fieldErrors);
      } else {
        toast.error(result.message);
      }
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
            {t("resetPwd.title")}
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

        {/* Password rule line */}
        <div className="mt-3 flex items-center gap-2 text-sm text-white/80">
          <span className="grid h-5 w-5 place-items-center rounded-full bg-[#16A34A]">
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
          </span>
          <span>{t("resetPwd.rule")}</span>
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
