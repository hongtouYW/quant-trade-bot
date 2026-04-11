"use client";

import Image from "next/image";
import { useState, useEffect, useRef, useContext } from "react";
import { useParams, useRouter } from "next/navigation";
import { useTranslations } from "next-intl";
import Link from "next/link";
import toast from "react-hot-toast";

import { IMAGES } from "@/constants/images";
import SubmitButton from "@/components/shared/SubmitButton";
import BalanceBar from "@/components/shared/TransferBalanceBar";
import { UIContext } from "@/contexts/UIProvider";
import { useBalanceStore } from "@/store/zustand/balanceStore";
import { useDeletePlayerMutation } from "@/services/authApi";
import QRCode from "react-qr-code"; // ✅ stable default export
import {
  calcCredit,
  calcPoints,
  extractError,
  getClientIp,
  getMemberInfo,
  handleATMInput,
} from "@/utils/utility";
import {
  useGetPlayerPasswordQuery,
  useGetPlayerViewQuery,
} from "@/services/authApi";
import {
  useGetGameViewQuery,
  useOpenGameUrlMutation,
} from "@/services/gameApi";
import {
  useFromCreditToPointMutation,
  useFromPointToCreditMutation,
} from "@/services/transactionApi";
import { skipToken } from "@reduxjs/toolkit/query";

