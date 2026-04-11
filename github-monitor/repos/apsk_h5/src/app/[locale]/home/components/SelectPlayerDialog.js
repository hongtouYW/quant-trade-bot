"use client";

import Image from "next/image";
import { useTranslations } from "next-intl";
import { IMAGES } from "@/constants/images"; // if you have a star/chevron asset; otherwise keep SVGs below

import { extractError, getMemberInfo } from "@/utils/utility";
import { useContext, useEffect, useState } from "react";
import Link from "next/link";
import { UIContext } from "@/contexts/UIProvider";
import {
  useAddBookmarkMutation,
  useDeleteBookmarkMutation,
} from "@/services/bookMarkApi";
import toast from "react-hot-toast";
import { useDispatch } from "react-redux";
import baseApi from "@/services/baseApi";
import { useProviderStore } from "@/store/zustand/providerStore";
import { useRouter } from "next/navigation";
import { useGameStore } from "@/store/zustand/gameStore";

export default function SelectPlayerDialog({
  open = true,
  players = [],
  gamePid, //gamePlatformId
  isBookMark,
  activeId, // ✅ now available
  activeTab, // ✅ now available
  memberId,
  providerName,
  providerCategory,
  providerId,
  // ✅ now available
  //action
  onClose,
  onAddNew,
  onChoose,
  onRefresh,
  onToggleBookMark,

  gamebookmarkId,
}) {
  const [bookMarkState, setBookMarkState] = useState(isBookMark);
  const [gBookMarkId, setGBookMarkId] = useState(gamebookmarkId);
  const t = useTranslations();
  const [addBookmark] = useAddBookmarkMutation();
  const [deleteBookmark] = useDeleteBookmarkMutation();
  const member = getMemberInfo();
  const { setLoading } = useContext(UIContext);
  const resetProvider = useProviderStore((s) => s.reset);
  const resetGame = useGameStore((s) => s.reset);

  const setSelectedProvider = useProviderStore((s) => s.setSelectedProvider);

  const router = useRouter();
  const dispatch = useDispatch();
  if (!open) return null;

  const handleCancel = () => onClose?.();

  const handleFavorite = async (e) => {
    e.stopPropagation();
    if (!member?.member_id) return;

    setLoading(true);
    try {
      if (bookMarkState) {
        // 🔹 remove bookmark
        const res = await deleteBookmark({
          gamebookmark_id: gBookMarkId,
          user_id: member.member_id,
        }).unwrap();

        if (res?.status) {
          const newId = res?.data?.gamebookmark_id;
          setGBookMarkId(newId);
          setBookMarkState(false);
          onToggleBookMark?.(gameId, false, newId);

          dispatch(
            baseApi.util.updateQueryData(
              "getGameList",
              {
                type: activeId,
                user_id: member.member_id,
                ...(activeTab === "favorite" ? { isBookmark: true } : {}),
              },
              (draft) => {
                const game = draft.data?.find((g) => g.game_id === gameId);
                if (game) {
                  game.isBookmark = false;
                  game.gamebookmark_id = newId;
                }
              }
            )
          );
        }
      } else {
        // 🔹 add bookmark
        const res = await addBookmark({
          game_id: gameId,
          gamebookmark_name: "Favourite",
          member_id: member.member_id,
          user_id: member.member_id,
        }).unwrap();

        if (res?.status) {
          const newId = res?.data?.gamebookmark_id;
          setBookMarkState(true);
          setGBookMarkId(newId);
          onToggleBookMark?.(gameId, true, newId);
          dispatch(
            baseApi.util.updateQueryData(
              "getGameList",
              {
                type: activeId,
                user_id: member.member_id,
                ...(activeTab === "favorite" ? { isBookmark: true } : {}),
              },
              (draft) => {
                const game = draft.data?.find((g) => g.game_id === gameId);
                if (game) {
                  game.isBookmark = true;
                  game.gamebookmark_id = newId;
                }
              }
            )
          );
        }
      }
    } catch (err) {
      const result = extractError(err);
      if (result.type === "validation") {
        toast.error(Object.values(result.fieldErrors).join(", "));
      } else {
        toast.error(JSON.stringify(err));
      }
    } finally {
      setLoading(false);
    }
  };

  const handleClick = (p) => {
    resetProvider();
    resetGame();
    setSelectedProvider({
      gamemember_id: p.gamemember_id, // this is provider member
      providerId,
      gamePid,
      providerName,
      providerCategory,
    });

    router.push("/game-start");
  };

  return (
    <div
      className="fixed inset-0 z-[100] bg-black/60 backdrop-blur-[1px]"
      onClick={handleCancel}
      aria-hidden
    >
      <div
        className="absolute inset-x-0 top-1/2 -translate-y-1/2 mx-auto max-w-[480px] min-h-[200px] rounded-2xl bg-[#0B1D48] text-white p-4 shadow-2xl"
        onClick={(e) => e.stopPropagation()}
        role="dialog"
        aria-modal="true"
        aria-label={t("players.selectTitle")}
      >
        {/* Handle bar */}
        <div className="mx-auto mb-3 h-1.5 w-12 rounded-full bg-white/30" />

        {/* Title + close (X) */}
        <div className="relative flex items-center justify-center">
          <button
            onClick={handleCancel}
            className="absolute left-0 grid h-8 w-8 place-items-center rounded-full text-white/80 active:scale-95"
            aria-label={t("common.close")}
          >
            {/* Small X icon */}
            <Image
              src={IMAGES.iconYellowClose}
              alt="back"
              width={22}
              height={22}
              className="object-contain"
            />
          </button>
          <h3 className="text-base font-semibold text-center">
            {t("players.selectTitle")}
          </h3>

          <button
            // onClick={handleFavorite}
            // onClick={handleFavorite}
            className="absolute right-0 grid h-8 w-8 place-items-center rounded-full text-white/80 active:scale-95"
            aria-label={t("common.favorite")}
          >
            <Image
              src={bookMarkState ? IMAGES.favorite : IMAGES.unfavorite}
              alt={bookMarkState ? "favourite" : "unfavourite"}
              width={22}
              height={22}
              className="object-contain"
            />
          </button>
        </div>

        {/* List */}
        <div className="mt-4 overflow-hidden rounded-xl ring-1 ring-white/10">
          {players.map((p, idx) => (
            <div
              // onClick={() => onChoose?.(p)}
              className="flex w-full items-center gap-3 bg-[#0B1D48] px-4 py-3 hover:bg-white/5"
              key={p.gamemember_id ?? idx}
              onClick={() => handleClick(p)}
            >
              {/* Avatar placeholder */}
              <div className="grid h-5 w-5 shrink-0 place-items-center">
                <Image
                  src={IMAGES.iconUser}
                  alt="profile"
                  width={22}
                  height={22}
                  className="object-contain"
                />
              </div>

              {/* Name + ID */}
              <div className="min-w-0 flex-1 ml-4">
                <p className="text-sm text-left text-[#F8AF07]">{p.login}</p>
                <p className="text-[13px] text-left text-white/60 truncate">
                  ID: {p.gamemember_id}
                </p>
              </div>

              {/* Points pill */}
              <div className="flex items-center gap-2">
                <div className="flex items-center rounded-full px-2 py-1">
                  <div className="flex items-center gap-2 rounded-full bg-[#354B9C] ">
                    <div
                      className="flex h-8 w-8 items-center justify-center rounded-full"
                      style={{
                        background:
                          "linear-gradient(180deg, #F8AF07 0%, #FFFC86 100%)",
                      }}
                    >
                      <Image
                        src={IMAGES.withdraw.buttonstar}
                        alt="points"
                        width={17}
                        height={17}
                      />
                    </div>

                    <span className="text-sm font-semibold mr-4 m-2">
                      {Number(p.balance ?? 0).toFixed(2)}
                    </span>
                  </div>
                </div>
                <span className="text-[13px] text-white/60">
                  {t("players.points")}
                </span>

                {/* Chevron */}
                <svg
                  viewBox="0 0 24 24"
                  className="ml-1 h-5 w-5 text-white/70"
                  fill="none"
                  stroke="currentColor"
                  strokeWidth="1.6"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    d="M9 5l7 7-7 7"
                  />
                </svg>
              </div>
            </div>
          ))}
        </div>

        {/* Add new */}
        {players?.length == 0 && (
          <div className="mt-5">
            <button
              onClick={onAddNew}
              className="mx-auto flex items-center justify-center gap-2 rounded-full border border-[#FFC000] px-20 py-3 text-sm font-medium text-[#FFC000] active:scale-95
       disabled:cursor-not-allowed disabled:border-[#b38a00] disabled:bg-[#1a1a1a] disabled:text-[#b38a00] disabled:active:scale-100"
            >
              <Image
                src={IMAGES.iconYellowPlus}
                alt="points"
                width={17}
                height={17}
              />
              {t("players.addNew")}
            </button>
          </div>
        )}
      </div>
    </div>
  );
}
