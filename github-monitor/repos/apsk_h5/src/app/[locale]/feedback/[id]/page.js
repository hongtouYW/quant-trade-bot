"use client";

import { useEffect, useState } from "react";
import Image from "next/image";
import { useRouter, useParams } from "next/navigation";
import { IMAGES } from "@/constants/images";
import { useTranslations } from "next-intl";
import { getMemberInfo } from "@/utils/utility";
import { useGetMemberFeedbackListQuery } from "@/services/feedbackApi";

export default function FeedbackDetailPage() {
  const t = useTranslations();
  const router = useRouter();
  const { id } = useParams();
  const [info, setInfo] = useState(null);
  const [feedback, setFeedback] = useState(null);

  // 1️⃣ get member info
  useEffect(() => {
    const m = getMemberInfo();
    setInfo(m);
  }, []);

  // 2️⃣ load list then filter by id
  const { data, isLoading } = useGetMemberFeedbackListQuery(info?.member_id, {
    skip: !info?.member_id,
  });

  // 3️⃣ find detail once list ready
  useEffect(() => {
    if (!data?.data) return;
    const found = data.data.find((x) => String(x.feedback_id) === String(id));

    setFeedback(found);
  }, [data, id]);

  return (
    <div className="mx-auto max-w-[480px] min-h-dvh bg-[#00143D] text-white px-4 pb-8">
      {/* ✅ Header */}
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
          {t("feedback.detailTitle")}
        </h1>
      </div>

      {/* ✅ Loading */}
      {isLoading && (
        <div className="flex flex-col items-center justify-center py-10 text-white/70">
          <div className="h-8 w-8 animate-spin rounded-full border-2 border-white/30 border-t-[#F8AF07]" />
          <p className="mt-2 text-sm">{t("common.loading")}</p>
        </div>
      )}

      {/* ✅ Detail */}
      {feedback && (
        <div className="mt-4 space-y-4">
          {/* Title */}
          <div className="rounded-lg border border-white/40 px-4 py-3 text-sm">
            {feedback.title}
          </div>

          {/* Short desc */}
          <div className="min-h-[110px] rounded-lg border border-white/40 px-4 py-3 text-sm leading-relaxed whitespace-pre-wrap">
            {feedback.feedback_desc || (
              <span className="text-white/50">{t("feedback.noDesc")}</span>
            )}
          </div>

          {/* Large text area look (same as your screenshot bottom box) */}
          <div className="min-h-[180px] rounded-lg border border-white/40 px-4 py-3 text-sm whitespace-pre-wrap">
            {feedback.photo ? (
              <Image
                src={`${process.env.NEXT_PUBLIC_IMAGE_URL}/${feedback.photo}`}
                alt="photo"
                width={200}
                height={200}
                className="rounded-lg object-contain"
              />
            ) : (
              <p className="text-white/60">{t("feedback.noPhoto")}</p>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
