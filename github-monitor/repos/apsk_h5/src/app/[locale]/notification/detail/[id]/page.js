"use client";

import { useEffect, useMemo, useState } from "react";
import Image from "next/image";
import { useRouter, useParams } from "next/navigation";
import { IMAGES } from "@/constants/images";
import { useTranslations } from "next-intl";
import { formatDate, getMemberInfo } from "@/utils/utility";
import SharedLoading from "@/components/shared/LoadingBar/ShareLoading";
import {
  useGetNotificationListQuery,
  useMarkNotificationReadMutation,
} from "@/services/notificationApi";
import {
  useGetSliderListQuery,
  useMarkSliderReadMutation,
} from "@/services/commonApi";

export default function DetailPage() {
  const t = useTranslations();
  const router = useRouter();
  const { id } = useParams(); // 👈 still using useParams
  const info = useMemo(() => getMemberInfo(), []);
  const [detail, setDetail] = useState(null);
  const [markRead] = useMarkNotificationReadMutation(); // NEW
  const [markSliderRead] = useMarkSliderReadMutation(); // NEW

  const { data, isLoading, refetch } = useGetNotificationListQuery(
    info?.member_id ? { member_id: info.member_id } : {},
    { skip: !info?.member_id },
  );

  const { refetch: refetchMarque } = useGetSliderListQuery(
    info?.member_id ? { member_id: info.member_id } : {},
    { skip: !info?.member_id },
  );

  // 1️⃣ Load sessionStorage detail
  useEffect(() => {
    try {
      const stored = sessionStorage.getItem("detail_item");
      if (!stored) return;

      const parsed = JSON.parse(stored);
      const type = parsed._type;

      // find id from stored data
      const parsedId =
        parsed.id ||
        parsed.notification_id ||
        parsed.slider_id ||
        parsed.feedback_id;

      if (String(parsedId) === String(id)) {
        setDetail(parsed);
      }
    } catch {}
  }, [id]);

  // 2️⃣ Redirect only if missing data
  useEffect(() => {
    if (detail !== null) return;

    const timer = setTimeout(() => {
      // If still no detail, session failed
      if (!detail) {
        router.back();
      }
    }, 200);

    return () => clearTimeout(timer);
  }, [detail, router]);

  useEffect(() => {
    if (!detail) return;

    // Only notification has "read" API
    if (detail._type === "notification") {
      const id = detail.notification_id;

      markRead({
        member_id: info.member_id,
        notification_id: id,
        messagetype: detail.messagetype,
      })
        .unwrap()
        .then((resp) => {
          if (resp?.status == 1) {
            refetch();
          }
        })
        .catch((err) => {});
    } else if (detail._type === "slider") {
      const id = detail.slider_id;

      markSliderRead({
        member_id: info.member_id,
        slider_id: id,
      })
        .unwrap()
        .then((resp) => {
          if (resp?.status) {
            refetchMarque();
          }
        })
        .catch((err) => {});
    }
  }, [detail]);

  // 3️⃣ Back button with safety
  const handleBack = () => {
    router.back();
  };

  // 4️⃣ Loading Phase
  if (!detail) {
    return <SharedLoading />;
  }

  return (
    <div className="mx-auto max-w-[480px] min-h-dvh bg-[#00143D] text-white px-4 pb-8">
      {/* Header */}
      <div className="relative flex items-center h-14">
        <button onClick={handleBack} className="z-10 cursor-pointer">
          <Image
            src={IMAGES.arrowLeft}
            alt="back"
            width={22}
            height={22}
            className="object-contain"
          />
        </button>
        <h1 className="absolute inset-x-0 text-lg font-semibold text-center">
          {detail.title || t("common.detail")}
        </h1>
      </div>

      <div className="mt-4 space-y-4">
        {/* Title */}
        {/* <div className="rounded-lg border border-white/40 px-4 py-3 text-sm">
          {detail.title}
        </div> */}

        {/* Description */}
        <div
          className="min-h-[110px] rounded-lg bg-white/10 px-4 py-3 text-sm leading-relaxed whitespace-pre-wrap"
          dangerouslySetInnerHTML={{
            __html: (
              detail.notification_desc ||
              detail.slider_desc ||
              detail.feedback_desc ||
              detail.desc ||
              detail.message ||
              detail.template ||
              `<span class="text-white/50">${t("common.noDesc")}</span>`
            )?.replace(/\n/g, "<br />"),
          }}
        />
        {/* Extra */}
        {/* <div className="min-h-[180px] rounded-lg border border-white/40 px-4 py-3 text-sm whitespace-pre-wrap">
          {detail.photo ? (
            <Image
              src={`${process.env.NEXT_PUBLIC_IMAGE_URL}/${detail.photo}`}
              alt="photo"
              width={200}
              height={200}
              className="rounded-lg object-contain"
            />
          ) : (
            <p className="text-white/60">{t("common.noExtraContent")}</p>
          )}
        </div> */}

        {/* Date */}
        <p className="text-xs text-white/50 text-right">
          {detail.created_on ? formatDate(detail.created_on) : ""}
        </p>
      </div>
    </div>
  );
}
