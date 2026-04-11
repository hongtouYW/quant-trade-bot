"use client";
import Image from "next/image";
import { memo, useState } from "react";
import { IMAGES } from "@/constants/images";
import MarqueeTitle from "../MarqueeTitle";
import { LazyLoadImage } from "react-lazy-load-image-component";
import "react-lazy-load-image-component/src/effects/blur.css";

function GameTileDetail({
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
      className="flex flex-col items-center gap-2 cursor-pointer select-none"
      onClick={onClick}
    >
      <div
        className={`relative w-full  overflow-hidden shadow-sm transition-all duration-200 flex items-center justify-center
        ${
          selected
            ? "border-[1.3px] border-[#FFC000] rounded-xl"
            : "border border-transparent"
        }
        rounded-2xl`}
      >
        {/* ⭐ Lazy image with blur + fallback */}
        <LazyLoadImage
          src={safeSrc}
          alt={alt}
          height={150}
          placeholderSrc={fallback}
          onError={() => {
            if (safeSrc !== fallback) {
              setImgSrc(fallback);
            }
          }}
          className="object-cover object-center w-full h-full rounded-xl"
          wrapperClassName="w-full h-full rounded-xl overflow-hidden"
        />

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
      {/* {title && title.trim() !== "" ? (
        <div className="flex items-center">
          <MarqueeTitle title={title} selected={selected} maxWidth={90} />
        </div>
      ) : null} */}
    </div>
  );
}

export default memo(GameTileDetail);
