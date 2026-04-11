"use client";

import Image from "next/image";
import { useRouter } from "next/navigation";
import { useTranslations } from "next-intl";
import { IMAGES } from "@/constants/images";
import SubmitButton from "@/components/shared/SubmitButton";
import {
  useGetInviteListQuery,
  useGetPerformanceProfileQuery,
  useGetPerformanceSummaryQuery,
  useEditInviteNameMutation, // ⭐ ADDED
} from "@/services/referralApi";
import { getMemberInfo } from "@/utils/utility";
import { useEffect, useMemo, useState } from "react";
import MarqueeTitle from "@/components/shared/MarqueeTitle";
import { toast } from "react-hot-toast";
import ShareDialog from "@/components/shared/ShareDialog";
import Link from "next/link";

export default function InvitePage() {
  const router = useRouter();
  const t = useTranslations();

  const [showShare, setShowShare] = useState(false);
  const [shareUrl, setShareUrl] = useState("");

  const copy = async (text) => {
    try {
      await navigator.clipboard.writeText(text);
      toast.success(t("common.copy"));
    } catch {
      toast.error(t("common.copyFailed"));
    }
  };

  const info = getMemberInfo();
  const memberId = info?.member_id;

  const {
    data: inviteData,
    isLoading,
    isFetching,
    refetch,
  } = useGetInviteListQuery({ member_id: memberId }, { skip: !memberId });

  const { data: summaryData } = useGetPerformanceProfileQuery(
    { member_id: memberId },
    { skip: !memberId }
  );

  const [editInviteName] = useEditInviteNameMutation(); // ⭐ ADDED

  // ⭐ ADDED — localItems (for optimistic updates)
  const [localItems, setLocalItems] = useState([]);

  // ⭐ Populate localItems when backend data loads
  useEffect(() => {
    if (inviteData?.data) {
      setLocalItems(
        inviteData.data.map((it) => ({
          default: it.default === 1,
          invitation_id: it.invitation_id || it.recruit_id,
          code: it.referralCode,
          link: it.qr,
          invitecode_name: it.invitecode_name || "",
        }))
      );
    }
  }, [inviteData]);

  // ⭐ Find default item inside localItems
  const defaultItem = localItems.find((x) => x.default) || localItems[0] || {};

  const inviteCode = defaultItem?.code || "";
  const inviteLink = defaultItem?.link || "";

  // ⭐ Edit state for TOP CARD
  const [editingMain, setEditingMain] = useState(false);
  const [mainName, setMainName] = useState("");

  useEffect(() => {
    if (defaultItem) {
      setMainName(defaultItem.invitecode_name || "");
    }
  }, [defaultItem]);

  // ⭐ SAVE LOGIC — optimistic update
  const saveMainName = async () => {
    const newName = mainName.trim() || null;
    const prevName = defaultItem.invitecode_name;

    // 1️⃣ Update UI instantly
    setLocalItems((list) =>
      list.map((it) =>
        it.invitation_id === defaultItem.invitation_id
          ? { ...it, invitecode_name: newName }
          : it
      )
    );

    setEditingMain(false);

    try {
      await editInviteName({
        member_id: memberId,
        invitation_id: defaultItem.invitation_id,
        invitecode_name: newName,
      }).unwrap();

      refetch();
      toast.success(resp?.message || t("common.success"));
    } catch (err) {
      toast.error(t("common.failed"));

      // 2️⃣ rollback UI
      setLocalItems((list) =>
        list.map((it) =>
          it.invitation_id === defaultItem.invitation_id
            ? { ...it, invitecode_name: prevName }
            : it
        )
      );
    }
  };

  // ===============================
  // Broadcast Channel Refresh
  // ===============================
  useEffect(() => {
    const bc = new BroadcastChannel("invite_refresh");
    bc.onmessage = (event) => {
      if (event.data === "updated") {
        refetch();
      }
    };
    return () => bc.close();
  }, [refetch]);

  return (
    <div className="mx-auto max-w-[480px] min-h-[100vh] bg-[#00143D] text-white pb-8">
      {/* Header */}
      <div className="relative flex items-center h-14 px-4">
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
          {t("invite.title")}
        </h1>
      </div>

      {/* Top illustration */}
      <div className="flex justify-center py-6">
        <Image
          src={IMAGES.earn.iconGroupEarn}
          alt="promo"
          width={220}
          height={220}
          className="object-contain"
        />
      </div>

      {/* Main card */}
      <div className="bg-[#122346] p-4">
        <div className="rounded-xl border border-[white] p-4 space-y-4">
          {/* ⭐ TOP CARD — Editable name */}
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-1 text-[15px]">
              {editingMain ? (
                <input
                  type="text"
                  value={mainName}
                  placeholder={
                    defaultItem?.invitecode_name?.trim()
                      ? defaultItem.invitecode_name
                      : t("invite.myCode")
                  }
                  onChange={(e) => setMainName(e.target.value)}
                  className="
                    bg-transparent
                    border-b border-[#FFC000]
                    text-[#FFC000]
                    text-sm
                    outline-none
                    min-w-[140px]
                  "
                />
              ) : (
                <span>
                  {defaultItem?.invitecode_name?.trim()
                    ? defaultItem.invitecode_name
                    : t("invite.myCode")}
                </span>
              )}

              {editingMain ? (
                <button onClick={saveMainName}>
                  <Image
                    src={IMAGES.iconCheckmark}
                    alt="save"
                    width={18}
                    height={18}
                    className="object-contain"
                  />
                </button>
              ) : (
                <button onClick={() => setEditingMain(true)}>
                  <Image
                    src={IMAGES.iconPencil}
                    alt="edit"
                    width={16}
                    height={16}
                  />
                </button>
              )}
            </div>

            {/* right: default */}
            {defaultItem?.default && (
              <div className="flex items-center gap-1 text-xs text-white/70">
                <span>{t("invite.default")}</span>
                <Image
                  src={IMAGES.iconCircle}
                  alt="default"
                  width={20}
                  height={20}
                />
              </div>
            )}
          </div>

          <div className="h-px bg-[#354B9C]" />

          {/* Invite Code */}
          <div className="flex items-center justify-between text-sm">
            <span>{t("invite.inviteCode")}</span>
            <div className="flex items-center gap-2">
              <span className="tracking-wider text-[#FFFC8680]">
                {inviteCode}
              </span>
              <button
                className="p-1"
                onClick={() => copy(inviteCode)}
                aria-label="copy code"
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

          <div className="h-px bg-[#354B9C]" />

          {/* Invite Link */}
          <div className="flex items-center justify-between gap-3 text-sm">
            <span className="shrink-0">{t("invite.inviteLink")}</span>
            <div className="flex items-center gap-2 overflow-hidden">
              <MarqueeTitle
                stop={showShare}
                speedSec={25}
                title={inviteLink}
                selected={false}
                maxWidth={300}
              />
              <button
                className="p-1 shrink-0"
                onClick={() => copy(inviteLink)}
                aria-label="copy link"
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

          <SubmitButton
            onClick={() => {
              setShareUrl(inviteLink);
              setShowShare(true);
            }}
          >
            {t("invite.inviteNow")}
          </SubmitButton>
        </div>
      </div>

      {/* MORE BUTTON */}
      <button
        className="mt-4 mx-auto flex items-center justify-center gap-1 text-[#F8AF07]"
        onClick={() => {
          router.push("/promote/member/list");
        }}
      >
        <span>{t("invite.more")}</span>
        <div className="relative h-4 w-4">
          <Image
            src={IMAGES.iconYellowRight}
            alt="arrow"
            fill
            className="object-contain opacity-80"
          />
        </div>
      </button>

      {/* Stats */}
      <div className="mt-6 px-4 ">
        <div className="flex items-center justify-between mb-3">
          <Link href="/promote/member/history">
            <p className="text-sm">{t("invite.myStats")}</p>
          </Link>

          <div className="relative h-4 w-4">
            <Link href="/promote/member/history">
              <Image
                src={IMAGES.iconYellowRight}
                alt="arrow"
                fill
                className="object-contain opacity-80"
              />
            </Link>
          </div>
        </div>

        <div className="flex justify-between gap-3">
          <div className="flex-1 rounded-lg bg-[#0A1F58] p-3 text-center">
            <p className="text-xs">{t("invite.groupCount")}</p>
            <p className="text-2xl mt-1 font-semibold leading-7">
              {summaryData?.data.totaldownline}
            </p>
          </div>

          <div className="flex-1 rounded-lg bg-[#0A1F58] p-3 text-center">
            <p className="text-xs">{t("invite.tradeCount")}</p>
            <p className="text-2xl mt-1 font-semibold leading-7">
              {summaryData?.data.totalcredit}
            </p>
          </div>

          <div className="flex-1 rounded-lg bg-[#0A1F58] p-3 text-center">
            <p className="text-xs">{t("invite.myRebate")}</p>
            <p className="text-2xl mt-1 font-semibold leading-7">
              {summaryData?.data.totalcommission}
            </p>
          </div>
        </div>
      </div>

      <ShareDialog
        open={showShare}
        onClose={() => setShowShare(false)}
        text={t("earn.share.shareTitle")}
        url={shareUrl}
      />
    </div>
  );
}
