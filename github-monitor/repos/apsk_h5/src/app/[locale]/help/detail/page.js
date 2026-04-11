"use client";

import { useEffect } from "react";
import Image from "next/image";
import { useRouter } from "next/navigation";
import { useSelector, useDispatch } from "react-redux";
import { useTranslations } from "next-intl";
import { IMAGES } from "@/constants/images";
import { clearSelectedQuestion } from "@/store/slice/helpSlice";

export default function HelpDetailPage() {
  const t = useTranslations();
  const router = useRouter();
  const dispatch = useDispatch();
  const question = useSelector((state) => state.help.selectedQuestion);

  const formatDate = (dateStr) => {
    if (!dateStr) return "";
    const date = new Date(dateStr);
    return isNaN(date.getTime())
      ? ""
      : date.toLocaleString("zh-CN", {
          year: "numeric",
          month: "2-digit",
          day: "2-digit",
          hour: "2-digit",
          minute: "2-digit",
        });
  };

  return (
    <div className="mx-auto max-w-[480px] min-h-dvh bg-[#00143D] text-white px-4 pb-8">
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
          {t("help.detailTitle")}
        </h1>
      </div>

      {/* 🔹 No Data */}
      {!question ? (
        <div className="flex flex-col items-center justify-center py-10 text-white/60">
          <p className="text-sm">{t("common.noData")}</p>
        </div>
      ) : (
        <>
          {/* 🔹 Combined Info Box */}
          <div className="mt-4 rounded-xl bg-[#0F214F] border border-white/10 px-4 py-4 text-white">
            {/* Title */}
            <div className="text-sm font-semibold text-[#FFC000]">
              {question.title}
            </div>

            {/* Datetime */}
            {(question.created_on || question.updated_on) && (
              <div className="mt-1 text-xs text-white/50">
                {t("help.createdOn")}:{" "}
                {formatDate(question.updated_on || question.created_on)}
              </div>
            )}

            {/* Description */}
            <div className="mt-3 text-sm leading-relaxed whitespace-pre-wrap text-white/90">
              {question.question_desc || (
                <span className="text-white/50">{t("help.noAnswer")}</span>
              )}
            </div>

            {/* Picture */}
            {question.picture && (
              <div className="mt-4 flex justify-center">
                <Image
                  src={`${process.env.NEXT_PUBLIC_IMAGE_URL}/${question.picture}`}
                  alt="help image"
                  width={260}
                  height={260}
                  className="rounded-lg object-contain"
                />
              </div>
            )}
          </div>
        </>
      )}
    </div>
  );
}
