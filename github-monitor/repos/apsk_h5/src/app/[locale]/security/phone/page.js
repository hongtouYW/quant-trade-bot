"use client";

import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { useTranslations } from "next-intl";
import { IMAGES } from "@/constants/images";
import Image from "next/image";
import Link from "next/link";
import SubmitButton from "@/components/shared/SubmitButton";
import { getMemberInfo, maskPhone, maskPhoneCenter } from "@/utils/utility";
import { useGetMemberViewQuery } from "@/services/authApi";

export default function BindPhone() {
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

  const [phone, setPhone] = useState("");

  useEffect(() => {
    if (info?.phone != null) {
      setPhone(info?.phone);
    }
  }, [info]);

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
          {t("bindPhone.title")}
        </h1>
      </div>

      {/* Body */}
      <div className="px-6 mt-6 flex-1">
        <h2 className="text-lg font-bold">{t("bindPhone.boundTitle")}</h2>
        <p className="text-sm text-white/60 mt-2">
          {t("bindPhone.boundSubtitle")}
        </p>

        {/* Masked phone */}
        <div className="mt-6 rounded-lg bg-white/10 px-6 py-4 text-center text-xl font-semibold tracking-wide">
          {"+"} {maskPhoneCenter(phone)}
        </div>
      </div>
    </div>
  );
}
