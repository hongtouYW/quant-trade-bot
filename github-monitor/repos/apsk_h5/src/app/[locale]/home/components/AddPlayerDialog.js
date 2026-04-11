"use client";

import { useEffect, useState } from "react";
import Image from "next/image";
import { useTranslations } from "next-intl";
import SubmitButton from "@/components/shared/SubmitButton";
import { IMAGES } from "@/constants/images";
import { useRouter } from "next/navigation";
import { useGetPlayerPasswordQuery } from "@/services/authApi";
import { getMemberInfo } from "@/utils/utility";
import toast from "react-hot-toast";
import Link from "next/link";
import { useProviderStore } from "@/store/zustand/providerStore";

// Inline SVGs
const EyeIcon = ({ className = "w-5 h-5" }) => (
  <svg
    xmlns="http://www.w3.org/2000/svg"
    fill="none"
    viewBox="0 0 24 24"
    className={className}
  >
    <defs>
      <linearGradient id="eyeGradient" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" stopColor="#F8AF07" />
        <stop offset="100%" stopColor="#FFFC86" />
      </linearGradient>
    </defs>
    <path
      stroke="url(#eyeGradient)"
      strokeLinecap="round"
      strokeLinejoin="round"
      strokeWidth={1.5}
      d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.64 0 8.577 3.01 9.964 7.183.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.64 0-8.577-3.01-9.964-7.178z"
    />
    <path
      stroke="url(#eyeGradient)"
      strokeLinecap="round"
      strokeLinejoin="round"
      strokeWidth={1.5}
      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
    />
  </svg>
);

const EyeSlashIcon = ({ className = "w-5 h-5" }) => (
  <svg
    xmlns="http://www.w3.org/2000/svg"
    fill="none"
    viewBox="0 0 24 24"
    className={className}
  >
    <defs>
      <linearGradient id="eyeSlashGradient" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" stopColor="#F8AF07" />
        <stop offset="100%" stopColor="#FFFC86" />
      </linearGradient>
    </defs>
    <path
      stroke="url(#eyeSlashGradient)"
      strokeLinecap="round"
      strokeLinejoin="round"
      strokeWidth={1.5}
      d="M3.98 8.223A10.477 10.477 0 002.036 12c1.387 4.173 5.324 7.183 9.964 7.183 1.73 0 3.356-.41 4.77-1.137M6.228 6.228A9.974 9.974 0 0112 4.5c4.64 0 8.577 3.01 9.964 7.183a10.523 10.523 0 01-4.296 5.038M6.228 6.228L3 3m3.228 3.228l12.544 12.544M9.878 9.878a3 3 0 104.244 4.244"
    />
  </svg>
);

