"use client";

import Image from "next/image";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useTranslations } from "next-intl";

import { IMAGES } from "@/constants/images";
import SubmitButton from "@/components/shared/SubmitButton";
import { useContext, useEffect, useMemo, useRef, useState } from "react";
import BalanceBar from "@/components/shared/TransferBalanceBar";
import { extractError, getMemberInfo, maskPhoneCompact } from "@/utils/utility";
import { UIContext } from "@/contexts/UIProvider";
import { useLazyMemberBankQuery, useMemberBankQuery } from "@/services/bankApi";
import { useBalanceStore } from "@/store/zustand/balanceStore";
import {
  useWithdrawMutation,
  useWithdrawQrMutation,
} from "@/services/transactionApi";
import { toast } from "react-hot-toast";
import UISelect from "@/components/shared/DropdownSelect";
import { useGetMemberViewQuery } from "@/services/authApi";
import { refreshBalancesCore } from "@/utils/TransferUti";
import {
  useLazyGetMemberViewQuery,
  useLazyGetPlayerViewQuery,
} from "@/services/authApi";
import SelectBankModal from "./components/SelectBankModal";
import { useWithdrawFormStore } from "@/store/zustand/withdrawStore";
import { skipToken } from "@reduxjs/toolkit/query";
import { useMarqueeStore } from "@/store/zustand/marqueStore";

