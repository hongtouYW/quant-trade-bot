"use client";

import { IMAGES } from "@/constants/images";
import React from "react";
import Image from "next/image";
export default function SharedLoading() {
  return (
    <div
      className={
        "flex flex-col items-center justify-center py-12 text-white/70 "
      }
    >
      <Image
        src={IMAGES.common.loadingBar}
        alt="Loading"
        width={150}
        height={150}
        className="object-contain"
        priority
      />
    </div>
  );
}
