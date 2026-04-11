// /app/bank/select/page.js testing
"use client";

import { useContext } from "react";
import { useRouter } from "next/navigation";
import { useTranslations } from "next-intl";
import Image from "next/image";
import { UIContext } from "@/contexts/UIContext";
import { IMAGES } from "@/constants/images";

export default function BankPickerPage() {
  const t = useTranslations();
  const router = useRouter();
  const { filteredBanks, bankQuery, setBankQuery, setSelectedBankId } =
    useContext(UIContext);

  return (
    <div className="mx-auto max-w-[480px] min-h-dvh bg-[#00143D] text-white px-4 pb-8">
      {/* header (your exact style) */}
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
          {t("bankPicker.title")}
        </h1>
      </div>

      {/* search pill */}
      <div className="mt-3 flex items-center gap-2 rounded-full border border-white/30 bg-[#0A1F58] pl-4 pr-2 py-2">
        <input
          value={bankQuery}
          onChange={(e) => setBankQuery(e.target.value)}
          placeholder={t("bankPicker.searchPlaceholder")}
          className="w-full bg-transparent outline-none placeholder:text-white/50"
        />
        <button
          className="rounded-full px-4 py-2 font-semibold text-black active:scale-95"
          style={{
            background: "linear-gradient(180deg, #F8AF07 0%, #FFFC86 100%)",
          }}
        >
          {t("bankPicker.search")}
        </button>
      </div>

      {/* list */}
      <div className="mt-4 divide-y divide-white/10 rounded-2xl overflow-hidden bg-[#071A4A]">
        {filteredBanks.map((b) => (
          <button
            key={b.id}
            onClick={() => {
              setSelectedBankId(b.id);
              router.back();
            }}
            className="flex w-full items-center gap-4 px-4 py-4 active:bg-white/5"
          >
            <div className="relative h-10 w-10 rounded-full overflow-hidden bg-white">
              <Image
                src={b.icon}
                alt={b.name}
                fill
                className="object-contain p-1.5"
                sizes="40px"
              />
            </div>
            <div className="flex-1 text-left">{b.name}</div>
            <Image
              src={IMAGES.iconYellowRight}
              alt=">"
              width={18}
              height={18}
              className="opacity-70"
            />
          </button>
        ))}
      </div>
    </div>
  );
}
