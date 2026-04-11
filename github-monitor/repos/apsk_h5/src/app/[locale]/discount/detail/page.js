"use client";

import Image from "next/image";
import { useRouter } from "next/navigation";
import { useTranslations } from "next-intl";
import { IMAGES } from "@/constants/images";
import SubmitButton from "@/components/shared/SubmitButton";
import { useSelector } from "react-redux";

export default function DiscountDetail() {
  const router = useRouter();
  const t = useTranslations("discountDetail");
  const promotion = useSelector((state) => state.promotion.selectedPromotion);

  return (
    <div className="relative mx-auto max-w-[480px] min-h-dvh bg-[#00143D] text-white px-4 pb-28">
      {/* 🔹 Header */}
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
          {promotion?.title || t("title")}
        </h1>
      </div>

      {/* 🔹 No Data */}
      {!promotion ? (
        <div className="flex flex-col items-center justify-center py-10 text-white/60">
          <p className="text-sm">{t("noData")}</p>
        </div>
      ) : (
        <>
          {/* 🔹 Banner (Full Width) */}
          {promotion.photo && (
            <div className="relative mt-3 overflow-hidden rounded-xl ring-1 ring-white/10">
              <Image
                src={`${process.env.NEXT_PUBLIC_IMAGE_URL}/${promotion.photo}`}
                alt={promotion.title}
                width={720}
                height={280}
                className="w-full h-auto object-cover"
                priority
              />
            </div>
          )}

          {/* 🔹 Description */}
          <div className="mt-5 text-sm leading-relaxed whitespace-pre-wrap text-white/90">
            {promotion.promotion_desc || (
              <span className="text-white/50">{t("noAnswer")}</span>
            )}
          </div>
        </>
      )}

      {/* 🔹 Sticky Footer */}
      {/* <div className="fixed bottom-0 left-1/2 w-full max-w-[480px] -translate-x-1/2 bg-[#00143D] px-4 pb-6 pt-3 border-t border-white/10">
        <div className="grid grid-cols-[1fr_1fr] items-center gap-4">
       
          <button
            onClick={() => router.back()}
            className="w-full rounded-full py-3 text-sm font-semibold text-black active:scale-95"
            style={{
              background: "linear-gradient(180deg, #2EE58D 0%, #14C86E 100%)",
              boxShadow: "0 8px 24px rgba(20,200,110,0.25)",
            }}
          >
            {t("actions.play")}
          </button>

        
          <SubmitButton onClick={() => router.push("/topup")}>
            {t("actions.deposit")}
          </SubmitButton>
        </div>
      </div> */}

      {/* tiny separator for visual balance */}
      <div className="mx-auto mt-6 h-1 w-40 rounded-full bg-white/30" />
    </div>
  );
}
