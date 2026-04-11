"use client";

import Image from "next/image";
import { useRouter } from "next/navigation";
import { useTranslations } from "next-intl";
import { use, useContext, useEffect, useMemo, useState } from "react"; // 👈 add use()
import { useSelector, useDispatch } from "react-redux";
import toast from "react-hot-toast";

import { IMAGES } from "@/constants/images";
import FastToggle from "@/components/shared/toogle";
import { clearCurrentBank } from "@/store/slice/bankDetailSlice";
import {
  useMemberBankQuery,
  useUpdateBankFastpayMutation,
  useDeleteBankMutation,
} from "@/services/bankApi";
import { extractError, getMemberInfo } from "@/utils/utility";
import { UIContext } from "@/contexts/UIProvider";

export default function BankDetailPage({ params: paramsPromise }) {
  const t = useTranslations();
  const router = useRouter();
  const dispatch = useDispatch();
  const { setLoading } = useContext(UIContext);

  // ✅ unwrap params Promise (required in Next 15+)
  const { id: routeId } = use(paramsPromise);

  const selected = useSelector((s) => s.bankDetail.current);

  const [memberId, setMemberId] = useState(null);
  useEffect(() => {
    const info = getMemberInfo?.();
    if (info?.member_id) setMemberId(String(info.member_id));
  }, []);

  const { data, isLoading, isError, refetch } = useMemberBankQuery(
    { member_id: memberId },
    { skip: !memberId }
  );

  const record = useMemo(() => {
    if (selected && String(selected.bankaccount_id) === String(routeId)) {
      return selected;
    }
    const arr = Array.isArray(data?.data) ? data.data : [];
    return (
      arr.find((x) => String(x.bankaccount_id) === String(routeId)) || null
    );
  }, [selected, data, routeId]);

  const [fast, setFast] = useState(false);
  useEffect(() => {
    if (record) setFast(record.fastpay === 1);
  }, [record]);

  const [updateFastpay, { isLoading: isSaving }] =
    useUpdateBankFastpayMutation();
  const [deleteBank, { isLoading: isDeleting }] = useDeleteBankMutation();

  const onToggleFast = async (val) => {
    if (!record || !memberId) return;
    setFast(val);
    try {
      await updateFastpay({
        bankaccount_id: record.bankaccount_id,
        status: val ? 1 : 0,
        member_id: memberId,
      }).unwrap();
      toast.success(t("bankAdd.toasts.saveSuccess"));
      await refetch();
    } catch (e) {
      setFast(!val);
      toast.error(
        e?.data?.message
          ? String(e.data.message)
          : t("bankAdd.toasts.saveFailed")
      );
    }
  };

  const onDelete = async () => {
    if (!record || !memberId) return;
    if (!confirm(t("bankEdit.deleteConfirm.message"))) return;

    try {
      // 🔥 Show global loader
      setLoading(true);

      // 🔥 Call API (no toast.promise)
      const resp = await deleteBank({
        bankaccount_id: record.bankaccount_id,
        member_id: memberId,
      }).unwrap();

      if (!resp?.status) {
        toast.error(resp?.message || t("bankAdd.toasts.saveFailed"));
        return;
      }

      // 🎉 Success
      toast.success(t("bankEdit.toast.deleted"));

      // 🔄 Refresh list
      await refetch();

      // 🧹 Clear store
      dispatch(clearCurrentBank());

      // 🔙 Back to bank list
      router.back();
    } catch (e) {
      // ❌ Use your extractError()
      const result = extractError(e);
      if (result.type === "validation") {
        Object.values(result.fieldErrors).forEach((msg) => {
          toast.error(msg);
        });
      } else {
        toast.error(result.message);
      }
    } finally {
      // 📴 Turn off global loader
      setLoading(false);
    }
  };

  if (!record && (isLoading || !memberId)) {
    return (
      <div className="mx-auto max-w-[480px] min-h-dvh bg-[#00143D] text-white px-4 pb-8">
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
            {t("bankEdit.header")}
          </h1>
        </div>
        <div className="mt-6 animate-pulse space-y-3">
          <div className="h-10 rounded bg-white/10" />
          <div className="h-10 rounded bg-white/10" />
          <div className="h-10 rounded bg-white/10" />
        </div>
      </div>
    );
  }

  if (!record && isError) {
    return (
      <div className="mx-auto max-w-[480px] min-h-dvh bg-[#00143D] text-white px-4 pb-8">
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
            {t("bankEdit.header")}
          </h1>
        </div>
        <p className="mt-4 text-sm text-red-300">{t("common.loadFailed")}</p>
      </div>
    );
  }

  const bankName =
    record?.bank?.bank_name ??
    record?.bank_name ??
    t("bankAdd.bank.placeholder");

  return (
    <div className="mx-auto max-w-[480px] min-h-dvh bg-[#00143D] text-white px-4 pb-8">
      {/* {(isSaving || isDeleting) && (
        <div className="fixed left-0 right-0 top-0 h-1 bg-gradient-to-r from-yellow-300 to-yellow-200 animate-pulse" />
      )} */}

      {/* header */}
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
          {t("bankEdit.header")}
        </h1>
      </div>

      {/* title */}
      <h2 className="mt-3 text-2xl font-bold">{t("bankEdit.title")}</h2>

      {/* read-only fields */}
      <div className="mt-6">
        <label className="block text-sm mb-2">
          {t("bankAdd.username.label")}
        </label>
        <input
          value={record?.bank_full_name || ""}
          readOnly
          className="w-full rounded-lg border border-[#2D3E7A] bg-[#0A1F58] px-4 py-3 text-white outline-none opacity-80"
        />
        <p className="mt-2 text-xs text-white/60">
          {t("bankAdd.username.tip")}
        </p>
      </div>

      <div className="mt-6">
        <label className="block text-sm mb-2">{t("bankAdd.bank.label")}</label>
        <div className="relative rounded-lg border border-[#2D3E7A] bg-[#0A1F58] px-4 py-3">
          <span>{bankName}</span>
        </div>
      </div>

      <div className="mt-6">
        <label className="block text-sm mb-2">
          {t("bankAdd.account.label")}
        </label>
        <input
          value={record?.bank_account || ""}
          readOnly
          inputMode="numeric"
          className="w-full rounded-lg border border-[#2D3EA7] bg-[#0A1F58] px-4 py-3 text-white outline-none opacity-80"
        />
      </div>

      {/* only interactive: FastPay + Delete */}
      <div className="mt-6">
        <div className="flex items-center justify-between">
          <span className="text-sm">{t("bankAdd.fastPay.label")}</span>
          <FastToggle value={fast} onChange={onToggleFast} />
        </div>
        <p className="mt-3 text-xs text-white/60">
          {t("bankAdd.fastPay.note")}
        </p>
      </div>

      <div className="mt-8">
        <button
          onClick={onDelete}
          className="w-full rounded-full py-3 font-semibold active:scale-95 bg-[#D7263D] text-white"
        >
          {t("bankEdit.actions.delete")}
        </button>
      </div>
    </div>
  );
}
