"use client";

import Image from "next/image";
import { useTranslations } from "next-intl";
import { useRouter } from "next/navigation";
import { IMAGES } from "@/constants/images";

export default function RegisterAgreementPage() {
  const t = useTranslations();
  const router = useRouter();

  return (
    <div className="mx-auto max-w-[480px] min-h-dvh bg-[#00143D] text-white px-4 pb-8">
      {/* HEADER */}
      <div className="relative flex items-center h-5">
        {/* <button onClick={() => router.back()} className="z-10 cursor-pointer">
          <Image
            src={IMAGES.arrowLeft}
            alt="back"
            width={22}
            height={22}
            className="object-contain"
          />
        </button> */}

        {/* <h1 className="absolute inset-x-0 text-lg font-semibold text-center">
          {t("register.agreement.title")}
        </h1> */}
      </div>

      {/* CONTENT */}
      <div className="space-y-4 text-sm leading-relaxed">
        <p>{t("register.agreement.section1")}</p>
        <p>{t("register.agreement.section2")}</p>
        <p>{t("register.agreement.section3")}</p>
        <p>{t("register.agreement.section4")}</p>
        <p>{t("register.agreement.section5")}</p>

        <p>{t("register.agreement.section6")}</p>
        <p>{t("register.agreement.section7")}</p>
        <p>{t("register.agreement.section8")}</p>
        <p>{t("register.agreement.section9")}</p>
        <p>{t("register.agreement.section10")}</p>

        <p>{t("register.agreement.section11")}</p>
        <p>{t("register.agreement.section12")}</p>
        <p>{t("register.agreement.section13")}</p>
        <p>{t("register.agreement.section14")}</p>
        <p>{t("register.agreement.section15")}</p>

        <p>{t("register.agreement.section16")}</p>
      </div>
    </div>
  );
}
