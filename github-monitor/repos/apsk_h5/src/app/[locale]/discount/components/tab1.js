"use client";

import Image from "next/image";
import Link from "next/link";
import { useState, useEffect } from "react";
import { useTranslations } from "next-intl";
import { useDispatch } from "react-redux";

import DiscountSideBar from "@/components/home/DiscountSideBar";
import { getMemberInfo } from "@/utils/utility";
import { useGetPromotionListQuery } from "@/services/promotionApi";
import { setSelectedPromotion } from "@/store/slice/promotionSlice";
import SharedLoading from "@/components/shared/LoadingBar/ShareLoading";
import EmptyRecord from "@/components/shared/EmptyRecord";

export default function ActivityTab1() {
  const t = useTranslations("promotion");
  const dispatch = useDispatch();
  const info = getMemberInfo();

  const [activeId, setActiveId] = useState("newmemberreload");
  const [promotions, setPromotions] = useState([]);

  // ✅ Fetch API via RTK Query
  const { data, isLoading, isFetching } = useGetPromotionListQuery(
    {
      member_id: info?.member_id,
      // promotion_type: activeId,
      promotion_type: null,
    },
    { skip: !activeId },
  );

  // ✅ Clear list immediately when tab changes
  useEffect(() => {
    setPromotions([]); // clear old data
  }, [activeId]);

  // ✅ Update when new data comes in
  useEffect(() => {
    if (data?.data) setPromotions(data.data);
  }, [data]);

  return (
    <div className="flex bg-[#00143D] text-white">
      {/* Sidebar */}
      {/* <div className="w-[84px] shrink-0">
        <DiscountSideBar
          activeId={activeId}
          onSelect={(it) => setActiveId(it.id)}
        />
      </div> */}

      {/* Right content */}
      <div className="flex-1 px-3 pb-20 overflow-y-auto">
        <h1 className="text-lg font-semibold text-center py-3">{t("title")}</h1>

        {/* 🔄 Loading */}
        {isLoading && <SharedLoading />}

        {/* 🚫 No Data */}
        {!isLoading && !isFetching && promotions.length === 0 && (
          <EmptyRecord></EmptyRecord>
        )}

        {/* Promotion list */}
        {promotions.map((item) => (
          <Link
            key={item.promotion_id}
            href="/discount/detail"
            onClick={() => dispatch(setSelectedPromotion(item))}
          >
            <div className="rounded-xl overflow-hidden mb-4 border border-white/10 bg-[#0F214F]">
              <Image
                src={`${process.env.NEXT_PUBLIC_IMAGE_URL}/${item.photo}`}
                alt={item.title}
                width={400}
                height={140}
                className="w-full h-auto object-cover"
              />
              <div className="px-3 py-2">
                <p className="text-sm font-semibold text-[#FFC000]">
                  {item.title}
                </p>
                <p className="text-xs text-white/70 mt-1 leading-relaxed line-clamp-2">
                  {item.promotion_desc}
                </p>
              </div>
            </div>
          </Link>
        ))}
      </div>
    </div>
  );
}