export default function GameStartPage() {
  const t = useTranslations();
  const router = useRouter();
  const { parts } = useParams();
  const [deletePlayer] = useDeletePlayerMutation();
  const [openGameUrl] = useOpenGameUrlMutation();
  const gmemberId = Array.isArray(parts) ? parts[0] : null;
  const gameId = Array.isArray(parts) ? parts[1] : null;

  // ---------- HYDRATION-SAFE STATE ----------
  // Mount gate to avoid SSR/CSR mismatch for dynamic content
  const [mounted, setMounted] = useState(false);
  useEffect(() => setMounted(true), []);

  // Derive playerId from params ONLY after mount//member id
  const [playerId, setPlayerId] = useState(gmemberId);
  // useEffect(() => {
  //   // params.id is only reliable after mount for hydration
  //   if (mounted && params?.gmemberId) setPlayerId(params.gmemberId);
  // }, [mounted, params]);

  // Stable defaults
  const [fromAmount, setFromAmount] = useState("0.00");
  const [toAmount, setToAmount] = useState("0.00");
  const [delta] = useState("+ 0.00");

  const { setLoading } = useContext(UIContext);
  const [username, setUsername] = useState("");
  const [password, setPassword] = useState("");
  const [isCreditToPoints, setIsCreditToPoints] = useState(true);
  const [transferOpen, setTransferOpen] = useState(false);

  const balanceRef = useRef(null);
  const creditsBalance = useBalanceStore((s) => s.credits);
  const pointsBalance = useBalanceStore((s) => s.points);
  const { showConfirm } = useContext(UIContext);
  const [gameUrl, setGameUrl] = useState(null); // ✅ store API result here

  const [info, setInfo] = useState(null);
  // Load member info only on client
  useEffect(() => {
    if (!mounted) return;
    const member = getMemberInfo();
    setInfo(member || null);
  }, [mounted]);

  useEffect(() => {
    if (!info?.member_id || !gmemberId || !gameId) return;

    // const fetchUrl = async () => {
    //   try {
    //     const res = await openGameUrl({
    //       member_id: info.member_id,
    //       gamemember_id: gmemberId,
    //       game_id: gameId,
    //     }).unwrap();

    //     if (res?.Data) {
    //       setGameUrl(res.Data); // ✅ save to state
    //     }
    //   } catch (err) {
    //     // console.error("Failed to get game URL:", err);
    //   }
    // };

    // fetchUrl();
  }, [info, gmemberId, gameId]);

  // ---------- QUERIES (force refetch) ----------
  const canQuery = mounted && playerId && info?.member_id;

  const {
    data: playerData,
    isLoading: playerLoading,
    refetch: refetchPlayer,
  } = useGetPlayerViewQuery(
    canQuery
      ? { gamemember_id: playerId, member_id: info.member_id }
      : skipToken,
    {
      skip: !canQuery,
      refetchOnMountOrArgChange: true,
      refetchOnFocus: true,
      keepUnusedDataFor: 0,
    },
  );

  const {
    data: passwordData,
    isLoading: isPasswordLoading,
    isFetching: isPasswordFetching,
    refetch: refetchPwd,
  } = useGetPlayerPasswordQuery(
    canQuery ? { gamemember_id: playerId, user_id: info.member_id } : skipToken,
    {
      skip: !canQuery,
      refetchOnMountOrArgChange: true,
      refetchOnFocus: true,
      keepUnusedDataFor: 0,
    },
  );

  const {
    data: gameViewData,
    isLoading: gameViewLoading,
    isFetching: gameViewFetching,
    refetch: refetchGame,
  } = useGetGameViewQuery(
    mounted && gameId && info?.member_id
      ? { game_id: gameId, user_id: info.member_id }
      : skipToken,
    {
      skip: !mounted || !gameId || !info?.member_id,
      refetchOnMountOrArgChange: true,
      refetchOnFocus: true,
      keepUnusedDataFor: 0,
    },
  );

  useEffect(() => {
    setLoading(
      gameViewLoading ||
        gameViewFetching ||
        isPasswordLoading ||
        isPasswordFetching,
    );
  }, [
    gameViewLoading,
    gameViewFetching,
    isPasswordFetching,
    isPasswordLoading,
    setLoading,
  ]);

  // Reflect query data in state (client-only)
  useEffect(() => {
    if (passwordData?.password) setPassword(passwordData.password);
  }, [passwordData]);

  useEffect(() => {
    if (playerData?.data?.loginId) setUsername(playerData.data.loginId);
  }, [playerData]);

  const [fromCreditToPoint, { isLoading }] = useFromCreditToPointMutation();
  const [fromPointToCredit] = useFromPointToCreditMutation();
  const qrRef = useRef(null);
  // ---------- HELPERS ----------
  function skipArg() {
    // dummy body for RTKQ when skipping; avoids accidental SSR changes
    return { __skip: true };
  }

  const handleOpen = async () => {
    try {
      window.open(gameUrl, "_blank");

      // setLoading(true);
      // const res = await openGameUrl({
      //   member_id: info?.member_id,
      //   gamemember_id: playerId,
      //   game_id: gameId,
      // }).unwrap();
      // console.log(res);
      // if (res?.Data) {
      //   setLoading(false);
      //   // ✅ Open in new tab
      //   window.open(res?.Data, "_blank", "noopener,noreferrer");
      // } else {
      //   // setError("No game URL returned");
      // }
    } catch (err) {
      // setError("Failed to open game");
      console.error(err);
    } finally {
      setLoading(false);
    }
  };

  const handleSave = () => {
    const svg = qrRef.current;
    if (svg) {
      const serializer = new XMLSerializer();
      const source = serializer.serializeToString(svg);

      const svgBlob = new Blob([source], {
        type: "image/svg+xml;charset=utf-8",
      });
      const url = URL.createObjectURL(svgBlob);

      const img = new window.Image(); // ✅ native Image, not next/image
      img.onload = () => {
        const size = 300; // make QR bigger
        const canvas = document.createElement("canvas");
        canvas.width = size;
        canvas.height = size;

        const ctx = canvas.getContext("2d");
        ctx.fillStyle = "#fff"; // background white
        ctx.fillRect(0, 0, size, size);
        ctx.drawImage(img, 0, 0, size, size);

        canvas.toBlob((blob) => {
          const link = document.createElement("a");
          link.href = URL.createObjectURL(blob);
          link.download = "invite-qr.webp";
          link.click();
        });

        URL.revokeObjectURL(url);
      };
      img.src = url;
    }
  };

  const handleDelete = async () => {
    setLoading(true);

    try {
      const ip = await getClientIp();
      const payload = {
        gamemember_id: params?.id,
        ip,
        member_id: info?.member_id,
      };

      const result = await deletePlayer(payload).unwrap();
      if (result?.status) {
        toast.success(t("players.deleteSuccess"));
        if (balanceRef?.current?.handleRefresh) {
          balanceRef.current.handleRefresh();
        }

        setTimeout(() => {
          router.push("/"); // ✅ always go home after short delay
        }, 1300); // 0.8s is smooth, can adjust
      }
    } catch (err) {
      toast.error(t("players.deleteFailed"));
    } finally {
      setLoading(false);
    }
  };

  const handleClick = () => {
    showConfirm({
      titleKey: "players.deleteConfirmTitle",
      message: t("players.deleteConfirmDesc", { player: username }), // ✔ direct text
      confirmKey: "common.confirm",
      cancelKey: "common.cancel",
      onConfirm: () => {
        handleDelete();
      },
      onCancel: () => {},
    });
  };

  const openGame = (pData) => {
    try {
      if (typeof navigator !== "undefined") {
        if (/android/i.test(navigator.userAgent)) {
          if (pData?.deeplink?.android) {
            window.location.href = pData.deeplink.android;
            return;
          }
        } else if (/iphone|ipad|ipod/i.test(navigator.userAgent)) {
          if (pData?.deeplink?.ios) {
            window.location.href = pData.deeplink.ios;
            return;
          }
        }
      }
      if (gameViewData?.downloadurl) {
        window.open(gameViewData.downloadurl, "_blank", "noopener,noreferrer");
      }
    } catch (err) {
      console.error("Failed to open game:", err);
      if (gameViewData?.downloadurl) {
        window.open(gameViewData.downloadurl, "_blank", "noopener,noreferrer");
      }
    }
  };

  // ---------- RENDER ----------
  // Render a minimal, stable shell on server to avoid mismatches.
  // Then after `mounted`, real dynamic content appears.
  return (
    <div className="mx-auto max-w-[480px] min-h-dvh bg-[#00143D] text-white px-4 pb-8">
      {/* Header */}
      <div className="sticky top-0 z-20 bg-[#00143D]">
        <div className="relative flex items-center h-14 ">
          <button onClick={() => router.back()} className="z-10 cursor-pointer">
            <Image
              src={IMAGES.arrowLeft}
              alt="back"
              width={22}
              height={22}
              className="object-contain"
            />
          </button>

          {/* Use empty string until mounted + data ready to avoid SSR/CSR text mismatch */}
          <h1 className="absolute inset-x-0 text-lg font-semibold text-center">
            {mounted && gameViewData?.game?.game_name
              ? gameViewData.game.game_name
              : ""}
          </h1>
        </div>
      </div>

      {/* Balance bar can receive raw param id; pass stable value (string or empty) */}
      <BalanceBar ref={balanceRef} gameMemberId={mounted ? gmemberId : ""} />

      {/* Divider */}
      <div className="mt-3 h-px w-full bg-[#354B9C]" />

      {/* QR + Player ID */}
      <div className="p-1">
        <div className="flex gap-4">
          <div className="rounded-xl p-2">
            <div className="flex h-[168px] w-[120px] flex-col overflow-hidden rounded-xl bg-white">
              <div className="flex flex-1 items-center justify-center">
                <QRCode ref={qrRef} value={String(playerId || "")} size={100} />
              </div>
              <button
                onClick={handleSave}
                className="w-full py-1.5 text-sm font-semibold text-black"
                style={{
                  background:
                    "linear-gradient(180deg, #F8AF07 0%, #FFFC86 100%)",
                }}
              >
                {t("earn.share.save")}
              </button>
            </div>
          </div>

          {/* Player ID input */}
          <div className="flex flex-1 items-center">
            <div className="w-full">
              <div className="mb-2 text-sm font-semibold">
                {t("players.myId")}
              </div>
              <div className="relative">
                <input
                  type="text"
                  value={playerId ?? ""}
                  disabled
                  onChange={(e) => setPlayerId(e.target.value)}
                  className="w-full rounded-xl bg-transparent px-4 py-3 text-sm outline-none"
                  style={{
                    border: "1.25px solid",
                    borderImageSlice: 1,
                    borderImageSource:
                      "linear-gradient(180deg, #F8AF07 0%, #FFFC86 100%)",
                  }}
                  placeholder={t("players.myId")}
                />
                <button
                  type="button"
                  onClick={() => {
                    if (typeof navigator !== "undefined" && playerId) {
                      navigator.clipboard.writeText(playerId);
                      toast.success(t("common.copySuccess"));
                    }
                  }}
                  className="absolute right-3 top-1/2 -translate-y-1/2"
                >
                  <Image
                    src={IMAGES.iconCopy}
                    alt="copy"
                    width={18}
                    height={18}
                  />
                </button>
              </div>
            </div>
          </div>
        </div>

        {/* Actions */}
        <div className="mt-4 flex items-center gap-4">
          <button
            onClick={handleClick}
            type="button"
            className="flex-1 flex items-center justify-center gap-2 rounded-full border-2 py-2.5 text-sm font-semibold text-[#FF7A7A] active:scale-95"
            style={{ borderColor: "#FF7A7A" }}
          >
            <Image src={IMAGES.redtrash} alt="delete" width={16} height={16} />
            <span>{t("players.delete")}</span>
          </button>

          <Link
            style={{ border: "1.8px solid #FFC000" }}
            className="flex flex-1 items-center justify-center rounded-full py-2.5 text-sm font-semibold text-[#FFC000]"
            href={`/game-start/change-password/${playerId}`}
          >
            *** {t("players.setPassword")}
          </Link>
        </div>
      </div>

      {/* Divider */}
      <div className="mt-2 mb-2 h-px w-full bg-[#354B9C]" />

      {gameViewData?.downloadurl ? (
        /* ✅ Case 1: Has download → keep 3 steps */
        <div className="mt-2 rounded-2xl  p-4 ">
          <p className="text-sm font-semibold text-center">
            {t("mega.credentials.title")} {/* 您的登录凭证 */}
          </p>

          <div className="mt-3 space-y-3">
            {/* Username row */}
            <div
              className="flex items-center gap-2 rounded-full px-3 py-2"
              style={{ border: "1.4px solid rgba(255,255,255,0.6)" }}
            >
              <span className="w-16 shrink-0 text-sm">
                {t("mega.credentials.username")}
              </span>
              <input
                readOnly
                value={username}
                className="flex-1 bg-transparent text-sm outline-none"
              />
              <button
                type="button"
                onClick={() => {
                  navigator.clipboard.writeText(username);
                  toast.success(t("common.copySuccess"));
                }}
                className="grid h-8 w-8 place-items-center rounded-full bg-white/10"
                aria-label={t("mega.actions.copy")}
              >
                <Image
                  src={IMAGES.iconCopy}
                  alt="copy"
                  width={16}
                  height={16}
                />
              </button>
            </div>

            {/* Password row */}
            <div
              className="flex items-center gap-2 rounded-full px-3 py-2"
              style={{ border: "1.4px solid rgba(255,255,255,0.6)" }}
            >
              <span className="w-16 shrink-0 text-sm">
                {t("mega.credentials.password")}
              </span>
              <input
                readOnly
                type="text"
                value={password}
                className="flex-1 bg-transparent text-sm outline-none"
              />
              <button
                type="button"
                onClick={() => {
                  navigator.clipboard.writeText(password);
                  toast.success(t("common.copySuccess"));
                }}
                className="grid h-8 w-8 place-items-center rounded-full bg-white/10"
                aria-label={t("mega.actions.copy")}
              >
                <Image
                  src={IMAGES.iconCopy}
                  alt="copy"
                  width={16}
                  height={16}
                />
              </button>
            </div>
          </div>
        </div>
      ) : null}

      {/* Credentials card */}

      {/* Divider */}
      <div className="mt-2 mb-2 h-px w-full bg-[#354B9C]" />

      {/* Transfer Section */}
      <section className="mt-3">
        {isCreditToPoints ? (
          <>
            <p className="text-sm font-semibold">{t("wallets.fromCredit")}</p>
            <p className="text-[13px] text-white/50">
              ID:{mounted && info?.member_id ? info.member_id : ""}
            </p>

            <div
              className="mt-2 flex items-center rounded-full px-3 py-2"
              style={{ border: "1.4px solid rgba(255,255,255,0.6)" }}
            >
              <span className="mr-2 grid h-7 w-7 place-items-center rounded-full">
                <Image
                  src={IMAGES.withdraw.inputMoney}
                  alt="credit"
                  width={25}
                  height={25}
                />
              </span>

              <div className="flex w-full items-center">
                <div className="flex-1 pr-2">
                  <input
                    inputMode="decimal"
                    value={fromAmount}
                    onKeyDown={(e) =>
                      handleATMInput(e, fromAmount, setFromAmount, (val) =>
                        setToAmount(calcPoints(val)),
                      )
                    }
                    onChange={() => {}}
                    className="w-full bg-transparent text-sm font-semibold text-[#FFC000] outline-none"
                  />
                </div>

                <button
                  type="button"
                  onClick={() => {
                    const val = (Number(creditsBalance) || 0).toFixed(2);
                    setFromAmount(val);
                    setToAmount(calcPoints(val));
                  }}
                  className="rounded-full px-4 py-1.5 text-sm font-semibold text-black"
                  style={{
                    background:
                      "linear-gradient(180deg, #F8AF07 0%, #FFFC86 100%)",
                  }}
                >
                  {t("wallets.all")}
                </button>
              </div>
            </div>

            <div className="my-3 grid place-items-center">
              <button
                type="button"
                onClick={() => setIsCreditToPoints((prev) => !prev)}
                className="grid place-items-center rounded-full"
              >
                <Image
                  src={IMAGES.games.iconTransfer}
                  alt="transfer"
                  width={50}
                  height={50}
                />
              </button>
            </div>

            <p className="text-sm font-semibold">{t("wallets.toPoints")}</p>
            <p className="text-[13px] text-white/50">
              ID:{playerId ?? ""} {/* stable before mount */}
            </p>

            <div
              className="mt-2 flex items-center rounded-full px-3 py-3"
              style={{ border: "1.4px solid rgba(255,255,255,0.6)" }}
            >
              <span className="mr-2 grid h-7 w-7 place-items-center rounded-full bg-[#0E1F55]">
                <Image
                  src={IMAGES.games.iconStar}
                  alt="star"
                  width={16}
                  height={16}
                />
              </span>

              <div className="flex w-full items-center">
                <div className="w-1/2 pr-2">
                  <input
                    inputMode="decimal"
                    value={toAmount}
                    onKeyDown={(e) =>
                      handleATMInput(e, toAmount, setToAmount, (val) =>
                        setFromAmount(calcCredit(val)),
                      )
                    }
                    onChange={() => {}}
                    className="w-full bg-transparent text-sm font-semibold text-[#FFC000] outline-none"
                  />
                </div>

                <span className="mx-3 h-5 w-px bg-white/40" />
                <span className="w-1/2 text-right text-sm text-white/40">
                  {delta}
                </span>
              </div>
            </div>
          </>
        ) : (
          <>
            <p className="text-sm font-semibold">{t("wallets.fromPoints")}</p>
            <p className="text-[13px] text-white/50">
              ID:{playerId ?? ""} {/* stable */}
            </p>

            <div
              className="mt-2 flex items-center rounded-full px-3 py-3"
              style={{ border: "1.4px solid rgba(255,255,255,0.6)" }}
            >
              <span className="mr-2 grid h-7 w-7 place-items-center rounded-full bg-[#0E1F55]">
                <Image
                  src={IMAGES.games.iconStar}
                  alt="star"
                  width={20}
                  height={20}
                />
              </span>

              <div className="flex w-full items-center">
                <div className="w-1/2 pr-2">
                  <input
                    inputMode="decimal"
                    value={toAmount}
                    onKeyDown={(e) =>
                      handleATMInput(e, toAmount, setToAmount, (val) =>
                        setFromAmount(calcCredit(val)),
                      )
                    }
                    onChange={() => {}}
                    className="w-full bg-transparent text-sm font-semibold text-[#FFC000] outline-none"
                  />
                </div>

                <span className="mx-3 h-5 w-px bg-white/40" />
                <span className="w-1/2 text-right text-sm text-white/40">
                  {delta}
                </span>
              </div>
            </div>

            <div className="my-3 grid place-items-center">
              <button
                type="button"
                onClick={() => setIsCreditToPoints((prev) => !prev)}
                className="grid place-items-center rounded-full"
              >
                <Image
                  src={IMAGES.games.iconTransfer}
                  alt="transfer"
                  width={50}
                  height={50}
                />
              </button>
            </div>

            <p className="text-sm font-semibold">{t("wallets.toCredit")}</p>
            <p className="text-[13px] text-white/50">
              ID:{mounted && info?.member_id ? info.member_id : ""}
            </p>

            <div
              className="mt-2 flex items-center rounded-full px-3 py-2"
              style={{ border: "1.4px solid rgba(255,255,255,0.6)" }}
            >
              <span className="mr-2 grid h-7 w-7 place-items-center rounded-full">
                <Image
                  src={IMAGES.withdraw.inputMoney}
                  alt="credit"
                  width={25}
                  height={25}
                />
              </span>

              <div className="flex w-full items-center">
                <div className="flex-1 pr-2">
                  <input
                    inputMode="decimal"
                    value={fromAmount}
                    onKeyDown={(e) =>
                      handleATMInput(e, fromAmount, setFromAmount, (val) =>
                        setToAmount(calcPoints(val)),
                      )
                    }
                    onChange={() => {}}
                    className="w-full bg-transparent text-sm font-semibold text-[#FFC000] outline-none"
                  />
                </div>

                <button
                  type="button"
                  onClick={() => {
                    const val = (Number(creditsBalance) || 0).toFixed(2);
                    setFromAmount(val);
                    setToAmount(calcPoints(val));
                  }}
                  className="rounded-full px-4 py-1.5 text-sm font-semibold text-black"
                  style={{
                    background:
                      "linear-gradient(180deg, #F8AF07 0%, #FFFC86 100%)",
                  }}
                >
                  {t("wallets.all")}
                </button>
              </div>
            </div>
          </>
        )}
      </section>

      <div className="mt-4 mb-5 h-px w-full bg-[#354B9C]" />

      {/* Convert */}
      <div className="mt-5">
        <SubmitButton onClick={() => setTransferOpen(true)}>
          {t("wallets.convert")}
        </SubmitButton>
      </div>
      {/* Divider */}
      {gameViewData?.downloadurl ? (
        <div className="mt-4 mb-5 h-px w-full bg-[#354B9C]" />
      ) : null}

      {/* Game Menu */}
      {gameViewData?.downloadurl ? (
        <div className="p-4">
          {/* Logo */}
          {mounted && gameViewData?.game?.icon && (
            <Image
              src={`${process.env.NEXT_PUBLIC_IMAGE_URL}/${gameViewData?.game?.icon}`}
              width={100}
              height={100}
              className="h-20 w-20 object-cover p-2"
              alt="Game Icon"
            />
          )}

          {/* Banner */}
          <div className="overflow-hidden rounded-xl">
            {mounted && gameViewData?.game?.banner && (
              <Image
                src={`${process.env.NEXT_PUBLIC_IMAGE_URL}/${gameViewData?.game?.banner}`}
                width={800}
                height={200}
                className="h-28 w-full object-cover"
                alt="Game Banner"
              />
            )}
          </div>

          {/* Title */}
          <h3 className="mt-4 text-sm font-semibold">
            {t("mega.subtitle")}{" "}
            {mounted && playerData?.data?.game?.game_name
              ? playerData.data.game.game_name
              : ""}
          </h3>

          {/* Step: Has download → keep 3 steps */}
          <ol className="mt-3 space-y-3">
            <li className="flex items-start gap-3">
              <span
                className="grid h-6 w-6 place-items-center rounded-full text-sm font-semibold text-black"
                style={{
                  background: "linear-gradient(180deg,#F8AF07 0%,#FFFC86 100%)",
                }}
              >
                1
              </span>
              <div>
                <p className="text-sm font-semibold">
                  {t("mega.steps.1.title")}
                </p>
                <p className="text-[13px] text-white/70">
                  {t("mega.steps.1.desc")}
                </p>
                <p className="text-[13px] text-[#FFC000]">
                  <a
                    href={gameViewData.downloadurl}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="text-sm font-semibold text-yellow-400 underline"
                  >
                    {t("common.downloadUrl")}
                  </a>
                </p>
              </div>
            </li>

            <li className="flex items-start gap-3">
              <span
                className="grid h-6 w-6 place-items-center rounded-full text-sm font-semibold text-black"
                style={{
                  background: "linear-gradient(180deg,#F8AF07 0%,#FFFC86 100%)",
                }}
              >
                2
              </span>
              <div>
                <p className="text-sm font-semibold">
                  {t("mega.steps.2.title")}
                </p>
                <p className="text-[13px] text-white/70">
                  {t("mega.steps.2.desc")}
                </p>
              </div>
            </li>

            <li className="flex items-start gap-3">
              <span
                className="grid h-6 w-6 place-items-center rounded-full text-sm font-semibold text-black"
                style={{
                  background: "linear-gradient(180deg,#F8AF07 0%,#FFFC86 100%)",
                }}
              >
                3
              </span>
              <div>
                <p className="text-sm font-semibold">
                  {t("mega.steps.3.title")}
                </p>
                <p className="text-[13px] text-white/70">
                  {t("mega.steps.3.desc")}
                </p>
              </div>
            </li>
          </ol>
        </div>
      ) : null}

      {/* Divider */}
      <div className="mt-4 mb-5 h-px w-full bg-[#354B9C]" />

      {/* Bottom actions */}
      <div className="sticky bottom-0 left-0 right-0 bg-[#00143D] px-4 pb-6 pt-3">
        <div className="flex items-center gap-3">
          <button
            disabled={!gameViewData?.downloadurl && !gameUrl}
            type="button"
            onClick={() =>
              gameViewData?.downloadurl ? openGame(playerData) : handleOpen()
            }
            className={`flex-1 rounded-full py-3 text-sm font-semibold transition active:scale-95 ${
              gameViewLoading ? "opacity-50 cursor-not-allowed" : ""
            }`}
            style={{
              background: "linear-gradient(90deg,#27E16D 0%,#16C3A1 100%)",
              color: "#00143D",
            }}
          >
            {t("wallets.openGame")}
          </button>

          {/* <button
            // disabled={gameViewLoading || !gameViewData?.downloadurl}
            type="button"
            onClick={() => handleOpen()}
            className={`flex-1 rounded-full py-3 text-sm font-semibold transition active:scale-95`}
            style={{
              background: "linear-gradient(90deg,#27E16D 0%,#16C3A1 100%)",
              color: "#00143D",
            }}
          >
            {t("wallets.openGame")}
          </button> */}

          <button
            type="button"
            onClick={() => router.push("/topup")}
            className="flex-1 rounded-full border py-3 text-sm font-semibold text-[#FFC000]"
            style={{ borderColor: "#FFC000" }}
          >
            {t("wallets.topup")}
          </button>

          <button
            onClick={() => router.push(`/transfer/${gmemberId}`)}
            type="button"
            className="flex-1 rounded-full border py-3 text-sm font-semibold text-[#FFC000]"
            style={{ borderColor: "#FFC000" }}
          >
            {t("wallets.transfer")}
          </button>
        </div>
      </div>

      {/* Modal */}
      {transferOpen && (
        <div className="fixed inset-0 z-[100] bg-black/60 backdrop-blur-[1px]">
          <div className="absolute inset-x-0 bottom-0 mx-auto max-w-[480px] rounded-t-2xl bg-[#0B1D48] text-white p-4 shadow-2xl">
            <div className="mx-auto mb-2 h-1.5 w-12 rounded-full bg-white/30" />

            <h3 className="mb-2 text-center text-lg font-semibold text-white">
              {t("wallets.confirmTitle")}
            </h3>

            <p className="mb-6 text-center text-sm text-white/70 min-h-[100px]">
              {isCreditToPoints
                ? t("wallets.confirmCreditToPoints")
                : t("wallets.confirmPointsToCredit")}
            </p>

            <div className="flex gap-3 mb-3">
              <button
                className="w-1/2 rounded-full border border-[#FFC000] py-2 text-[#FFC000]"
                onClick={() => setTransferOpen(false)}
              >
                {t("common.cancel")}
              </button>
              <button
                className="w-1/2 rounded-full bg-gradient-to-b from-[#F8AF07] to-[#FFFC86]
             border border-transparent h-12 flex items-center justify-center
             text-black font-semibold disabled:opacity-50 active:scale-95"
                onClick={async () => {
                  const amount = parseFloat(
                    isCreditToPoints ? fromAmount : toAmount,
                  );
                  if (!amount || amount <= 0) {
                    toast.error("Please enter a valid amount");
                    return;
                  }
                  try {
                    setTransferOpen(false);
                    setLoading(true);

                    // Use your own API to avoid CORS (recommended):
                    // const r = await fetch("/api/ip");
                    // const { ip } = await r.json();

                    // Or a CORS-friendly public fallback:
                    let ip = "unknown";
                    try {
                      const r = await fetch("https://ipapi.co/json/");
                      const d = await r.json();
                      ip = d?.ip || "unknown";
                    } catch (_) {}

                    const payload = {
                      amount,
                      gamemember_id: playerId,
                      ip,
                      member_id: info?.member_id,
                    };

                    if (isCreditToPoints) {
                      await fromCreditToPoint(payload).unwrap();
                    } else {
                      await fromPointToCredit(payload).unwrap();
                    }

                    toast.success(t("wallets.transferSuccess"));
                    setFromAmount("0.00");
                    setToAmount("0.00");
                    if (balanceRef?.current?.handleRefresh) {
                      balanceRef.current.handleRefresh();
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
                }}
              >
                {isLoading ? "..." : t("common.confirm")}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