export default function WithdrawPage() {
  const router = useRouter();
  const t = useTranslations();
  const withdrawPlaceholder = "${withdraw}";
  const addItem = useMarqueeStore((s) => s.addItem);
  const [info, setInfo] = useState(null);
  const { setLoading } = useContext(UIContext);
  const [type, setType] = useState("transfer");

  // Zustand: only for amount
  const amount = useWithdrawFormStore((s) => s.amount);
  const setAmount = useWithdrawFormStore((s) => s.setAmount);

  // React local state for bank data
  const [selectedBankId, setSelectedBankId] = useState(null);
  const [selectedBankAccount, setSelectedBankAccount] = useState("");
  const [selectedBankFullName, setSelectedBankFullName] = useState("");
  const [bankBizName, setBankBizName] = useState("");
  const shouldReloadBanks = useWithdrawFormStore((s) => s.shouldReloadBanks);
  const clearShouldReloadBanks = useWithdrawFormStore(
    (s) => s.clearShouldReloadBanks,
  );

  const [showBankModal, setShowBankModal] = useState(false);
  // const [amount, setAmount] = useState("");
  const [errors, setErrors] = useState({ amount: "", bank: "" });
  const [withdraw] = useWithdrawMutation();
  const { setIsGlobalLoading, isGlobalLoading } = useContext(UIContext);
  const [getWithdrawQr, { data, isLoading, isError }] = useWithdrawQrMutation();
  // Zustand (credit always global; points only used when gameMemberId exists)
  const {
    credits,
    points,
    setCredits,
    setPoints,
    isTransferring,
    setIsTransferring,
    markTransferDone,
  } = useBalanceStore();

  const balanceRef = useRef(null);
  const memberBankQueryArg = useMemo(() => {
    return info?.member_id ? { member_id: info.member_id } : skipToken;
  }, [info?.member_id]);

  const [loadBanks, { data: banksData, isLoading: lazyBankLoading }] =
    useLazyMemberBankQuery();

  const {
    refetch: refetchMemberBanks,
    isUninitialized,
    currentData: memberCurrentData,
    data: memberBankData,
    isLoading: memberBankLoading,
    isError: memberBankError,
  } = useMemberBankQuery(memberBankQueryArg, {
    refetchOnMountOrArgChange: true,
    refetchOnFocus: true,
  });
  const [bankMeta, setBankMeta] = useState(null);
  const chargeRate = Number(bankMeta?.charge || 0); // 5
  const hasGame = bankMeta?.has_game !== false;
  const numericAmount = Number(amount || 0);

  const chargeAmount = useMemo(() => {
    if (!numericAmount || !chargeRate) return 0;
    return (numericAmount * chargeRate) / 100;
  }, [numericAmount, chargeRate]);

  const finalAmount = useMemo(() => {
    return Math.max(numericAmount - chargeAmount, 0);
  }, [numericAmount, chargeAmount]);

  // Lazy queries
  const [
    triggerGetMemberView,
    { isLoading: memberLoading, isFetching: memberFetching },
  ] = useLazyGetMemberViewQuery();
  const [
    triggerGetPlayerView,
    { isLoading: playerLoading, isFetching: playerFetching },
  ] = useLazyGetPlayerViewQuery();

  // member API
  const {
    data: user,
    isFetching,
    refetch: refetchMember,
  } = useGetMemberViewQuery(info ? { member_id: info.member_id } : undefined, {
    skip: !info?.member_id,
  });

  const handleSubmit = async () => {
    // 1) validate
    if (type != "qr") {
      const nextErrors = { amount: "", bank: "" };
      const numericAmount = Number(amount);

      if (!amount || Number.isNaN(numericAmount)) {
        nextErrors.amount = t("withdraws.errors.amountRequired");
      } else if (numericAmount <= 0) {
        nextErrors.amount = t("withdraws.errors.amountGtZero");
      } else if (numericAmount > credits) {
        nextErrors.amount = t("withdraws.errors.amountMin", {
          value: credits.toFixed(2),
        });
      }

      if (!selectedBankId) {
        nextErrors.bank = t("withdraws.errors.bankRequired");
      }

      if (nextErrors.amount || nextErrors.bank) {
        setErrors(nextErrors);
        return;
      }

      // 2) call API
      try {
        setLoading(true);

        const payload = {
          amount: numericAmount,
          bankaccount_id: String(selectedBankId),
          member_id: info?.member_id,
        };

        const res = await withdraw(payload).unwrap();

        if (res?.status) {
          addItem({
            id: crypto.randomUUID(),
            text: `${maskPhoneCompact(info?.phone)} ${withdrawPlaceholder} RM ${numericAmount || 0}`,
            time: Date.now(),
          });
          setErrors({ amount: "", bank: "" }); // clear field errors
          handleRefresh();
          //  balanceRef.current?.handleRefresh();
          setAmount(0);
          router.back();
          setTimeout(() => {
            toast.success(t("withdraws.success"));
          }, 1000);
        } else {
          toast.error(res?.message || t("common.failed"));
        }
      } catch (err) {
        const result = extractError(err);

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
        setLoading(false);
      }
    } else {
      const nextErrors = { amount: "", bank: "" };
      const numericAmount = Number(amount);

      if (!amount || Number.isNaN(numericAmount)) {
        nextErrors.amount = t("withdraws.errors.amountRequired");
      } else if (numericAmount <= 0) {
        nextErrors.amount = t("withdraws.errors.amountGtZero");
      } else if (numericAmount > credits) {
        nextErrors.amount = t("withdraws.errors.amountMin", {
          value: credits.toFixed(2),
        });
      }

      if (nextErrors.amount) {
        setErrors(nextErrors);
        return;
      }

      try {
        setLoading(true);

        const res = await getWithdrawQr({
          member_id: info?.member_id,
          amount: amount,
        }).unwrap();

        if (res?.status) {
          sessionStorage.removeItem("withdrawQrData");
          // ✅ store full response (stringified)
          sessionStorage.setItem("withdrawQrData", JSON.stringify(res));

          // ✅ go to QR display page
          router.push("/withdraw/qr");
        } else {
          toast.error(res?.message || t("common.failed"));
        }
      } catch (err) {
        const result = extractError(err);

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
        setLoading(false);
      }
    }
  };
  useEffect(() => {
    setLoading(lazyBankLoading || memberBankLoading);
  }, [lazyBankLoading, memberBankLoading, setLoading]);

  useEffect(() => {
    if (!shouldReloadBanks) return;
    if (!info?.member_id) return; // safety

    // 1. Clear selected bank
    setSelectedBankId(null);

    // 2. Load fresh bank list from API
    loadBanks({ member_id: info?.member_id }).then((res) => {
      const list = Array.isArray(res?.data?.data) ? res.data.data : [];
      if (!list.length) return;

      // sort fastpay first
      const sorted = [...list].sort(
        (a, b) => Number(b.fastpay) - Number(a.fastpay),
      );

      // 🔹 SAVE meta here
      setBankMeta({
        charge: res.charge,
        has_game: res.has_game,
      });

      // auto select first item
      const first = sorted[0];
      if (first) {
        setSelectedBankId(first.bankaccount_id);
        setSelectedBankAccount(first.bank_account);
        setSelectedBankFullName(first.bank_full_name);
        setBankBizName(first.bank.bank_name);
      }
    });

    // 3. Reset reload flag
    clearShouldReloadBanks();
  }, [shouldReloadBanks, info?.member_id]);

  useEffect(() => {
    const member = getMemberInfo();
    setInfo(member);
  }, []);
  useEffect(() => {
    // ❗ Do NOT auto-select while we are reloading fresh data
    if (shouldReloadBanks) return;

    if (!memberCurrentData) return;

    // 🔹 sync meta for charge / has_game
    setBankMeta({
      charge: memberCurrentData?.charge,
      has_game: memberCurrentData?.has_game,
    });

    const list = Array.isArray(memberCurrentData.data)
      ? memberCurrentData.data
      : [];

    if (!list.length) return;

    const sorted = [...list].sort(
      (a, b) => Boolean(b.fastpay) - Boolean(a.fastpay),
    );

    const exists = sorted.some((x) => x.bankaccount_id === selectedBankId);

    if (!exists) {
      const first = sorted[0];
      setSelectedBankId(first.bankaccount_id);
      setSelectedBankAccount(first.bank_account);
      setSelectedBankFullName(first.bank_full_name);
      setBankBizName(first.bank.bank_name);
    }
  }, [
    shouldReloadBanks, // ❗ add this
    memberCurrentData,
    selectedBankId,
    setSelectedBankId,
    setSelectedBankAccount,
    setSelectedBankFullName,
    setBankBizName,
  ]);

  const handleRefresh = async () => {
    if (!info?.member_id || isTransferring) return;

    try {
      setIsTransferring(true);
      setIsGlobalLoading(true);

      await refreshBalancesCore({
        info,
        gameMemberId: null, // ✅ Correct usage
        triggerGetMemberView,
        triggerGetPlayerView,
        setCredits,
        setPoints,
      });
    } catch (err) {
      console.error("Refresh error:", err);
    } finally {
      setIsGlobalLoading(false);
      markTransferDone();
      setIsTransferring(false);
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
          {t("withdraws.title")}
        </h1>
      </div>

      {/* Credit / Points */}
      <BalanceBar ref={balanceRef} />

      {/* My ID */}
      <div className="mt-6">
        <label className="mb-1 block text-sm text-white/70">
          {t("withdraws.myId")}
        </label>
        <input
          disabled
          value={info?.prefix ?? ""}
          className="h-11 w-full rounded-md bg-[#0A1F58] px-3 text-sm text-white outline-none"
        />
      </div>

      {/* Amount */}
      <div className="mt-4">
        <label className="mb-1 block text-sm text-white/70">
          {t("withdraws.amount")}
        </label>
        <div className="flex items-center gap-2">
          <div className="relative flex-1">
            <input
              value={amount}
              onKeyDown={(e) => {
                const allowed = [
                  "Backspace",
                  "Delete",
                  "ArrowLeft",
                  "ArrowRight",
                  "Tab",
                ];

                if (allowed.includes(e.key)) return;

                // Only allow digit keys (mobile safe)
                if (!/^\d$/.test(e.key)) {
                  e.preventDefault();
                }
              }}
              onChange={(e) => {
                // Mobile keyboards sometimes send weird chars like "٬" or "٫"
                const raw = e.target.value.replace(/[^\d]/g, "");

                const cents = Number(raw || 0);

                setAmount((cents / 100).toFixed(2));
              }}
              placeholder="0.00"
              className="h-11 w-full rounded-md bg-[#0A1F58] pl-10 pr-3 text-sm text-white outline-none"
            />
            <Image
              src={IMAGES.withdraw.inputMoney}
              alt="money"
              width={18}
              height={18}
              className="absolute left-3 top-1/2 -translate-y-1/2"
            />
          </div>
          <button
            onClick={() => setAmount(Number(credits || 0).toFixed(2))}
            className="rounded-md bg-[#F8AF07] px-3 py-2 text-sm font-semibold text-black"
          >
            {t("withdraws.all")}
          </button>
        </div>
        {errors.amount && (
          <p className="mt-2 text-xs text-red-400">{t(errors.amount)}</p>
        )}

        {!hasGame && numericAmount > 0 && (
          <div className="mt-2 text-xs text-white/70 space-y-1">
            <div>{t("withdraws.accountCommission", { value: chargeRate })}</div>
            <div className="mt-2 text-xs text-[#FFF] space-y-1">
              {t("withdraws.finalAmount", {
                value: finalAmount.toFixed(2),
              })}
            </div>
          </div>
        )}
      </div>

      <div className="mt-6 flex items-center gap-2 rounded-md border border-[#FFBC00] ">
        <button
          onClick={() => setType("transfer")}
          className={`flex-1   py-3 text-sm font-semibold active:scale-95 ${
            type === "transfer"
              ? "bg-[#F8AF07] text-black"
              : "bg-[#0A1F58]/40 text-[#FFBC00]"
          }`}
        >
          {t("withdraws.submitTransfer")}
        </button>
        <button
          onClick={() => setType("qr")}
          className={`flex-1 py-3 text-sm font-semibold active:scale-95 ${
            type === "qr"
              ? "bg-[#F8AF07] text-black"
              : "bg-[#0A1F58]/40 text-[#FFBC00]"
          }`}
        >
          {t("withdraws.qrPay")}
        </button>
      </div>

      {/* Transfer form */}
      {type === "transfer" && (
        <>
          {/* Bank Picker */}
          <div className="mt-6">
            {/* 🔹 Top row: Left + Right labels */}
            <div className="flex items-center justify-between">
              <label className="mb-1 block text-sm text-white/70">
                {t("withdraws.bank")}
              </label>

              <span
                className="
    mb-1 inline-flex items-center gap-1.5
    text-sm cursor-pointer
    text-[#F8AF07]
  "
                onClick={() => setShowBankModal(true)}
              >
                {t("withdraws.selectBank")}
                <svg
                  className="w-4 h-4"
                  viewBox="0 0 24 24"
                  fill="none"
                  stroke="#F8AF07"
                  strokeWidth="2"
                  strokeLinecap="round"
                  strokeLinejoin="round"
                >
                  <polyline points="9 18 15 12 9 6" />
                </svg>
              </span>
            </div>

            {/* 🔹 Readonly clickable input (replaces UISelect) */}
            <input
              readOnly
              onClick={() => setShowBankModal(true)}
              value={bankBizName}
              className="h-11 w-full rounded-md bg-[#0A1F58] px-3 text-sm text-white 
                   outline-none cursor-pointer active:opacity-80"
            />
          </div>

          {/* Bank account name */}
          <div className="mt-4">
            <label className="mb-1 block text-sm text-white/70">
              {t("withdraws.bankUsername")}
            </label>
            <input
              disabled
              value={selectedBankFullName ?? ""}
              className="h-11 w-full rounded-md bg-[#0A1F58] px-3 text-sm text-white outline-none"
            />
          </div>

          {/* Bank account number */}
          <div className="mt-4">
            <label className="mb-1 block text-sm text-white/70">
              {t("withdraws.bankAccount")}
            </label>
            <input
              disabled
              value={selectedBankAccount ?? ""}
              className="h-11 w-full rounded-md bg-[#0A1F58] px-3 text-sm text-white outline-none"
            />
          </div>

          {/* Modal */}
          {showBankModal && (
            <SelectBankModal
              memberId={info?.member_id}
              selectedBankId={selectedBankId}
              onConfirm={(bank) => {
                setSelectedBankId(bank.bankaccount_id);
                setSelectedBankAccount(bank.bank_account);
                setSelectedBankFullName(bank.bank_full_name);
                setBankBizName(bank.bank.bank_name);

                setShowBankModal(false);
              }}
              onAddNewBank={() => router.push("/bank/create")}
              onClose={() => setShowBankModal(false)}
            />
          )}
        </>
      )}

      {/* QR form */}
      {type === "qr" && (
        <div className="mt-6">
          {/* <p className="text-sm text-white/70">
            {t("withdraws.qrPay")} – Coming soon...
          </p> */}
        </div>
      )}

      {/* Submit button */}
      <div className="mt-8">
        <SubmitButton onClick={handleSubmit}>
          {t("withdraws.submit")}
        </SubmitButton>
      </div>
    </div>
  );
}
