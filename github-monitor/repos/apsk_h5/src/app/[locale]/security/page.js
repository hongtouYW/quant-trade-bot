"use client";

import Image from "next/image";
import { useRouter } from "next/navigation";
import { useTranslations } from "next-intl";
import { IMAGES } from "@/constants/images";
import { useGetMemberViewQuery } from "@/services/authApi";
import { useEffect, useMemo, useState } from "react";
import { getMemberInfo } from "@/utils/utility";

export default function SafetyPrivacy({ hrefs = {} }) {
  const t = useTranslations();
  const router = useRouter();

  const [info, setInfo] = useState(null);
  // read cookies on client
  useEffect(() => {
    const member = getMemberInfo();
    setInfo(member);
  }, []);

  const { data: user, isLoading } = useGetMemberViewQuery(
    info ? { member_id: info.member_id } : undefined,
    { skip: !info?.member_id }
  );

  const member = user?.data;

  const bindings = useMemo(() => {
    const phoneBound = !!member?.bindphone;
    const emailBound = !!member?.bindemail;
    const gaBound = !!member?.bindgoogle || member?.GAstatus === 1;
    return { phoneBound, emailBound, gaBound };
  }, [member]);

  const rows = [
    {
      key: "phone",
      icon: IMAGES.safety.phone,
      label: t("safety.items.phone"),
      status: bindings.phoneBound
        ? t("safety.status.bound")
        : t("safety.status.unbound"),
      href: hrefs.phone ?? "/security/phone",
    },
    {
      key: "email",
      icon: IMAGES.safety.email,
      label: t("safety.items.email"),
      status: bindings.emailBound
        ? t("safety.status.bound")
        : t("safety.status.unbound"),
      href: hrefs.email ?? "/security/email",
    },
    {
      key: "google",
      icon: IMAGES.safety.gmail,
      label: t("safety.items.googleAuth"),
      status: bindings.gaBound
        ? t("safety.status.bound")
        : t("safety.status.unbound"),
      href: hrefs.google ?? "/security/google",
    },
    // {
    //   key: "face",
    //   icon: IMAGES.safety.facescan,
    //   label: t("safety.items.face"),
    //   status: t("safety.status.unadded"),
    //   href: hrefs.face ?? "/security/face",
    // },
    // {
    //   key: "finger",
    //   icon: IMAGES.safety.fingerprint,
    //   label: t("safety.items.fingerprint"),
    //   status: t("safety.status.unadded"),
    //   href: hrefs.finger ?? "/security/fingerprint",
    // },
    {
      key: "changepin",
      icon: IMAGES.safety.changepin,
      label: t("safety.items.changePassword"),
      status: "",
      href: hrefs.changepin ?? "/security/change-password",
    },
  ];

  return (
    <div className="mx-auto max-w-[480px] min-h-dvh bg-[#0B1D48] text-white">
      {/* Header */}

      <div className="relative flex items-center h-14 px-4">
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
          {t("setting.safety")}
        </h1>
      </div>

      {/* List */}
      <div className="bg-[#162344]/60 backdrop-blur-sm">
        {rows.map((r, idx) => (
          <a
            key={r.key}
            href={r.href}
            className={[
              "flex items-center justify-between px-4 py-4",
              "border-b border-white/10",
              idx === rows.length - 1 ? "border-b-0" : "",
              "active:bg-white/5",
            ].join(" ")}
          >
            <div className="flex items-center gap-3">
              <div className="relative h-6 w-6 shrink-0">
                <Image
                  src={r.icon}
                  alt={r.key}
                  fill
                  className="object-contain"
                  sizes="24px"
                  priority
                />
              </div>
              <span className="text-sm">{r.label}</span>
            </div>

            <div className="flex items-center gap-2">
              {r.status ? (
                <span className="text-xs text-white/60">{r.status}</span>
              ) : null}
              {/* right chevron */}
              <div className="relative h-2 w-2">
                <Image
                  src={IMAGES.iconRight}
                  alt="arrow"
                  fill
                  className="object-contain opacity-80"
                />
              </div>
            </div>
          </a>
        ))}
      </div>
    </div>
  );
}
