"use client";

import Image from "next/image";
import dynamic from "next/dynamic";
import { memo, useMemo } from "react";
const Carousel = dynamic(() => import("react-multi-carousel"), { ssr: false });
import "react-multi-carousel/lib/styles.css";
import { IMAGES } from "@/constants/images";
import { useRouter } from "next/navigation";
import { useGetNotificationListQuery } from "@/services/notificationApi";
import { useGetSliderListQuery } from "@/services/commonApi";
import { getMemberInfo } from "@/utils/utility";
import HomeProfileWallet from "../HomeProfileWallet";

function IconCircle({ src, alt, hasUnread }) {
  return (
    <div className="relative grid h-12 w-12 place-items-center">
      <Image
        src={src}
        alt={alt}
        width={44}
        height={44}
        className="w-9 h-9 object-contain"
      />

      {/* 🔵 Blue dot for unread */}
      {hasUnread && (
        <span
          className="
          absolute 
          top-1 right-2
          h-[14px] w-[14px]
          rounded-full 
          bg-[#00F2FF] 
          border-[2px] border-[#F8AF07]
        "
        />
      )}
    </div>
  );
}

const MemoIconCircle = memo(IconCircle);

function Dot({ onClick, active }) {
  return (
    <button
      onClick={onClick}
      className={`mx-1 inline-block h-1.5 rounded-full transition-all ${
        active ? "w-3 bg-yellow-400" : "w-1.5 bg-yellow-400/50"
      }`}
      type="button"
    />
  );
}
const MemoDot = memo(Dot);

function HeroCarouselBase({ banners, logo911, search, ring }) {
  const router = useRouter();
  const info = useMemo(() => getMemberInfo(), []);
  const responsive = useMemo(
    () => ({
      desktop: { breakpoint: { max: 3000, min: 1025 }, items: 1 },
      tablet: { breakpoint: { max: 1024, min: 481 }, items: 1 },
      mobile: { breakpoint: { max: 480, min: 0 }, items: 1 },
    }),
    [],
  );

  // const { data: noticeData } = useGetNotificationListQuery(
  //   info?.member_id ? { member_id: info.member_id } : {},
  //   {
  //     skip: !info?.member_id,
  //     refetchOnMountOrArgChange: true,
  //   },
  // );

  // const noticeUnread =
  //   noticeData?.data?.filter((n) => n.is_read === 0).length || 0;
  // const marqueeUnread =
  //   marqueData?.data?.filter((n) => n.is_read === 0).length || 0;

  // const hasUnread = noticeUnread > 0 || marqueeUnread > 0;

  return (
    <div className="relative">
      {/* ===== Overlay: HomeProfileWallet ===== */}
      <div className="absolute inset-x-0 top-0 z-30 pointer-events-none">
        <div className="pointer-events-auto">
          <HomeProfileWallet />
        </div>
      </div>

      {/* ======= Carousel ======= */}
      <Carousel
        responsive={responsive}
        infinite
        arrows={false}
        autoPlay={banners.length > 1}
        draggable={banners.length > 1}
        swipeable={banners.length > 1}
        showDots={banners.length > 1}
        autoPlaySpeed={4000}
        containerClass="!h-[200px]"
        itemClass="!h-[200px]"
        dotListClass="!top-[10px]" // move dots up slightly
        customDot={<MemoDot />}
      >
        {banners.map((banner, idx) => (
          <div key={idx} className="relative h-[150px] w-full overflow-hidden">
            {/* Background */}
            <Image
              src={banner.background}
              alt={`Banner ${idx + 1}`}
              fill
              priority={idx === 0}
              className="object-cover"
              sizes="(max-width: 480px) calc(100vw - 32px), 480px"
            />

            {/* Gradient overlay */}
            <div className="absolute inset-0 bg-[#00143D]/70" />
            <div className="absolute inset-0 bg-gradient-to-b from-black/30 to-transparent" />

            {/* word image */}
            {/* {banner.word && (
              <div className="absolute left-4 right-4 bottom-8">
                <Image
                  src={banner.word}
                  alt="Banner word"
                  width={320}
                  height={80}
                  className="h-auto w-[82%] max-w-[340px]"
                />
              </div>
            )} */}
          </div>
        ))}
      </Carousel>
    </div>
  );
}

export default memo(HeroCarouselBase);
