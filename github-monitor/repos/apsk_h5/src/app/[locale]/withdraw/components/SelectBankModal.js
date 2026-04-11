"use client";

import { useMemo, useState } from "react";
import { useTranslations } from "next-intl";
import { useMemberBankQuery } from "@/services/bankApi";

export default function SelectBankModal({
  memberId,
  selectedBankId,
  onConfirm, // return final selected bank
  onAddNewBank,
  onClose,
}) {
  const t = useTranslations();

  // temporary selection inside modal (only apply after tick ✓)
  const [tempSelectedId, setTempSelectedId] = useState(selectedBankId);

  const bankQueryArg = useMemo(() => {
    if (!memberId) return skipToken;
    return { member_id: memberId };
  }, [memberId]);

  const {
    data: bankData,
    isLoading,
    isError,
    refetch,
  } = useMemberBankQuery(bankQueryArg, {
    refetchOnMountOrArgChange: true,
    refetchOnFocus: true,
    keepUnusedDataFor: 0,
  });

  const list = bankData?.data || [];

  return (
    <div
      className="fixed inset-0 z-[100] bg-black/60 backdrop-blur-[1px]
                 flex items-center justify-center"
      onClick={onClose}
    >
      <div
        className="mx-2 w-full max-w-[480px] rounded-2xl bg-[#0B1D48] text-white 
                   shadow-2xl flex flex-col h-[80dvh]"
        onClick={(e) => e.stopPropagation()}
      >
        {/* HEADER */}
        <div className="flex items-center justify-between px-4 h-14 border-b border-white/10">
          {/* Close X */}
          <button
            onClick={onClose}
            className="text-white/80 text-xl active:scale-90"
          >
            ×
          </button>

          {/* Title */}
          <span className="text-sm">{t("withdraws.selectBank")}</span>

          {/* Confirm ✓ */}
          <button
            onClick={() => {
              const bank = list.find(
                (b) => b.bankaccount_id === tempSelectedId
              );

              if (bank) onConfirm(bank); // return bank data
              onClose(); // CLOSE modal
            }}
            className="text-[#F8AF07] text-xl active:scale-90"
          >
            ✓
          </button>
        </div>

        {/* LIST */}
        <div className="flex-1 overflow-y-auto divide-y divide-white/10">
          {isLoading && (
            <div className="text-center py-10 text-white/60 text-sm">
              {t("common.loading")}
            </div>
          )}

          {isError && (
            <div className="text-center py-10 text-red-400">
              {t("common.error")}
            </div>
          )}

          {!isLoading &&
            !isError &&
            list.map((bank) => {
              const active = tempSelectedId === bank.bankaccount_id;
              const last4 = bank.bank_account?.slice(-4) ?? "";

              return (
                <div
                  key={bank.bankaccount_id}
                  onClick={() => setTempSelectedId(bank.bankaccount_id)}
                  className="px-4 py-4 cursor-pointer active:opacity-80"
                >
                  <div className="flex items-center justify-between">
                    <div>
                      <div className="text-sm font-semibold">
                        {bank.bank?.bank_name}
                      </div>
                      <div className="text-sm text-white/70 tracking-widest">
                        •••••••• {last4}
                      </div>
                    </div>

                    {/* Radio */}
                    <div
                      className={`h-5 w-5 rounded-full border-2 flex items-center justify-center ${
                        active ? "border-[#F8AF07]" : "border-white/40"
                      }`}
                    >
                      {active && (
                        <div className="h-3 w-3 rounded-full bg-[#F8AF07]" />
                      )}
                    </div>
                  </div>
                </div>
              );
            })}
        </div>

        {/* Add new bank */}
        {list.length < 5 && (
          <div className="p-4 border-t border-white/10">
            <button
              onClick={onAddNewBank}
              className="w-full h-12 rounded-full border border-[#F8AF07]
                 text-[#F8AF07] active:scale-95"
            >
              + {t("withdraws.addBank")}
            </button>
          </div>
        )}
      </div>
    </div>
  );
}
