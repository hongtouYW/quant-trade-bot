"use client";
import GameTile from "@/components/shared/GameTile";
import HeroCarousel from "@/components/home/HomeBanner";
import HomeProfilePanel from "@/components/home/HomeProfilePanel";
import HomeSideBar from "@/components/home/HomeSideBar";
import { IMAGES } from "@/constants/images";
import { useLocale, useTranslations } from "next-intl";
import Image from "next/image";
import { useContext, useEffect, useState } from "react";
import Link from "next/link";
import { UIContext } from "@/contexts/UIProvider";
import { useRouter, usePathname } from "next/navigation";
import { clearAllCookie, clearCookie } from "@/utils/cookie";
import { COOKIE_NAMES } from "@/constants/cookies";
import { delayedRedirect, getMemberInfo } from "@/utils/utility";
import { useGetMemberViewQuery } from "@/services/authApi";
import HomeProfileWallet from "@/components/home/HomeProfileWallet";

export default function Setting() {
  const t = useTranslations();
  const locale = useLocale(); // "cn" or "en"
  const router = useRouter();
  const { setConfirmConfig } = useContext(UIContext);
  const [avatarSrc, setAvatarSrc] = useState("");
  const [info, setInfo] = useState(null);

  const items = [
    {
      key: "safety",
      icon: IMAGES.setting.articlePerson,
      label: t("profile.title"),
      href: "/profile",
    },

    {
      key: "safety",
      icon: IMAGES.setting.iconSafety,
      label: t("setting.safety"),
      href: "/security",
    },
    {
      key: "contact",
      icon: IMAGES.setting.iconHeadphone,
      label: t("setting.contact"),
      rightText: t("setting.contactNote"), // 灰色提示文字
      href: "/find-us",
    },
    {
      key: "download",
      icon: IMAGES.setting.iconQr,
      label: t("setting.download"),
      href: "/download",
    },
    // {
    //   key: "feedback",
    //   icon: IMAGES.setting.iconFeedback,
    //   label: t("setting.feedback"),
    //   href: "/feedback",
    // },

    {
      key: "language",
      icon: IMAGES.setting.iconGlobe,
      label: t("setting.language"),
      rightText: t(`language.current.${locale}`),
      href: "/language",
    },
    {
      key: "help",
      icon: IMAGES.setting.iconQuestion,
      label: t("setting.help"),
      href: "/help",
    },
    {
      key: "logout",
      icon: IMAGES.setting.squareArrowRight,
      label: t("setting.logout"),
      rightText: t("account.switch"),
      href: "/logout",
    },
  ];
  useEffect(() => {
    const member = getMemberInfo();
    setInfo(member);
  }, []);

  const {
    data: user,
    error,
    isLoading,
    isSuccess,
  } = useGetMemberViewQuery(
    info ? { member_id: info.member_id } : undefined, // body passed only when info exists
    {
      skip: !info?.member_id, // avoid running with empty info
    },
  );

  useEffect(() => {
    if (user != null) {
      if (user?.data?.avatar != null) {
        setAvatarSrc(user?.data?.avatar);
      }
    }
  }, [user]);

  const onLogoutClick = () => {
    setConfirmConfig({
      titleKey: "common.confirmLogout",
      messageKey: "setting.logoutConfirmMessage",
      confirmKey: "setting.logoutConfirm",
      cancelKey: "common.cancel",
      onConfirm: () => {
        clearAllCookie();
        // clearCookie(COOKIE_NAMES.BEARER_TOKEN);
        // clearCookie(COOKIE_NAMES.MEMBER_INFO);
        // clearCookie(COOKIE_NAMES.REFRESH_TOKEN);
        delayedRedirect(router, "/login", 500);
        // router.replace("/login");

        // your real logout here
        // localStorage.removeItem("auth_token");
        // document.cookie = "auth_token=; Max-Age=0; path=/";
        // router.replace("/login");
      },
      onCancel: () => {},
    });
  };

  return (
    <main className="min-h-dvh text-white">
      <div className="relative h-[250px] flex items-center justify-center">
        {/* Background */}
        <Image
          src={IMAGES.setting.background}
          alt="Background"
          fill
          sizes="(max-width: 480px) calc(100vw - 32px), 480px"
          priority
          className="object-cover"
        />

        <div className="absolute inset-0 bg-[#00143D]/70" />
        <div className="absolute inset-0 bg-gradient-to-b from-black/30 to-transparent" />

        <div className="absolute inset-x-0 top-0 z-30 pointer-events-none">
          <div className="pointer-events-auto">
            <HomeProfileWallet />
          </div>
        </div>

        {/* <div className="flex flex-col items-center gap-6 relative z-[10]">
          <button
            type="button"
            onClick={() => router.push("/profile")}
            className="relative h-36 w-36 rounded-full overflow-hidden shadow-lg 
                        border-1 border-[#F8AF07] active:opacity-90" // 👈 yellow border
            aria-label={t("profile.changeAvatar")}
          >
            <Image
              src={avatarSrc == "" ? IMAGES.avatarPlaceholder : avatarSrc}
              alt={t("profile.changeAvatar")}
              fill
              className="object-cover"
            />
          </button>

          <Link href="/profile">
            <button
              className="px-5 py-1 text-white text-sm rounded-[50px]"
              style={{
                backgroundColor: "#1B2D72",
                border: "1px solid #F8AF07",
              }}
            >
              {t("setting.profile")}
            </button>
          </Link>
        </div> */}
      </div>
      <div className="relative">
        <div className="absolute inset-x-0 top-[-98px] z-30">
          <HomeProfilePanel type="Setting" />

          <div className="mx-auto max-w-[480px] min-h-dvh bg-[#01133C] flex">
            <div className="bg-[#0B1D48] flex-1 overflow-y-auto">
              {items.map((item, idx) => {
                const RowInner = (
                  <>
                    {/* Left */}
                    <div className="flex items-center gap-3">
                      <Image
                        src={item.icon}
                        alt={item.label}
                        width={20}
                        height={20}
                        className="object-contain"
                      />
                      <span className="text-white text-sm">{item.label}</span>
                    </div>
                    {/* Right */}
                    <div className="flex items-center gap-2">
                      {item.rightText && (
                        <span className="text-xs text-white/60">
                          {item.rightText}
                        </span>
                      )}
                      <Image
                        src={IMAGES.setting.arrowRight}
                        alt="arrow"
                        width={24}
                        height={24}
                        className="object-contain"
                      />
                    </div>
                    {/* Divider */}
                    {idx !== items.length - 1 && (
                      <div className="absolute bottom-0 left-3 right-3 h-px bg-[#354B9C]" />
                    )}
                  </>
                );

                // Intercept logout: open modal instead of link navigation
                if (item.key === "logout") {
                  return (
                    <button
                      key={item.key}
                      type="button"
                      onClick={onLogoutClick}
                      className="relative w-full flex items-center justify-between px-4 py-5"
                    >
                      {RowInner}
                    </button>
                  );
                }

                return (
                  <Link
                    key={`${item.key}-${item.href}`}
                    href={item.href}
                    scroll={false}
                    className="relative flex items-center justify-between px-4 py-5"
                  >
                    {RowInner}
                  </Link>
                );
              })}
            </div>
          </div>
        </div>
      </div>
    </main>
  );
}
