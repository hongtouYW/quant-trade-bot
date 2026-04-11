"use client";
import Image from "next/image";
import { memo, useState } from "react";
import { IMAGES } from "@/constants/images";
import MarqueeTitle from "../MarqueeTitle";
import { LazyLoadImage } from "react-lazy-load-image-component";
import "react-lazy-load-image-component/src/effects/blur.css";

function GameTile({
  src,
  title,
  alt,
  selected = false,
  isBookMark = false,
  onClick,
  type = "P",
}) {
  const fallback =
    type === "P" ? IMAGES.home.rightbar.provider : IMAGES.home.rightbar.game;

  const [imgSrc, setImgSrc] = useState(src);
  const safeSrc = imgSrc || fallback;

  return (
    <div
      className="flex flex-col items-center gap-1 cursor-pointer select-none"
      onClick={onClick}
    >
      {/* ⭐ OUTER: Aspect ratio (controls height) */}
      <div
        className={`relative w-full aspect-[1/1.25] rounded-2xl shadow-sm overflow-hidden
        ${
          selected
            ? "border-[1.3px] border-[#FFC000]"
            : "border border-transparent"
        }`}
      >
        {/* ⭐ INNER: Prevent hydration collapse */}
        <div className="absolute inset-0 w-full h-full">
          <LazyLoadImage
            src={safeSrc}
            alt={alt}
            placeholderSrc={fallback}
            onError={() => {
              if (safeSrc !== fallback) setImgSrc(fallback);
            }}
            className="w-full h-full object-cover object-center rounded-2xl"
            wrapperClassName="w-full h-full rounded-2xl overflow-hidden"
          />
        </div>

        {/* ⭐ Bookmark */}
        {Boolean(isBookMark) && (
          <div className="absolute top-1.5 right-1.5 z-10">
            <Image
              src={IMAGES.favorite}
              alt="favourite"
              width={22}
              height={22}
              className="object-contain scale-90"
            />
          </div>
        )}
      </div>

      {/* ⭐ Title */}
      {title?.trim() ? (
        <div className="flex items-center">
          <MarqueeTitle title={title} selected={selected} maxWidth={90} />
        </div>
      ) : null}
    </div>
  );
}

export default memo(GameTile);
