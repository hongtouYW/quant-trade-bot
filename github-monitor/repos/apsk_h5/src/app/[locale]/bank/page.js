"use client";

import { useEffect, useMemo, useState } from "react";
import Image from "next/image";
import { useRouter } from "next/navigation";
import { useTranslations } from "next-intl";
import { IMAGES } from "@/constants/images";
import FastToggle from "@/components/shared/toogle";
import { getMemberInfo } from "@/utils/utility";
import {
  useDeleteBankMutation,
  useMemberBankQuery,
  useUpdateBankFastpayMutation,
} from "@/services/bankApi";
import { useDispatch } from "react-redux";
import { setCurrentBank } from "@/store/slice/bankDetailSlice";
import toast from "react-hot-toast";

// helper: mask account into "•••• •••• ••••" + last4
function maskAccount(value) {
  const digits = String(value || "").replace(/\D/g, "");
  const last4 = digits.slice(-4) || "";
  return { masked: "•••• •••• ••••", last4 };
}

export default function BankScreen() {
  const t = useTranslations();
  const router = useRouter();
  const dispatch = useDispatch();

  const [memberId, setMemberId] = useState(null);
  useEffect(() => {
    const info = getMemberInfo?.();
    if (info?.member_id) setMemberId(info.member_id);
  }, []);

  // fetch bank accounts
  const args = memberId ? { member_id: String(memberId) } : null;
  const { data, refetch, isLoading, isError } = useMemberBankQuery(args, {
    skip: !args,
    refetchOnMountOrArgChange: true,
    refetchOnFocus: true,
  });

  // keep local banks for optimistic update
  const [localBanks, setLocalBanks] = useState([]);
  useEffect(() => {
    if (Array.isArray(data?.data)) {
      setLocalBanks(data.data);
    }
  }, [data]);

  const [updateFastpay] = useUpdateBankFastpayMutation();
  const [deleteBank, { isLoading: isDeleting }] = useDeleteBankMutation();

  const handleToggleFast = (val, bankaccount_id) => {
    // 🔹 update UI immediately
    setLocalBanks((prev) =>
      prev.map((it) =>
        it.bankaccount_id === bankaccount_id
          ? { ...it, fastpay: val ? 1 : 0 }
          : it
      )
    );

    // 🔹 background API call
    onToggleFast(val, bankaccount_id);
  };

  const onToggleFast = async (val, bankaccount_id) => {
    try {
      await updateFastpay({
        bankaccount_id,
        status: val ? 1 : 0,
        member_id: memberId,
      }).unwrap();

      toast.success(t("bankAdd.toasts.saveSuccess"));
      // refetch later to be sure backend + local state are in sync
      await refetch();
    } catch (e) {
      toast.error(
        e?.data?.message
          ? String(e.data.message)
          : t("bankAdd.toasts.saveFailed")
      );

      // 🔹 rollback on error
      setLocalBanks((prev) =>
        prev.map((it) =>
          it.bankaccount_id === bankaccount_id
            ? { ...it, fastpay: val ? 0 : 1 }
            : it
        )
      );
    }
  };

  return (
    <div className="mx-auto max-w-[480px] min-h-dvh bg-[#00143D] text-white px-4 pb-8">
      {/* Header */}
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
          {t("bankDetail.title")}
        </h1>
      </div>

      {/* Section: existing banks */}
      <h2 className="mt-4 text-2xl font-bold">{t("bankDetail.addedTitle")}</h2>
      <p className="mt-2 text-sm text-white/70">{t("bankDetail.desc")}</p>

      {isLoading && (
        <div className="mt-4 rounded-2xl border border-white/20 bg-white/[0.02]">
          <div className="px-4 py-4 animate-pulse">
            <div className="flex items-center justify-between gap-3">
              <div className="min-w-0 w-full">
                <div className="h-5 w-32 rounded bg-white/10" />
                <div className="mt-2 h-5 w-48 rounded bg-white/10" />
              </div>
              <div className="h-5 w-5 rounded bg-white/10" />
            </div>
            <div className="mt-4 h-px bg-white/10" />
            <div className="mt-3 flex items-center justify-between">
              <div className="h-4 w-24 rounded bg-white/10" />
              <div className="h-6 w-12 rounded-full bg-white/10" />
            </div>
          </div>
        </div>
      )}

      {isError && (
        <p className="mt-3 text-sm text-red-300">
          {t("common.loadFailed")}{" "}
          <button
            onClick={() => refetch()}
            className="underline decoration-dotted"
          >
            {t("common.retry")}
          </button>
        </p>
      )}

      {localBanks.map((item) => {
        const id = item.bankaccount_id;
        const bankName = item.bank?.bank_name ?? "—";
        const account = item.bank_account ?? "";
        const { masked, last4 } = maskAccount(account);

        return (
          <div
            key={id}
            className="mt-4 rounded-2xl border border-white/20 bg-white/[0.02]"
          >
            <div
              role="button"
              tabIndex={0}
              onClick={() => {
                dispatch(setCurrentBank(item));
                router.push(`/bank/detail/${id}`);
              }}
              onKeyDown={(e) => {
                if (e.key === "Enter" || e.key === " ") {
                  dispatch(setCurrentBank(item));
                  router.push(`/bank/detail/${id}`);
                }
              }}
              className="w-full text-left outline-none"
            >
              <div className="px-4 py-4">
                <div className="flex items-center justify-between gap-3">
                  <div className="min-w-0">
                    <div className="text-lg font-semibold">{bankName}</div>
                    <div className="mt-2 text-base tracking-widest text-white/90">
                      {masked} <span className="tracking-normal">{last4}</span>
                    </div>
                  </div>
                  <svg
                    viewBox="0 0 24 24"
                    className="h-5 w-5 shrink-0 text-white/70"
                    aria-hidden="true"
                  >
                    <path
                      d="M9 6l6 6-6 6"
                      fill="none"
                      stroke="currentColor"
                      strokeWidth="2"
                      strokeLinecap="round"
                      strokeLinejoin="round"
                    />
                  </svg>
                </div>

                <div className="mt-4 h-px bg-white/10" />

                <div className="mt-3 flex items-center justify-between">
                  <span className="text-sm text-white/90">
                    {t("bankDetail.fastPay")}
                  </span>
                  <FastToggle
                    value={item.fastpay === 1}
                    onChange={(val) => handleToggleFast(val, id)}
                  />
                </div>
              </div>
            </div>
          </div>
        );
      })}

      {localBanks.length < 5 && (
        <>
          <h2 className="mt-8 text-2xl font-bold">
            {t("bankDetail.addNewTitle")}
          </h2>
          <p className="mt-2 text-sm text-white/70">
            {t("bankDetail.addNewDesc")}
          </p>

          <button
            onClick={() => router.push("/bank/create")}
            className="mt-4 w-full rounded-2xl border-2 border-dashed border-white/40 bg-white/[0.02] py-10"
          >
            <div className="flex flex-col items-center justify-center gap-3">
              <div className="relative h-10 w-10">
                <Image
                  src={IMAGES.iconPlus}
                  alt="plus"
                  fill
                  className="object-contain"
                  sizes="40px"
                  priority
                />
              </div>
              <div className="text-sm text-white/80">
                {t("bankDetail.addBankAccount")}
              </div>
            </div>
          </button>
        </>
      )}
    </div>
  );
}
