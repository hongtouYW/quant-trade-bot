"use client";

import { useContext, useEffect, useRef, useState } from "react";
import Image from "next/image";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useTranslations } from "next-intl";
import { IMAGES } from "@/constants/images";
import { UIContext } from "@/contexts/UIProvider";
import { toast } from "react-hot-toast";
import { delayedRedirect, extractError, getMemberInfo } from "@/utils/utility";
import {
  useGetMemberViewQuery,
  useUpdateProfileMutation,
} from "@/services/authApi";
import { updateMemberInfo } from "@/utils/cookie";
import AvatarPickerModal from "@/components/AvatarPicker";

export default function ProfilePage() {
  const t = useTranslations();
  const router = useRouter();

  // ---- state ----
  const [username, setUsername] = useState("");
  const [userId, setUserId] = useState(""); // maybe readonly
  const [socialId, setSocialId] = useState("");
  const [birthday, setBirthday] = useState("");
  const [avatarSrc, setAvatarSrc] = useState("");
  const fileInputRef = useRef(null);
  const { showAvatarPicker } = useContext(UIContext);
  const [errors, setErrors] = useState({});
  const [info, setInfo] = useState(null);
  const { setLoading } = useContext(UIContext);
  const [avatarOpen, setAvatarOpen] = useState(false);

  const [
    updateProfile,
    {
      data: profileData,
      error: profileError,
      isLoading: profileIsLoading,
      isSuccess: profileIsSuccess,
    },
  ] = useUpdateProfileMutation();

  // read cookies on client
  useEffect(() => {
    const member = getMemberInfo();
    setInfo(member);
  }, []);

  const {
    data: user,
    error,
    isLoading,
    isSuccess,
    isFetching,
    refetch,
  } = useGetMemberViewQuery(
    info ? { member_id: info.member_id } : undefined, // body passed only when info exists
    {
      skip: !info?.member_id,
      refetchOnMountOrArgChange: false, // (default)// avoid running with empty info
    },
  );

  useEffect(() => {
    if (user != null) {
      setUsername(user?.data?.member_login);
      setUserId(user?.data?.member_id);
      setSocialId(user?.data?.whatsapp);
      if (user?.data?.avatar != null) {
        setAvatarSrc(user?.data?.avatar);
      }

      if (user?.data?.dob) {
        // API format: "2007-09-06 00:00:00"
        const formatted = user.data.dob.split(" ")[0]; // take only "2007-09-06"
        setBirthday(formatted);
      }
    }
  }, [user]);

  const handleSave = async (e) => {
    e.preventDefault();
    const newErrors = {};

    if (!username.trim()) {
      newErrors.username = t("profile.errors.usernameRequired");
    }

    if (!birthday.trim()) {
      newErrors.birthday = t("profile.errors.birthdayRequired");
    }

    if (Object.keys(newErrors).length > 0) {
      setErrors(newErrors);
      return; // ❌ stop here
    }

    setErrors({});
    if (Object.keys(newErrors).length === 0) {
      setLoading(true); // always reset
      try {
        const payload = {
          avatar: avatarSrc || "", // from your avatar picker
          dob: birthday ? `${birthday} 00:00:00` : "",
          member_id: userId, // from cookie or profile state
          member_name: username, // your display name (or socialId if that's what backend expects)
        };
        const result = await updateProfile(payload).unwrap();

        if (result?.status) {
          refetch();
          updateMemberInfo({
            member_id: userId,
            member_name: username,
          });
          toast.success(t("profile.saveSuccess"));

          setTimeout(() => {
            router.back();
          }, 1000);
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
    <div className="mx-auto max-w-[480px] min-h-dvh bg-[#041A3A] text-white relative">
      {/* Top half background */}
      <div className="relative w-full h-[20vh] min-h-[200px]">
        <Image
          src={IMAGES.setting.background}
          alt="Background"
          fill
          priority
          sizes="(max-width: 480px) 100vw, 480px" // 👈 correct responsive sizes
          className="object-cover"
        />
        {/* Top bar */}
        <div className="absolute left-0 right-0 top-0 flex items-center gap-3 p-4">
          <button onClick={() => router.back()} className="z-10 cursor-pointer">
            <Image
              src={IMAGES.arrowLeft}
              alt="back"
              width={22}
              height={22}
              className="object-contain"
            />
          </button>
        </div>

        {/* Avatar */}
        <div className="absolute left-1/2 -bottom-10 -translate-x-1/2">
          <button
            type="button"
            onClick={() => setAvatarOpen(true)}
            className="relative h-36 w-36 rounded-full overflow-hidden shadow-lg 
               border-1 border-[#F8AF07] active:opacity-90" // 👈 yellow border
            aria-label={t("profile.changeAvatar")}
          >
            <Image
              src={avatarSrc == "" ? IMAGES.avatarPlaceholder : avatarSrc}
              alt={t("profile.changeAvatar")}
              fill
              sizes="144px" // 👈 fixed size to match h-36 w-36
              className="object-cover"
            />
            <div className="absolute bottom-0 w-full bg-black/80 text-center text-[11px] text-[#F8AF07] py-1 z-10">
              {t("profile.changeAvatar")}
            </div>
          </button>
        </div>
      </div>

      {/* Body */}
      <div className="px-5 pt-14 pb-24">
        {/* Title */}
        <h1 className="text-2xl font-semibold mb-6">{t("profile.title")}</h1>

        {/* Username */}
        <div className="mb-4">
          <label className="block mb-2 text-sm text-white/80">
            {t("profile.username")}
          </label>
          <input
            type="text"
            value={username}
            onChange={(e) => setUsername(e.target.value)}
            placeholder={t("profile.usernamePlaceholder")}
            className="w-full rounded-md bg-transparent border border-white/25 px-3 py-3 text-sm text-[#F8AF07] placeholder:text-white/50 focus:outline-none focus:border-white/50"
          />

          {errors.username && (
            <p className="mt-1 text-[13px] text-red-400">{errors.username}</p>
          )}
        </div>

        {/* User ID (readonly) */}
        <div className="mb-4">
          <label className="block mb-2 text-sm text-white/80">
            {t("profile.userId")}
          </label>
          <input
            type="text"
            value={userId}
            readOnly
            className="w-full rounded-md bg-transparent border border-white/25 px-3 py-3 text-sm text-white placeholder:text-white/50 focus:outline-none"
          />
        </div>

        {/* Social (WhatsApp) */}
        <div className="mb-4">
          <label className="block mb-2 text-sm text-white/80">
            {t("profile.bindSocial")}
          </label>
          <div className="relative">
            <input
              type="text"
              readOnly
              value={socialId}
              onChange={(e) => setSocialId(e.target.value)}
              className="w-full rounded-md bg-transparent border border-white/25 px-3 py-3 pr-12 text-sm text-white placeholder:text-white/50 focus:outline-none focus:border-white/50"
            />
            <div className="absolute inset-y-0 right-3 flex items-center">
              <Image
                src={IMAGES.profile.whatsapp}
                alt="WhatsApp"
                width={20}
                height={20}
                className="object-contain"
              />
            </div>
          </div>
        </div>

        {/* Birthday */}
        <div className="mb-6">
          <label className="block mb-2 text-sm text-white/80">
            {t("profile.birthday")}
          </label>
          <div className="relative">
            <input
              type="date"
              value={birthday}
              onChange={(e) => setBirthday(e.target.value)}
              className="w-full rounded-md bg-transparent border border-white/25 
                 px-3 py-3 pr-10 text-sm text-white [color-scheme:dark] 
                 focus:outline-none focus:border-white/50 
                 [appearance:none] [&::-webkit-calendar-picker-indicator]:opacity-0 
                 [&::-webkit-calendar-picker-indicator]:absolute 
                 [&::-webkit-calendar-picker-indicator]:w-full 
                 [&::-webkit-calendar-picker-indicator]:h-full"
            />
            {/* custom yellow down icon */}
            <div className="pointer-events-none absolute inset-y-0 right-3 flex items-center">
              <Image
                src={IMAGES.yellowDown}
                alt="open"
                width={16}
                height={16}
                className="opacity-80"
                style={{ height: "auto" }} // 👈 keeps aspect ratio if width changes
              />
            </div>
          </div>

          {errors.birthday && (
            <p className="mt-1 text-[13px] text-red-400">{errors.birthday}</p>
          )}
        </div>

        {/* Save button */}
        <button
          type="button"
          onClick={handleSave}
          className="mt-4 w-full h-12 rounded-full font-semibold text-[#00143D]"
          style={{
            background: "linear-gradient(180deg, #F8AF07 0%, #FFFC86 100%)",
          }}
        >
          {t("common.save")}
        </button>
      </div>

      {avatarOpen && (
        <AvatarPickerModal
          open={avatarOpen}
          onClose={() => setAvatarOpen(false)}
          onSelect={(avatarUrl) => setAvatarSrc(avatarUrl)}
          memberId={info?.member_id} // 👈 parent passes this
        />
      )}
    </div>
  );
}
