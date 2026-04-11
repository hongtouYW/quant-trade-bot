"use client";

import { useMemo, useState } from "react";
import { useRouter } from "next/navigation";
import { useTranslations } from "next-intl";
import Image from "next/image";

import { IMAGES } from "@/constants/images";
import { useAllBankQuery } from "@/services/bankApi"; // ← RTK Query
import { useAddBankFormStore } from "@/store/zustand/addBankForm";

export default function BankPickerPage() {
  const t = useTranslations();
  const router = useRouter();

  // local search state (replaces context.bankQuery)
  const [bankQuery, setBankQuery] = useState("");

  // call API
  const { data, isLoading, isError, refetch } = useAllBankQuery({});

  // normalize to an array
  const banks = useMemo(
    () => (Array.isArray(data?.data) ? data.data : []),
    [data]
  );
  const setSelectedBank = useAddBankFormStore((s) => s.setSelectedBank);
  const markFromPicker = useAddBankFormStore((s) => s.markFromPicker);

  // filter locally (replaces context.filteredBanks)
  const filteredBanks = useMemo(() => {
    const q = bankQuery.trim().toLowerCase();
    if (!q) return banks;
    return banks.filter((b) => {
      const name = (b.bank_name ?? "").toLowerCase();
      const code = (b.superpaybankcode ?? "").toLowerCase();
      const fpay = String(b.fpaybank_id ?? "");
      return name.includes(q) || code.includes(q) || fpay.includes(q);
    });
  }, [banks, bankQuery]);

  const getIcon = (g) => {
    if (!g?.icon) {
      return IMAGES.iconBankPlaceholder;
    }

    // Already a full URL
    if (g.icon.startsWith("http://") || g.icon.startsWith("https://")) {
      return g.icon;
    }

    // Relative path → prepend base URL
    return `${process.env.NEXT_PUBLIC_IMAGE_URL}/${g.icon}`;
  };

  return (
    <div className="mx-auto max-w-[480px] min-h-dvh bg-[#00143D] text-white  pb-8">
      {/* header */}
      <div className="relative flex items-center px-4  h-14">
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

      {/* search */}
      <div className="mt-3 px-4  flex items-center gap-2 rounded-full border border-white/30 bg-[#0A1F58] pl-4 pr-2 py-2">
        <input
          value={bankQuery}
          onChange={(e) => setBankQuery(e.target.value)}
          placeholder={t("bankPicker.searchPlaceholder")}
          className="w-full bg-transparent outline-none placeholder:text-white/50"
        />
      </div>

      {/* list */}
      <div className="mt-4 divide-y divide-[#354B9C]  overflow-hidden bg-[#162344] px-4">
        {isError && (
          <button
            onClick={() => refetch()}
            className="w-full py-3 text-left text-sm text-red-300"
          >
            {t("common.loadFailed")} — {t("common.retry")}
          </button>
        )}

        {isLoading && (
          <div className="px-4 py-4 animate-pulse">
            <div className="flex items-center gap-4">
              <div className="h-10 w-10 rounded-full bg-white/10" />
              <div className="h-4 w-40 rounded bg-white/10" />
            </div>
          </div>
        )}

        {!isLoading &&
          filteredBanks.map((b) => (
            <button
              key={b.bank_id}
              onClick={() => {
                setSelectedBank(b.bank_id, b.bank_name);
                markFromPicker(true);
                setTimeout(() => router.back(), 500); // 👈 short delay 50–100 ms
              }}
              className="flex w-full items-center gap-4 px-4 py-4 active:bg-white/5"
            >
              <div className="relative h-15 w-15 overflow-hidden">
                <Image
                  src={getIcon(b)}
                  alt={b.bank_name}
                  fill
                  className="object-contain p-1.5"
                  sizes="50px"
                />
              </div>
              <div className="flex-1 text-left">{b.bank_name}</div>
              <Image
                src={IMAGES.iconYellowRight}
                alt=">"
                width={10}
                height={10}
                className="opacity-70"
              />
            </button>
          ))}
      </div>
    </div>
  );
}