export default function AddPlayerPopup({ onClose }) {
  const { selectedProvider } = useProviderStore();

  const gameMemberId = selectedProvider?.gamemember_id;
  const providerId = selectedProvider?.providerId;
  const gamePlatformId = selectedProvider?.gamePid;
  const providerName = selectedProvider?.providerName;
  const gameLoginId = selectedProvider?.loginId;

  const t = useTranslations();
  const [showPwd, setShowPwd] = useState(true);

  const info = getMemberInfo();
  const {
    data: passwordData,
    isLoading: passwordLoading,
    isError: passwordError,
  } = useGetPlayerPasswordQuery(
    {
      gamemember_id: gameMemberId,
      user_id: info?.member_id,
    },
    {
      skip: !gameMemberId || !info?.member_id,
    }
  );

  const [form, setForm] = useState({
    playerId: gameLoginId,
    password: "",
  });

  useEffect(() => {
    if (passwordData?.password) {
      setForm((prev) => ({
        ...prev, // keep existing fields
        password: passwordData.password,
      }));
    }
  }, [passwordData]);

  const togglePwd = () => setShowPwd((p) => !p);
  const router = useRouter();
  return (
    <div className="fixed inset-0 z-[200] bg-black/60 backdrop-blur-[1px] flex items-center justify-center">
      <div className="mx-auto w-full max-w-[420px] rounded-2xl bg-[#0B1D48] text-white p-6 shadow-2xl">
        {/* Header */}

        <div className="mb-2 space-y-2">
          {/* Row 1: Brand (WL 真人) + right link */}

          <div className="relative flex items-center justify-center">
            <button
              onClick={onClose}
              className="absolute left-0 grid h-8 w-8 place-items-center rounded-full text-white/80 active:scale-95"
              aria-label={t("common.close")}
            >
              <Image
                src={IMAGES.iconYellowClose}
                alt="close"
                width={22}
                height={22}
                className="object-contain"
              />
            </button>

            <h4 className="text-lg text-center text-[#6F8EFF]">
              {providerName}
            </h4>

            <button
              //   onClick={onTitleLink} // optional handler
              className="absolute right-0 grid h-8 w-8 place-items-center text-[#FFC000] active:scale-95"
              aria-label={t("common.link")}
            >
              <Image
                src={IMAGES.iconLink}
                alt="link"
                width={22}
                height={22}
                className="object-contain"
              />
            </button>
          </div>

          {/* Row 2: Title + left close + right link */}
          <div className="relative flex items-center justify-center">
            <h3 className="text-base font-semibold text-center">
              {t("players.addTitle")}
            </h3>
          </div>
        </div>
        {/* Icon */}
        <div className="mx-auto mb-6 grid h-28 w-28 place-items-center rounded-full bg-[#001B4F]">
          <Image
            src={IMAGES.player.addnew}
            alt="Add player"
            width={200}
            height={300}
          />
        </div>

        {/* Player ID */}
        <div className="mb-4 flex items-center gap-3">
          <label className="w-24 text-sm font-semibold shrink-0">
            {t("players.myId")}
          </label>
          <div className="relative flex-1">
            <input
              type="text"
              value={form.playerId}
              onChange={(e) => setForm({ ...form, playerId: e.target.value })}
              className="w-full rounded-md bg-[#00143D] px-3 py-4 text-sm outline-none"
              style={{
                border: "0.5px solid",
              }}
            />
            <button
              type="button"
              className="absolute right-2 top-1/2 -translate-y-1/2 text-[#F8AF07]"
              onClick={() => {
                navigator.clipboard.writeText(form.playerId);
                toast.success(t("common.copySuccess"));
              }}
            >
              <Image
                src={IMAGES.iconCopy}
                alt="close"
                width={22}
                height={22}
                className="object-contain"
              />
            </button>
          </div>
        </div>

        {/* Password — one row */}
        <div className="mb-6 flex items-center gap-3">
          <label className="w-24 text-sm font-semibold shrink-0">
            {t("players.password")}
          </label>
          <div className="relative flex-1">
            <input
              readOnly
              type={showPwd ? "text" : "password"}
              value={form.password}
              onChange={(e) => setForm({ ...form, password: e.target.value })}
              className="w-full rounded-md bg-[#00143D] px-3 py-4 text-sm outline-none pr-10"
              style={{
                border: "0.5px solid",
              }}
            />
            <button
              type="button"
              className="absolute right-2 top-1/2 -translate-y-1/2 text-[#F8AF07]"
              onClick={() => {
                navigator.clipboard.writeText(form.password);
                toast.success(t("common.copySuccess"));
              }}
            >
              <Image
                src={IMAGES.iconCopy}
                alt="close"
                width={22}
                height={22}
                className="object-contain"
              />
            </button>
          </div>
        </div>

        {/* Buttons */}
        <div className="space-y-3">
          <Link href={`/game-start`} passHref>
            <SubmitButton>{t("players.startGame")}</SubmitButton>
          </Link>

          <button
            type="button"
            onClick={onClose}
            className="w-full mt-3 rounded-full border border-[#FFC000] py-3 text-sm font-medium text-[#FFC000] active:scale-95"
          >
            {t("common.backHome")}
          </button>
        </div>
      </div>
    </div>
  );
}
