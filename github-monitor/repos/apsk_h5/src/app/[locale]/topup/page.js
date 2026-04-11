"use client";

import Image from "next/image";
import { useState, useMemo, useContext, useEffect, useRef } from "react"; // ✨ ADDED useRef
import { useRouter } from "next/navigation";
import { useTranslations } from "next-intl";
import { IMAGES } from "@/constants/images";
import SubmitButton from "@/components/shared/SubmitButton";
import { UIContext } from "@/contexts/UIProvider";
import {
  usePaymentListQuery,
  useTopupMutation,
  useTopupStatusQuery,
} from "@/services/transactionApi";
import { extractError, getMemberInfo } from "@/utils/utility";
// ✨ ADDED
import { toast } from "react-hot-toast";
import { useMemberBankQuery } from "@/services/bankApi";
import { useGetMemberViewQuery } from "@/services/authApi";
import SharedLoading from "@/components/shared/LoadingBar/ShareLoading";
import {
  useClaimFirstBonusMutation,
  useClaimWeeklyBonusMutation,
} from "@/services/vipApi";
import EmptyRecord from "@/components/shared/EmptyRecord";

export default function TopUpPage() {
  const t = useTranslations();
  const router = useRouter();
  const { setLoading, setSuccessSheet } = useContext(UIContext);
  const [fromPayment, setFromPayment] = useState(false);
  const FALLBACK_PRESETS = [50, 100, 250, 500, 1000];
  const GLOBAL_MIN = 1;
  const GLOBAL_MAX = 20000;

  const [activeTab, setActiveTab] = useState("online"); // online | crypto | ewallet | qr
  const [selectedPaymentId, setSelectedPaymentId] = useState(null);
  const [amount, setAmount] = useState("");

  // for polling
  const [pollArgs, setPollArgs] = useState(null);

  // user id for API
  const info = getMemberInfo();
  const userId = info?.member_id ? String(info.member_id) : null;

  // member API
  const { refetch: refetchMember } = useGetMemberViewQuery(
    info ? { member_id: info.member_id } : undefined,
    {
      skip: !info?.member_id,
    },
  );

  const handleBack = () => {
    if (fromPayment) {
      router.replace("/"); // 👈 or wherever you want
    } else {
      router.back();
    }
  };

  // fetch payment list
  const {
    data: paymentResp,
    isLoading,
    isError,
    isFetching,
    refetch,
  } = usePaymentListQuery(
    { type: activeTab, user_id: userId },
    { skip: !activeTab || !userId },
  );

  // normalize
  const payments = useMemo(
    () => (Array.isArray(paymentResp?.data) ? paymentResp.data : []),
    [paymentResp],
  );

  // auto-select FIRST payment ONLY if nothing is selected
  useEffect(() => {
    if (payments.length > 0 && selectedPaymentId === null) {
      setSelectedPaymentId(payments[0].payment_id);
    }
  }, [payments, selectedPaymentId]);

  const selectedPayment = useMemo(
    () => payments.find((p) => p.payment_id === selectedPaymentId) || null,
    [payments, selectedPaymentId],
  );

  const apiMin = Number(selectedPayment?.min_amount) || GLOBAL_MIN;
  const apiMax = Number(selectedPayment?.max_amount) || GLOBAL_MAX;

  const args = userId ? { member_id: String(userId) } : null;
  const { data } = useMemberBankQuery(args, {
    skip: !args,
    refetchOnMountOrArgChange: true,
    refetchOnFocus: true,
  });

  // allowed amounts from amount_type; else fallback presets
  const allowedAmounts = useMemo(() => {
    const s = selectedPayment?.amount_type?.trim();
    if (!s) return FALLBACK_PRESETS;
    return s
      .split(",")
      .map((v) => Number(String(v).trim()))
      .filter((n) => Number.isFinite(n) && n > 0);
  }, [selectedPayment]);

  // dynamic min/max
  const { effectiveMin, effectiveMax } = useMemo(() => {
    // Priority 1: API min/max
    if (apiMin && apiMax) {
      return {
        effectiveMin: apiMin,
        effectiveMax: apiMax,
      };
    }

    // Priority 2: amount_type fixed list
    if (selectedPayment?.amount_type && allowedAmounts.length > 0) {
      return {
        effectiveMin: Math.min(...allowedAmounts),
        effectiveMax: Math.max(...allowedAmounts),
      };
    }

    // Fallback
    return { effectiveMin: GLOBAL_MIN, effectiveMax: GLOBAL_MAX };
  }, [apiMin, apiMax, selectedPayment, allowedAmounts]);

  const parsed = Number(amount || 0);

  // const withinRange =
  //   parsed > 0 && parsed >= effectiveMin && parsed <= effectiveMax;

  const withinRange = parsed >= effectiveMin && parsed <= effectiveMax;

  const hasPayment = !!selectedPaymentId;
  const valid = !!selectedPaymentId && withinRange;

  // --- Topup API ---
  const [topup, { isLoading: topupLoading }] = useTopupMutation();

  // --- Status polling ---
  // const { data: statusData } = useTopupStatusQuery(pollArgs, {
  //   skip: !pollArgs,
  //   pollingInterval: pollArgs ? 2000 : 0, // every 2s
  // });

  // ✨ ADDED: allow an immediate "one-shot" refetch of status when cancelling
  // const { refetch: refetchStatusNow } = useTopupStatusQuery(pollArgs, {
  //   skip: !pollArgs,
  // });
  const [creditId, setCreditId] = useState(null);
  const { refetch: refetchStatusNow } = useTopupStatusQuery(
    creditId && userId
      ? { credit_id: creditId, user_id: String(userId) }
      : undefined,
    {
      skip: !creditId || !userId, // ✅ only load when both exist
    },
  );

  // watch status response
  // useEffect(() => {
  //   if (!statusData) return;
  //   const creditStatus = statusData?.credit?.status;
  //   const orderStatus = statusData?.response?.order_status;

  //   if (creditStatus === 1 || orderStatus === "completed") {
  //     setPollArgs(null); // stop polling
  //     setLoading(false); // stop loading bar
  //     setSuccessSheet({
  //       visible: true,
  //       titleKey: "topups.success.title",
  //       descKey: "topups.success.desc",
  //       amount: parsed,
  //       imageSrc: IMAGES.topup.moneySuccess,
  //       onClose: async () => {
  //         await refetchMember();
  //         // e.g., refetch user balance, then back
  //         // dispatch(fetchBalance());
  //         router.back();
  //       },
  //     });
  //   }
  // }, [statusData, setLoading, setSuccessSheet, parsed, router]);

  // useEffect(() => {
  //   if (typeof window === "undefined") return;

  //   const params = new URLSearchParams(window.location.search);
  //   const txid = params.get("id");
  //   const method = params.get("method");

  //   if (txid && method) {
  //     setActiveTab(method);
  //     setFromPayment(true); // ✅ mark it came from payment
  //     setCreditId(txid); // 1️⃣ store creditId first (this re-renders)
  //   }
  // }, []);

  // // 2️⃣ when creditId + userId are ready, THEN run refetch
  // useEffect(() => {
  //   if (!creditId || !userId) return; // wait until both exist

  //   (async () => {
  //     try {
  //       setLoading(true);
  //       const res = await refetchStatusNow({
  //         credit_id: creditId,
  //         user_id: String(userId),
  //       }).unwrap();

  //       const creditStatus = res?.credit?.status;
  //       const orderStatus = res?.response?.order_status;
  //       const amount = res?.response?.credit?.amount;

  //       if (creditStatus === 1 || orderStatus === "completed") {
  //         setSuccessSheet({
  //           visible: true,
  //           titleKey: "topups.success.title",
  //           descKey: "topups.success.desc",
  //           amount: amount || 0,
  //           imageSrc: IMAGES.topup.moneySuccess,
  //           onClose: async () => {
  //             await refetchMember();
  //             router.replace("/");
  //           },
  //         });
  //       } else if (creditStatus === 0) {
  //         toast.info(
  //           t("topups.status.processing") ||
  //             "Payment is pending or being processed"
  //         );
  //       } else {
  //         toast.error(t("topups.status.failed") || "Payment failed");
  //       }
  //     } catch (err) {
  //       toast.error(t("topups.status.error") || "Unable to verify payment");
  //     } finally {
  //       setLoading(false);
  //     }
  //   })();
  // }, [creditId, userId]); // 3️⃣ depend on creditId + userId

  // --- Submit ---
  // const handleSubmit = async () => {
  //   if (!valid) return;
  //   const banks = data?.banks ?? data?.data ?? [];
  //   if (!Array.isArray(banks) || banks.length === 0) {
  //     toast.error(t("wallets.addBankRequired"));
  //     router.push("/bank");
  //     return;
  //   }
  //   const win = window.open("", "_blank");
  //   if (win && win.document) {
  //     const doc = win.document;
  //     doc.title = t("wallets.redirectingTitle") || "正在跳转...";

  //     const body = doc.createElement("body");
  //     body.style.cssText =
  //       "font-family:sans-serif; text-align:center; padding-top:60px; background:#fafafa;";

  //     const h2 = doc.createElement("h2");
  //     h2.textContent = t("wallets.redirecting") || "正在准备您的支付页面...";

  //     const p = doc.createElement("p");
  //     p.textContent = t("wallets.pleaseWait") || "请稍候片刻。";

  //     body.appendChild(h2);
  //     body.appendChild(p);
  //     doc.body.replaceWith(body);
  //   }

  //   try {
  //     setLoading(true);
  //     const res = await topup({
  //       amount: parsed,
  //       member_id: String(userId),
  //       payment_id: String(selectedPaymentId),
  //     }).unwrap();
  //     if (res?.url && win) win.location.href = res.url;
  //     else win?.close();
  //     const creditId = String(res?.credit?.credit_id || "");
  //     if (creditId && userId)
  //       setPollArgs({ credit_id: creditId, user_id: String(userId) });
  //     else setLoading(false);
  //   } catch (err) {
  //     const result = extractError(err);
  //     toast.error(result.message);
  //     setLoading(false);
  //     win?.close();
  //   } finally {
  //     setLoading(false);
  //   }
  // };

  const handleSubmit = async () => {
    if (!valid) return;

    const banks = data?.banks ?? data?.data ?? [];
    if (!Array.isArray(banks) || banks.length === 0) {
      toast.error(t("wallets.addBankRequired"));
      router.push("/bank");
      return;
    }

    try {
      setLoading(true);
      // sessionStorage.setItem(
      //   "paymentRedirectUrl",
      //   "/topup?method=online&&id=1000851"
      // );
      // setTimeout(() => {
      //   setLoading(false);
      //   router.push("/topup/redirect");
      // }, 2000);

      // 🟢 Call API
      const res = await topup({
        amount: parsed,
        member_id: userId,
        payment_id: selectedPaymentId,
        device: "web",
      }).unwrap();

      // 🟢 If got URL, store it for redirect page
      if (res?.url) {
        sessionStorage.setItem("paymentRedirectUrl", res.url);
        router.replace("/topup/redirect");
      } else {
        toast.error(t("common.error") || "Something went wrong");
      }
    } catch (err) {
      const result = extractError(err);

      toast.error(result.message || "Topup failed");
    } finally {
      setLoading(false);
    }
  };

  // --- Submit ---
  // const handleSubmit = async () => {
  //   // Validate form
  //   if (!valid) return;

  //   const banks = data?.banks ?? data?.data ?? [];
  //   if (!Array.isArray(banks) || banks.length === 0) {
  //     toast.error(t("wallets.addBankRequired"));
  //     router.push("/bank");
  //     return;
  //   }

  //   // 🟡 Open a new tab immediately (user-initiated)
  //   const win = window.open("", "_blank");

  //   // 🟡 Write a placeholder message instead of blank
  //   if (win && win.document) {
  //     const doc = win.document;
  //     doc.title = t("wallets.redirectingTitle") || "正在跳转...";

  //     const body = doc.createElement("body");
  //     body.style.cssText =
  //       "font-family:sans-serif; text-align:center; padding-top:60px; background:#fafafa;";

  //     const h2 = doc.createElement("h2");
  //     h2.textContent = t("wallets.redirecting") || "正在准备您的支付页面...";

  //     const p = doc.createElement("p");
  //     p.textContent = t("wallets.pleaseWait") || "请稍候片刻。";

  //     body.appendChild(h2);
  //     body.appendChild(p);
  //     doc.body.replaceWith(body);
  //   }

  //   try {
  //     setLoading(true);

  //     const res = await topup({
  //       amount: parsed,
  //       member_id: String(userId),
  //       payment_id: String(selectedPaymentId),
  //     }).unwrap();

  //     // 🟢 Replace tab content with the real URL once ready
  //     if (res?.url && win) {
  //       win.location.href = res.url;
  //     } else {
  //       win?.close();
  //     }

  //     // Optional polling setup
  //     const creditId = String(res?.credit?.credit_id || "");
  //     if (creditId && userId) {
  //       setPollArgs({ credit_id: creditId, user_id: String(userId) });
  //     } else {
  //       setLoading(false);
  //     }
  //   } catch (err) {
  //     const result = extractError(err);

  //     toast.error(result.message);

  //     setLoading(false);
  //     win?.close();
  //   }
  // };

  // ================================
  // ✨ ADDED: Tap-anywhere-to-cancel
  // ================================
  const cancelToastIdRef = useRef(null);
  const isShowingCancelToast = () => !!cancelToastIdRef.current;

  const showCancelToast = () => {
    if (isShowingCancelToast()) return;

    cancelToastIdRef.current = toast.custom(
      (tt) => (
        <div className="pointer-events-auto w-[92%] max-w-[420px] rounded-xl bg-[#0B1D48] text-white shadow-lg border border-white/10 p-4">
          <div className="text-sm font-semibold mb-1">
            {t("topups.cancel.title")}
          </div>
          <div className="text-[13px] text-white/80 mb-3">
            {t("topups.cancel.desc")}
          </div>
          <div className="flex items-center justify-end gap-2">
            <button
              onClick={() => {
                toast.dismiss(tt.id);
                cancelToastIdRef.current = null;
                // Keep waiting — do nothing
              }}
              className="px-3 py-1.5 rounded-md border border-[#FFC000] text-[#FFC000] text-sm active:scale-95"
            >
              {t("topups.keepWaiting")}
            </button>
            <button
              onClick={async () => {
                toast.dismiss(tt.id);
                cancelToastIdRef.current = null;

                try {
                  if (pollArgs) {
                    const res = await refetchStatusNow().unwrap();
                    const creditStatus = res?.credit?.status;
                    const orderStatus = res?.response?.order_status;

                    if (creditStatus === 1 || orderStatus === "completed") {
                      // already completed — success sheet will open via effect
                      setPollArgs(null);
                      setLoading(false);
                      toast.success(t("topups.status.completedNow"));
                    } else {
                      // not completed — stop waiting and show current orderStatus
                      setPollArgs(null);
                      setLoading(false);
                      toast.error(
                        t("topups.status.current", {
                          status: String(
                            orderStatus ?? creditStatus ?? "unknown",
                          ),
                        }),
                      );
                    }
                  } else {
                    // still initializing (no credit_id yet)
                    setLoading(false);
                    toast.error(t("topups.status.initializing"));
                  }
                } catch (e) {
                  setPollArgs(null);
                  setLoading(false);
                  toast.error(t("common.error"));
                }
              }}
              className="px-3 py-1.5 rounded-md bg-[#FFC000] text-black text-sm font-semibold active:scale-95"
            >
              {t("common.cancel")}
            </button>
          </div>
        </div>
      ),
      { duration: 4000 }, // keep visible long enough to act
    );
  };

  const isWaiting = topupLoading || !!pollArgs;

  // Global tap handler: if waiting, prompt cancel
  const handleGlobalTap = () => {
    if (!isWaiting) return;
    showCancelToast();
  };
  // ================================

  return (
    // ✨ ADDED onClick={handleGlobalTap}
    <div
      className="mx-auto max-w-[480px] min-h-dvh bg-[#00143D] text-white px-4 pb-8"
      onClick={handleGlobalTap}
    >
      {/* Header */}
      <div className="relative flex items-center h-14">
        <button onClick={handleBack} className="z-10 cursor-pointer">
          <Image
            src={IMAGES.arrowLeft}
            alt="back"
            width={22}
            height={22}
            className="object-contain"
          />
        </button>
        <h1 className="absolute inset-x-0 text-lg font-semibold text-center">
          {t("topups.title")}
        </h1>
      </div>

      {/* Tabs */}
      <div className="mt-2 flex items-center gap-6 text-base">
        {[
          { k: "online", label: t("topups.tabs.online") },
          // { k: "crypto", label: t("topups.tabs.crypto") },
          // { k: "ewallet", label: t("topups.tabs.ewallet") },
          // { k: "qr", label: t("topups.tabs.qr") },
        ].map((tab) => (
          <button
            key={tab.k}
            onClick={() => setActiveTab(tab.k)}
            className={`pb-2 ${
              activeTab === tab.k
                ? "text-[#FFC000] border-b-2 border-[#FFC000]"
                : "text-white/70"
            }`}
          >
            {tab.label}
          </button>
        ))}
      </div>

      <div className="mt-6">
        {/* Loading / empty states */}
        {isLoading && <SharedLoading />}

        {/* 🚫 No Data */}
        {!isLoading && !isFetching && payments.length === 0 && (
          <EmptyRecord></EmptyRecord>
        )}

        <div className="grid grid-cols-3 gap-3">
          {payments.map((p) => {
            const active = selectedPaymentId === p.payment_id;

            return (
              <button
                key={p.payment_id}
                onClick={() => {
                  setSelectedPaymentId(p.payment_id);
                  setAmount("");
                }}
                className={`h-12 rounded-full border text-base flex items-center justify-center gap-2 ${
                  active
                    ? "bg-[#FFC000] border-[#FFC000] text-black"
                    : "border-white/60 text-white"
                }`}
              >
                {p.icon && (
                  <img
                    src={`${process.env.NEXT_PUBLIC_IMAGE_URL}/${p.icon}`}
                    alt={p.payment_name}
                    className="w-20 h-10 object-contain"
                  />
                )}

                {/* {p.payment_name} */}
              </button>
            );
          })}
        </div>
      </div>

      {/* Amount chips */}
      {selectedPayment && (
        <div className="mt-8">
          <div className="text-sm font-semibold">
            {t("topups.amount.title")}
          </div>
          <div className="mt-4 grid grid-cols-3 gap-4">
            {allowedAmounts.map((v) => {
              const isSelected = Number(amount) === v;
              return (
                <button
                  key={v}
                  onClick={() => setAmount(String(v))}
                  className={`h-12 rounded-full text-base font-medium border ${
                    isSelected
                      ? "bg-[#FFC000] text-black border-[#FFC000]"
                      : "border-[#FFC000] text-[#FFC000]"
                  }`}
                >
                  {v}
                </button>
              );
            })}
          </div>
        </div>
      )}

      {/* Custom amount input */}
      {selectedPayment && (
        <div className="mt-6">
          <div className="text-sm text-white/70 mb-2">
            {t("topups.amount.other")}
          </div>

          <div className="flex items-center gap-3 rounded-xl border border-white/60 px-4 py-3">
            <div className="grid place-items-center h-8 w-8 rounded-full bg-[#0B1D48]">
              <span className="text-[#FFC000] font-semibold">$</span>
            </div>
            <input
              inputMode="decimal"
              value={amount}
              onKeyDown={(e) => {
                // allow control keys
                if (
                  [
                    "Backspace",
                    "Delete",
                    "ArrowLeft",
                    "ArrowRight",
                    "Tab",
                  ].includes(e.key)
                )
                  return;

                // block non-digit
                if (!/^[0-9]$/.test(e.key)) {
                  e.preventDefault();
                }
              }}
              onChange={(e) => {
                // keep only digits
                let raw = e.target.value.replace(/\D/g, "");

                // convert to currency
                const cents = parseInt(raw || "0", 10);
                setAmount((cents / 100).toFixed(2));
              }}
              placeholder={t("topups.amount.placeholder")}
              className="w-full bg-transparent text-base outline-none placeholder:text-white/40"
            />
          </div>

          {/* limits hint */}
          <div className="mt-2 text-sm text-white/50">
            {t("topups.amount.limitsRange", {
              min: effectiveMin.toLocaleString(),
              max: effectiveMax.toLocaleString(),
            })}
          </div>
          {/* validation */}
          {amount && !withinRange && (
            <div className="mt-1 text-sm text-red-400">
              {parsed > effectiveMax
                ? t("topups.amount.overMaxSplit")
                : t("topups.amount.outOfRange")}
            </div>
          )}
        </div>
      )}

      {/* Submit */}
      <div className="mt-10">
        <SubmitButton
          onClick={handleSubmit}
          className={`${
            valid ? "" : "opacity-60 pointer-events-none"
          } rounded-2xl`}
        >
          {topupLoading
            ? t("topups.actions.submit") + "..."
            : t("topups.actions.submit")}
        </SubmitButton>
      </div>
    </div>
  );
}
