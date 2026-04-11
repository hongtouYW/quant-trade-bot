"use client";

import { useState, useEffect, useContext } from "react";
import Image from "next/image";
import { useTranslations } from "next-intl";
import { IMAGES } from "@/constants/images";
import SubmitButton from "@/components/shared/SubmitButton";
import { UIContext } from "@/contexts/UIProvider";
import { useBalanceStore } from "@/store/zustand/balanceStore";
import {
  calcCredit,
  calcPoints,
  handleATMInput,
  getMemberInfo,
  extractError,
  getClientIp,
} from "@/utils/utility";
import {
  useFromCreditToPointMutation,
  useFromPointToCreditMutation,
} from "@/services/transactionApi";
import {
  useGetMemberViewQuery,
  useGetPlayerViewQuery,
} from "@/services/authApi";
import { skipToken } from "@reduxjs/toolkit/query";
import { toast } from "react-hot-toast";
import { useRouter } from "next/navigation";
import { useOpenGameUrlMutation } from "@/services/gameApi";

export default function WalletTransferModal({
  open,
  onClose,
  gmemberId,
  providerName,
  providerLoginId,
  providerCategory,
  gameId,
  url, // ✅ redirect url
}) {
  const t = useTranslations();
  const { setLoading, showMessage, showConfirm } = useContext(UIContext);

  const [info, setInfo] = useState(null);
  const [isCreditToPoints, setIsCreditToPoints] = useState(true);
  const [fromAmount, setFromAmount] = useState("0.00");
  const [toAmount, setToAmount] = useState("0.00");
  const [mounted, setMounted] = useState(false);
  const router = useRouter();
  // ---------- QUERIES ----------

  const creditsBalance = useBalanceStore((s) => s.credits);
  const pointsBalance = useBalanceStore((s) => s.points);
  const setCredits = useBalanceStore((s) => s.setCredits);
  const setPoints = useBalanceStore((s) => s.setPoints);

  const [fromCreditToPoint, { isLoading }] = useFromCreditToPointMutation();
  const [fromPointToCredit] = useFromPointToCreditMutation();
  const [openGameUrl] = useOpenGameUrlMutation();

  //-- call query --
  const {
    data: user,
    isLoading: isUserLoading,
    isFetching,
    refetch: refetchMember,
  } = useGetMemberViewQuery(info ? { member_id: info.member_id } : undefined, {
    skip: !info?.member_id,
  });

  const {
    data: playerData,
    isLoading: playerLoading,
    refetch: refetchPlayer,
  } = useGetPlayerViewQuery(
    gmemberId && info?.member_id
      ? { gamemember_id: gmemberId, member_id: info.member_id }
      : skipToken,
    {
      skip: !gmemberId || !info?.member_id,
      refetchOnMountOrArgChange: true, // only refetch if params change
      refetchOnFocus: true, // auto refresh on tab focus
      refetchOnReconnect: true, // auto refresh on reconnect
      keepUnusedDataFor: 0,
    }
  );

  useEffect(() => {
    if (open) {
      setInfo(getMemberInfo());
      setIsCreditToPoints(true);
      // ✅ Reset amounts every time modal opens
      setFromAmount("0.00");
      setToAmount("0.00");
    }
  }, [open]);

  // push credits into store
  useEffect(() => {
    if (user?.data) {
      setCredits(Number(user.data.balance || 0));
    }
  }, [user, setCredits]);

  useEffect(() => {
    if (playerData?.data && gmemberId) {
      setPoints(Number(playerData.data.balance || 0));
    }
  }, [playerData, setPoints, gmemberId]);

  const handleConfirm = async (e, openUrl = true) => {
    e.preventDefault();
    if (!info?.member_id || !gmemberId) return;

    const amount = parseFloat(fromAmount) || 0;

    try {
      setLoading(true);

      // 🧩 Step 1 — Validation
      if (amount > 0) {
        if (isCreditToPoints && amount > creditsBalance) {
          toast.error(t("wallets.insufficientCredit"));
          return;
        }
        if (!isCreditToPoints && amount > pointsBalance) {
          toast.error(t("wallets.insufficientPoints"));
          return;
        }

        const payload = {
          amount,
          gamemember_id: gmemberId,
          ip: await getClientIp(),
          member_id: info.member_id,
        };

        // 🧩 Step 2 — Run transfer API
        if (isCreditToPoints) {
          await fromCreditToPoint(payload).unwrap();
        } else {
          await fromPointToCredit(payload).unwrap();
        }

        // 🧩 Step 3 — Reset + refresh balance
        setFromAmount("0.00");
        setToAmount("0.00");
        await refetchMember();
        await refetchPlayer();
      }

      // 🧩 Step 4 — Handle URL (only if openUrl = true)
      if (openUrl) {
        let gameUrl = "";

        if (providerCategory !== "app") {
          const res = await openGameUrl({
            member_id: info.member_id,
            gamemember_id: gmemberId,
            game_id: gameId,
          }).unwrap();

          gameUrl = res?.data ?? res?.Data;

          if (!gameUrl) {
            toast.error(t("wallets.cannotLoadGame"));
            return;
          }
        } else {
          gameUrl = url;
        }

        sessionStorage.setItem("redirectUrl", gameUrl);
        router.push("/redirect");
      } else {
        // 🧩 No need to open URL
        toast.success(t("wallets.transferSuccess"));
      }

      // 🧩 Step 5 — Close modal
      // onClose?.();
    } catch (err) {
      const result = extractError(err);
      toast.error(result.message || t("wallets.cannotLoadGame"), {
        duration: 8000,
      });
    } finally {
      setLoading(false);
    }
  };

  // const handleConfirm = async () => {
  //   if (!url) return;
  //   const amount = parseFloat(fromAmount) || 0;

  //   // 🧭 1️⃣ Pre-open a new tab (Safari-safe)
  //   const win = window.open("", "_blank");

  //   if (win && win.document) {
  //     const doc = win.document;
  //     doc.title = t("wallets.loadingGameTitle");
  //     doc.body.style.cssText =
  //       "font-family:sans-serif; text-align:center; padding-top:60px; background:#0B1D48; color:white;";
  //     doc.body.innerHTML = `
  //     <h2>${t("wallets.loadingGame")}</h2>
  //     <p>${t("wallets.pleaseWaitToContinue")}</p>
  //   `;
  //   }

  //   try {
  //     setLoading(true);

  //     // 2️⃣ Only run transfer if amount > 0
  //     if (amount > 0) {
  //       const payload = {
  //         amount,
  //         gamemember_id: gmemberId,
  //         ip: await getClientIp(),
  //         member_id: info?.member_id,
  //       };

  //       if (isCreditToPoints) {
  //         await fromCreditToPoint(payload).unwrap();
  //       } else {
  //         await fromPointToCredit(payload).unwrap();
  //       }

  //       setFromAmount("0.00");
  //       setToAmount("0.00");
  //       refetchMember();
  //     }

  //     // 3️⃣ Success → redirect game page
  //     onClose?.();
  //     if (win) win.location.href = url;
  //   } catch (err) {
  //     const result = extractError(err);
  //     setLoading(false);

  //     // ✅ Toast error in current tab
  //     toast.error(result.message || t("wallets.cannotLoadGame"), {
  //       duration: 8000,
  //     });

  //     // ✅ Prepare localized texts for new tab
  //     const titleText = t("wallets.errorTitle"); // e.g. "Error"
  //     const messageText = result.message; // e.g. "Cannot load game. Please go back and try again."
  //     const closeText = t("wallets.close"); // e.g. "Close"

  //     // ✅ Show fallback message inside opened tab
  //     if (win && win.document) {
  //       const doc = win.document;
  //       doc.title = titleText;
  //       doc.body.style.cssText =
  //         "font-family:sans-serif;text-align:center;padding-top:60px;background:#0B1D48;color:white;";
  //       doc.body.innerHTML = `
  //     <h2 style="color:#FFC000;">${titleText}</h2>
  //     <p>${messageText}</p>
  //     <p style="margin-top:16px;font-size:13px;color:#ccc;">${t(
  //       "wallets.redirectingSoon"
  //     )}</p>
  //   `;

  //       // ✅ Auto-close tab and refocus main window
  //       setTimeout(() => {
  //         win.close();
  //         window.focus();
  //       }, 4000);
  //     }
  //   } finally {
  //     setLoading(false);
  //   }
  // };

  // const handleConfirm = async () => {
  //   const amount = parseFloat(fromAmount);

  //   if (amount > 0) {
  //     try {
  //       setLoading(true);
  //       let ip = "unknown";
  //       try {
  //         const res = await fetch("https://ipapi.co/json/");
  //         const data = await res.json();
  //         ip = data?.ip || "unknown";
  //       } catch (_) {}

  //       const payload = {
  //         amount,
  //         gamemember_id: playerId,
  //         ip,
  //         member_id: info?.member_id,
  //       };

  //       if (isCreditToPoints) {
  //         await fromCreditToPoint(payload).unwrap();
  //       } else {
  //         await fromPointToCredit(payload).unwrap();
  //       }

  //       // showMessage(t("wallets.transferSuccess"), "success");
  //       setFromAmount("0.00");
  //       setToAmount("0.00");
  //       onClose?.();

  //       // ✅ open new tab after successful transfer
  //       if (url) {
  //         setTimeout(() => {
  //           window.open(url, "_blank", "noopener,noreferrer");
  //         }, 400);
  //       }
  //     } catch (err) {
  //       const result = extractError(err);
  //       showMessage(result.message || t("wallets.transferFailed"), "error");
  //     } finally {
  //       setLoading(false);
  //     }
  //   }

  //   // if (!amount || amount <= 0) {
  //   //   showMessage(t("wallets.enterValidAmount"), "error");
  //   //   return;
  //   // }

  //   // showConfirm({
  //   //   titleKey: "wallets.confirmTitle",
  //   //   messageKey: isCreditToPoints
  //   //     ? "wallets.confirmCreditToPoints"
  //   //     : "wallets.confirmPointsToCredit",
  //   //   confirmKey: "common.confirm",
  //   //   cancelKey: "common.cancel",
  //   //   onConfirm: async () => {
  //   //     try {
  //   //       setLoading(true);
  //   //       let ip = "unknown";
  //   //       try {
  //   //         const res = await fetch("https://ipapi.co/json/");
  //   //         const data = await res.json();
  //   //         ip = data?.ip || "unknown";
  //   //       } catch (_) {}

  //   //       const payload = {
  //   //         amount,
  //   //         gamemember_id: playerId,
  //   //         ip,
  //   //         member_id: info?.member_id,
  //   //       };

  //   //       if (isCreditToPoints) {
  //   //         await fromCreditToPoint(payload).unwrap();
  //   //       } else {
  //   //         await fromPointToCredit(payload).unwrap();
  //   //       }

  //   //       showMessage(t("wallets.transferSuccess"), "success");
  //   //       setFromAmount("0.00");
  //   //       setToAmount("0.00");
  //   //       onClose?.();

  //   //       // ✅ open new tab after successful transfer
  //   //       if (url) {
  //   //         setTimeout(() => {
  //   //           window.open(url, "_blank", "noopener,noreferrer");
  //   //         }, 400);
  //   //       }
  //   //     } catch (err) {
  //   //       const result = extractError(err);
  //   //       showMessage(result.message || t("wallets.transferFailed"), "error");
  //   //     } finally {
  //   //       setLoading(false);
  //   //     }
  //   //   },
  //   // });
  // };

  if (!open) return null;

  return (
    <div
      className="fixed inset-0 z-[100] flex items-center justify-center bg-black/60 backdrop-blur-[2px]"
      onClick={onClose}
    >
      <div
        className="relative w-[90%] max-w-[420px] rounded-2xl bg-[#0B1D48] text-white border-2 border-[#F8AF07] shadow-2xl px-5 pb-6 pt-4"
        onClick={(e) => e.stopPropagation()}
      >
        {/* close button */}
        <button
          onClick={onClose}
          className="absolute right-3 top-3 text-white/60 active:scale-95"
        >
          <Image
            src={IMAGES.iconYellowClose}
            alt="close"
            width={20}
            height={20}
          />
        </button>

        {/* Header */}
        <h1 className="text-center text-lg font-semibold mb-4 mt-2">
          {t("wallets.transferTitle")}
        </h1>

        {/* Transfer direction card */}
        <div
          onClick={() => setIsCreditToPoints((prev) => !prev)}
          className="rounded-2xl bg-[#10296E] p-4 border border-[#1C3CA2] active:scale-95 transition-transform duration-150 cursor-pointer"
        >
          <div className="flex items-center justify-between">
            {/* Left */}
            <div className="flex flex-col items-start flex-1">
              <p className="text-sm font-semibold">
                {isCreditToPoints ? (
                  t("wallets.credit")
                ) : (
                  <>
                    {providerName} {t("wallets.points")}
                  </>
                )}
              </p>
              <p className="text-[13px] text-white/50">
                ID:
                {isCreditToPoints
                  ? info?.member_id ?? ""
                  : providerLoginId ?? ""}
              </p>
              <div className="mt-2 flex items-center gap-1 text-[#FFC000]">
                <Image
                  src={
                    isCreditToPoints
                      ? IMAGES.withdraw.inputMoney
                      : IMAGES.games.iconStar
                  }
                  alt="credit"
                  width={18}
                  height={18}
                />
                <span className="text-lg font-semibold">
                  {Number(
                    isCreditToPoints ? creditsBalance : pointsBalance
                  ).toFixed(2)}
                </span>
              </div>
            </div>

            {/* Center arrow (auto rotate) */}
            <div className="mx-3">
              <Image
                src={IMAGES.games.transferArrowRight}
                alt="transfer"
                width={30}
                height={30}
                className={`object-contain transition-transform duration-700 ${
                  isCreditToPoints ? "rotate-[720deg]" : "rotate-[1440deg]"
                }`}
              />
            </div>

            {/* Right */}
            <div className="flex flex-col items-end flex-1">
              <p className="text-sm font-semibold">
                {isCreditToPoints ? (
                  <>
                    {providerName} {t("wallets.points")}
                  </>
                ) : (
                  t("wallets.credit")
                )}
              </p>
              <p className="text-[13px] text-white/50">
                ID:
                {isCreditToPoints
                  ? providerLoginId ?? ""
                  : info?.member_id ?? ""}
              </p>
              <div className="mt-2 flex items-center gap-1 text-[#FFC000]">
                <Image
                  src={
                    isCreditToPoints
                      ? IMAGES.games.iconStar
                      : IMAGES.withdraw.inputMoney
                  }
                  alt="points"
                  width={18}
                  height={18}
                />
                <span className="text-lg font-semibold">
                  {Number(
                    isCreditToPoints ? pointsBalance : creditsBalance
                  ).toFixed(2)}
                </span>
              </div>
            </div>
          </div>
        </div>

        {/* Input amount */}
        <div className="mt-6">
          <p className="text-center text-sm text-white/70 mb-3">
            {t("wallets.enterAmount")}
          </p>

          <div
            className="flex items-center rounded-full px-4 py-3"
            style={{ border: "1.5px solid rgba(255,255,255,0.5)" }}
          >
            <Image
              src={IMAGES.withdraw.inputMoney}
              alt="money"
              width={20}
              height={20}
              className="mr-2"
            />
            <input
              inputMode="decimal"
              value={fromAmount}
              onKeyDown={(e) =>
                handleATMInput(e, fromAmount, setFromAmount, (val) =>
                  setToAmount(calcPoints(val))
                )
              }
              onChange={() => {}}
              className="flex-1 bg-transparent text-sm font-semibold text-[#FFC000] outline-none"
            />
            <button
              type="button"
              onClick={() => {
                // only auto-set if fromAmount is empty or 0 (optional—remove this guard if you always want to set)

                const base = isCreditToPoints
                  ? Number(creditsBalance) // using credits when Credit ➜ Points
                  : Number(pointsBalance); // using points when Points ➜ Credit

                const val = (base || 0).toFixed(2);
                setFromAmount(val);

                // use the correct converter for the current direction
                setToAmount(
                  isCreditToPoints
                    ? calcPoints(val) // credit -> points
                    : calcCredit(val) // points -> credit (implement this if you haven't)
                );
              }}
              className="ml-3 rounded-full px-4 py-1.5 text-sm font-semibold text-black active:scale-95"
              style={{
                background: "linear-gradient(180deg, #F8AF07 0%, #FFFC86 100%)",
              }}
            >
              {t("wallets.all")}
            </button>
          </div>
        </div>

        {/* Confirm */}
        {/* <div className="mt-6">
          <button
            onClick={(e) => handleConfirm(e, false)}
            type="button"
            className="flex-1 w-full  rounded-full border py-3 text-sm font-semibold text-[#FFC000]"
            style={{ borderColor: "#FFC000" }}
          >
            {t("wallets.transfer")}
          </button>
        </div> */}

        <div className="mt-6">
          <button
            onClick={(e) => handleConfirm(e, true)}
            disabled={isLoading}
            className={`flex-1 w-full rounded-full py-3 text-sm font-semibold transition active:scale-95 ${
              isLoading ? "opacity-60 cursor-not-allowed" : "text-black"
            }`}
            style={{
              background: "linear-gradient(180deg, #43E97B 0%, #38F9D7 100%)", // ✅ green gradient
            }}
          >
            {isLoading ? "..." : t("wallets.openGame")}
          </button>
        </div>
      </div>
    </div>
  );
}
