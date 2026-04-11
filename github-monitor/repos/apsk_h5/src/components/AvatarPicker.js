"use client";

import { useState, useMemo } from "react";
import Image from "next/image";
import { useTranslations } from "next-intl";

import { useGetAvatarListQuery } from "@/services/commonApi";
import { IMAGES } from "@/constants/images";

export default function AvatarPickerModal({
  open,
  onClose,
  onSelect,
  memberId,
}) {
  const t = useTranslations();

  // query runs only if open + memberId exist
  const { data } = useGetAvatarListQuery(
    open && memberId ? { member_id: memberId } : undefined,
    { skip: !open || !memberId }
  );

  const images = useMemo(() => data?.images ?? [], [data]);
  const [selected, setSelected] = useState(null);
  const [selectedFull, setSelectedFull] = useState(null);

  const handleConfirm = () => {
    if (selectedFull) onSelect?.(selectedFull);
    onClose?.();
  };

  if (!open) return null; // guard only on `open`

  return (
    <div
      className="fixed inset-0 z-[100] bg-black/60 backdrop-blur-[1px]"
      onClick={onClose}
    >
      <div
        className="absolute inset-x-0 bottom-0 mx-auto max-w-[480px] rounded-t-2xl bg-[#0B1D48] text-white pb-6"
        onClick={(e) => e.stopPropagation()}
      >
        {/* handle bar */}
        <div className="mx-auto mt-2 mb-2 h-1.5 w-12 rounded-full bg-white/30" />

        {/* header */}
        <div className="relative flex items-center h-14 px-4">
          <button onClick={onClose} className="z-10 cursor-pointer">
            <Image
              src={IMAGES.arrowLeft}
              alt="back"
              width={22}
              height={22}
              className="object-contain"
            />
          </button>
          <h1 className="absolute inset-x-0 text-lg font-semibold text-center">
            {t("avatar.title")}
          </h1>
          <button
            onClick={handleConfirm}
            disabled={!selected}
            className="z-10 ml-auto flex items-center justify-center 
             w-10 h-10 rounded-full 
             text-[#FFC000] text-lg font-bold"
          >
            <Image
              src={IMAGES.iconCheckmark}
              alt="confirm"
              width={20}
              height={20}
              className="object-contain"
            />
          </button>
        </div>

        {/* grid */}
        <div className="grid grid-cols-3 gap-3 px-4">
          {images.map((img) => (
            <button
              key={img.filename}
              onClick={() => {
                setSelected(img.filename);
                setSelectedFull(img.url);
              }}
              className={`relative overflow-hidden rounded-lg border-2 ${
                selected === img.filename
                  ? "border-[#FFC000]"
                  : "border-transparent"
              }`}
            >
              <Image
                src={img.url}
                alt={img.filename}
                width={120}
                height={120}
                className="object-cover w-full h-full"
              />
            </button>
          ))}
        </div>
      </div>
    </div>
  );
}
