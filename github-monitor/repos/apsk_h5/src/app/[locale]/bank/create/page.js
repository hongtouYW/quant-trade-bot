"use client";

import Image from "next/image";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useTranslations } from "next-intl";
import { useContext, useEffect, useMemo, useState } from "react";
import toast from "react-hot-toast";

import { IMAGES } from "@/constants/images";
import SubmitButton from "@/components/shared/SubmitButton";
import FastToggle from "@/components/shared/toogle";
import { useAddBankMutation, useMemberBankQuery } from "@/services/bankApi";
import { extractError, getMemberInfo } from "@/utils/utility";
import { useAddBankFormStore } from "@/store/zustand/addBankForm";
import { UIContext } from "@/contexts/UIProvider";
import { useWithdrawFormStore } from "@/store/zustand/withdrawStore";

export default function AddBankAccount() {
  const t = useTranslations();
  const router = useRouter();
  const { setLoading } = useContext(UIContext);
  // ✅ bind to Zustand store (no local useState for form)
  const {
    name,
    account,
    fastPay,
    selectedBankId,
    selectedBankName,
    fromPicker,
    setName,
    setAccount,
    setFastPay,
    setSelectedBank,
    markFromPicker,
    reset,
  } = useAddBankFormStore();

  // 1) First mount: clear unless we are returning from picker
  useEffect(() => {
    if (!fromPicker) {
      reset();
    } else {
      // consume the flag so subsequent renders don't skip reset accidentally
      markFromPicker(false);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []); // run once

  // member id
  const [memberId, setMemberId] = useState(null);
  useEffect(() => {
    const info = getMemberInfo?.();
    if (info?.member_id) setMemberId(String(info.member_id));
  }, []);

  // manual refetch handle (Option 2)
  const { refetch: refetchMemberBanks } = useMemberBankQuery(
    { member_id: memberId },
    { skip: !memberId }
  );

  const [addBank, { isLoading: isAdding }] = useAddBankMutation();
  const { data: bankList, isLoading: isBankLoading } = useMemberBankQuery(
    { member_id: memberId },
    { skip: !memberId }
  );

  // validation (uses translations keys from zh.json)
  const errors = useMemo(() => {
    const e = {};
    if (!name.trim()) e.name = "bankAdd.errors.nameRequired";
    if (selectedBankId == null) e.bank = "bankAdd.errors.bankRequired";
    if (!account.trim()) {
      e.account = "bankAdd.errors.accountRequired";
    } else if (!/^[0-9]+$/.test(account)) {
      e.account = "bankAdd.errors.accountDigitsOnly";
    } else if (account.length < 8) {
      e.account = "bankAdd.errors.accountMinLen";
    } else if (account.length > 24) {
      e.account = "bankAdd.errors.accountMaxLen";
    }
    return e;
  }, [name, selectedBankId, account]);

  const handleSubmit = async () => {
    if (Object.keys(errors).length > 0) {
      toast.error(t(Object.values(errors)[0]));
      return;
    }
    if (!memberId) {
      toast.error(t("common.networkError"));
      return;
    }

    const payload = {
      bank_account: account,
      bank_full_name: name,
      bank_id: String(selectedBankId),
      fastpay: fastPay ? 1 : 0,
      member_id: memberId,
    };

    try {
      // 🔥 Show global loading overlay
      setLoading(true);

      // 🧩 Call API (no toast.promise)
      const resp = await addBank(payload).unwrap();

      // ❌ API may return status false
      if (!resp?.status) {
        toast.error(resp?.message || t("bankAdd.toasts.saveFailed"));
        return;
      }

      // 🔁 Refresh bank list cache
      if (memberId) {
        try {
          await refetchMemberBanks();
        } catch {}
      }

      useWithdrawFormStore.getState().markShouldReloadBanks();

      // 🧹 Clear store
      reset();

      // 🎉 Success toast
      toast.success(t("bankAdd.toasts.saveSuccess"));

      // 🔙 Go back
      router.back();
    } catch (e) {
      const result = extractError(e);
      if (result.type === "validation") {
        // Show under each field
        Object.values(result.fieldErrors).forEach((msg) => {
          toast.error(msg); // show each field error in a toast
        });
      } else {
        toast.error(result.message);

        // Toast or global alert
      }
    } finally {
      // 📴 ALWAYS turn off loading overlay
      setLoading(false);
    }
  };

  useEffect(() => {
    setLoading(isBankLoading);
  }, [isBankLoading, setLoading]);

  // auto-set username if first bank exists
  useEffect(() => {
    if (Array.isArray(bankList?.data) && bankList.data.length > 0) {
      const firstBank = bankList.data[0];
      if (firstBank?.bank_full_name) {
        setName(firstBank.bank_full_name); // ✅ push into Zustand store
      }
    }
  }, [bankList, setName]);

  return (
    <div className="mx-auto max-w-[480px] min-h-dvh bg-[#00143D] text-white px-4 pb-8">
      {/* thin top loading bar during submit */}
      {isAdding && (
        <div className="fixed left-0 right-0 top-0 h-1 bg-gradient-to-r from-yellow-300 to-yellow-200 animate-pulse" />
      )}

      {/* Header */}
      <div className="relative flex items-center h-14">
        <button
          onClick={() => {
            router.back();
          }}
          className="z-10 cursor-pointer"
        >
          <Image
            src={IMAGES.arrowLeft}
            alt="back"
            width={22}
            height={22}
            className="object-contain"
          />
        </button>
        <h1 className="absolute inset-x-0 text-lg font-semibold text-center">
          {t("bankAdd.title")}
        </h1>
      </div>

      {/* Page title */}
      <h2 className="mt-3 text-2xl font-bold">{t("bankAdd.addTitle")}</h2>

      {/* Username */}
      <div className="mt-6">
        <label className="block text-sm mb-2">
          {t("bankAdd.username.label")}
        </label>
        <input
          type="text"
          disabled={Boolean(bankList?.data?.[0]?.bank_full_name)} // ✅ disable if first record exists
          value={name}
          onChange={(e) => setName(e.target.value)}
          placeholder={t("bankAdd.username.placeholder")}
          className="w-full rounded-lg border border-[#2D3E7A] bg-[#0A1F58] px-4 py-3 text-white placeholder:text-white/50 outline-none focus:border-[#4CCBF7]"
        />
        {errors.name && (
          <p className="mt-2 text-xs text-red-400">{t(errors.name)}</p>
        )}
        <p className="mt-2 text-xs text-white/60">
          {t("bankAdd.username.tip")}
        </p>
      </div>

      {/* Bank name (Select) */}
      <div className="mt-6">
        <label className="block text-sm mb-2">{t("bankAdd.bank.label")}</label>
        <div className="relative">
          <Link
            href="/bank/picker"
            onClick={() => markFromPicker(true)} // 👈 mark so we don't reset on return
            className="relative block rounded-lg border border-[#2D3E7A] bg-[#0A1F58] px-4 py-3"
          >
            <span className={selectedBankId != null ? "" : "text-white/50"}>
              {selectedBankId != null
                ? selectedBankName
                : t("bankAdd.bank.placeholder")}
            </span>
            <span className="absolute right-3 top-1/2 -translate-y-1/2">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
                <path d="M6 9l6 6 6-6" stroke="white" strokeWidth="2" />
              </svg>
            </span>
          </Link>
          {errors.bank && (
            <p className="mt-2 text-xs text-red-400">{t(errors.bank)}</p>
          )}
        </div>
      </div>

      {/* Bank account number */}
      <div className="mt-6">
        <label className="block text-sm mb-2">
          {t("bankAdd.account.label")}
        </label>
        <input
          type="text"
          inputMode="numeric"
          value={account}
          onChange={(e) => setAccount(e.target.value)}
          placeholder={t("bankAdd.account.placeholder")}
          className="w-full rounded-lg border border-[#2D3E7A] bg-[#0A1F58] px-4 py-3 text-white placeholder:text-white/50 outline-none focus:border-[#4CCBF7]"
        />
        {errors.account && (
          <p className="mt-2 text-xs text-red-400">{t(errors.account)}</p>
        )}
      </div>

      {/* Fast Pay toggle */}
      <div className="mt-6">
        <div className="flex items-center justify-between">
          <span className="text-sm">{t("bankAdd.fastPay.label")}</span>
          <FastToggle value={fastPay} onChange={setFastPay} />
        </div>
        <p className="mt-3 text-xs text-white/60">
          {t("bankAdd.fastPay.note")}
        </p>
      </div>

      {/* Submit */}
      <div className="mt-8">
        <SubmitButton onClick={handleSubmit}>
          {t("bankAdd.actions.submit")}
        </SubmitButton>
      </div>
    </div>
  );
}
